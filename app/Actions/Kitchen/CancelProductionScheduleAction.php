<?php

namespace App\Actions\Kitchen;

use App\Enums\KitchenProductionScheduleStatus;
use App\Models\KitchenProductionSchedule;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Cancels a scheduled production plan without deleting its items or lightweight snapshots.
 */
class CancelProductionScheduleAction
{
    public const MAX_REASON_LENGTH = 1000;

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function execute(User $actor, KitchenProductionSchedule $schedule, ?string $reason): KitchenProductionSchedule
    {
        $reason = trim((string) $reason);

        if ($reason === '') {
            throw ValidationException::withMessages(['cancellation_reason' => 'Alasan pembatalan wajib diisi.']);
        }

        if (mb_strlen($reason) > self::MAX_REASON_LENGTH) {
            throw ValidationException::withMessages(['cancellation_reason' => 'Alasan pembatalan maksimal 1.000 karakter.']);
        }

        return DB::transaction(function () use ($actor, $schedule, $reason): KitchenProductionSchedule {
            $locked = KitchenProductionSchedule::query()->whereKey($schedule->getKey())->lockForUpdate()->firstOrFail();

            Gate::forUser($actor)->authorize('view', $locked);

            if ($locked->status === KitchenProductionScheduleStatus::Cancelled) {
                return $locked;
            }

            Gate::forUser($actor)->authorize('cancel', $locked);

            $locked->update([
                'status' => KitchenProductionScheduleStatus::Cancelled,
                'cancelled_by' => $actor->id,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
                'updated_by' => $actor->id,
            ]);

            return $locked;
        });
    }
}
