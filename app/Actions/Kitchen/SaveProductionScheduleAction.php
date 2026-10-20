<?php

namespace App\Actions\Kitchen;

use App\Enums\KitchenProductionScheduleStatus;
use App\Models\KitchenProductionSchedule;
use App\Models\KitchenProductionScheduleItem;
use App\Models\User;
use App\Services\Kitchen\ProductionScheduleValidator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SaveProductionScheduleAction
{
    public function __construct(private readonly ProductionScheduleValidator $validator) {}

    /**
     * @param  array{branch_id?: mixed, production_date?: mixed, notes?: mixed, items?: mixed}  $input
     */
    public function execute(
        User $actor,
        array $input,
        ?KitchenProductionSchedule $schedule = null,
        ?string $expectedUpdatedAt = null,
    ): KitchenProductionSchedule {
        if ($schedule === null && ! $actor->can('create kitchen production schedules')) {
            throw new AuthorizationException('Anda tidak berhak membuat jadwal produksi.');
        }

        $data = $this->validator->validate($actor, $input);

        try {
            return DB::transaction(function () use ($actor, $data, $schedule, $expectedUpdatedAt): KitchenProductionSchedule {
                $locked = $schedule === null
                    ? null
                    : KitchenProductionSchedule::query()->whereKey($schedule->getKey())->lockForUpdate()->firstOrFail();

                if ($locked !== null) {
                    Gate::forUser($actor)->authorize('update', $locked);

                    if ($locked->status !== KitchenProductionScheduleStatus::Scheduled) {
                        throw ValidationException::withMessages(['schedule' => 'Jadwal yang sudah dibatalkan tidak dapat diubah.']);
                    }

                    if ($expectedUpdatedAt !== null && $locked->updated_at?->toIso8601String() !== $expectedUpdatedAt) {
                        throw ValidationException::withMessages(['schedule' => 'Jadwal sudah diubah oleh pengguna lain. Muat ulang sebelum menyimpan.']);
                    }
                }

                $this->validator->ensureSlotIsFree(
                    $data['branch']->id,
                    $data['production_date'],
                    $locked?->id,
                );

                $saved = $locked ?? KitchenProductionSchedule::query()->create([
                    'branch_id' => $data['branch']->id,
                    'production_date' => $data['production_date'],
                    'status' => KitchenProductionScheduleStatus::Scheduled,
                    'notes' => $data['notes'],
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                ]);

                if ($locked !== null) {
                    KitchenProductionScheduleItem::query()
                        ->where('kitchen_production_schedule_id', $locked->id)
                        ->delete();

                    $saved->fill([
                        'branch_id' => $data['branch']->id,
                        'production_date' => $data['production_date'],
                        'notes' => $data['notes'],
                        'updated_by' => $actor->id,
                    ]);
                    $saved->updated_at = now();
                    $saved->save();
                }

                ProductionScheduleItemWriter::write($saved, $data['items']);

                return $saved->refresh();
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'production_date' => 'Jadwal untuk Branch dan tanggal ini sudah ada.',
            ]);
        }
    }
}
