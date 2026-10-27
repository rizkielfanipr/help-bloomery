<?php

namespace App\Services\Kitchen;

use App\Enums\KitchenProductionScheduleStatus;
use App\Models\KitchenProductionSchedule;
use App\Models\KitchenProductionScheduleItem;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * Everything the schedule detail shows, loaded here so Blade runs no query
 * (docs/products/kitchen/production-schedule/prd.md §12). Cancelled items stay readable from their snapshot.
 */
class ProductionScheduleDetailBuilder
{
    /**
     * @return array{
     *     schedule: KitchenProductionSchedule,
     *     items: list<array{item: KitchenProductionScheduleItem, notice: ?string}>,
     *     canUpdate: bool,
     *     canCancel: bool,
     * }
     *
     * @throws AuthorizationException
     */
    public function build(User $user, int $scheduleId): array
    {
        $schedule = KitchenProductionSchedule::query()
            ->with([
                'branch:id,brand_id,name,is_active',
                'creator:id,name',
                'updater:id,name',
                'canceller:id,name',
                'items.catalog',
            ])
            ->findOrFail($scheduleId);

        Gate::forUser($user)->authorize('view', $schedule);

        $isScheduled = $schedule->status === KitchenProductionScheduleStatus::Scheduled;

        return [
            'schedule' => $schedule,
            'items' => $schedule->items
                ->map(fn (KitchenProductionScheduleItem $item): array => ['item' => $item, 'notice' => $this->notice($item)])
                ->values()
                ->all(),
            'canUpdate' => $isScheduled && Gate::forUser($user)->allows('update', $schedule),
            'canCancel' => $schedule->status !== KitchenProductionScheduleStatus::Cancelled && Gate::forUser($user)->allows('cancel', $schedule),
        ];
    }

    /**
     * Historical catalog differences are informational and never change the saved snapshot.
     */
    private function notice(KitchenProductionScheduleItem $item): ?string
    {
        $catalog = $item->catalog;

        return match (true) {
            $catalog === null => 'BOM tidak lagi ada di katalog; data diambil dari snapshot.',
            ! $catalog->is_active => 'BOM saat ini tidak aktif di ESB; jadwal tetap memakai snapshot.',
            default => null,
        };
    }
}
