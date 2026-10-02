<?php

namespace App\Actions\StoreSalesOrder;

use App\Enums\StoreSalesOrderStatus;
use App\Models\StoreSalesOrder;
use App\Models\StoreSalesOrderActivity;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * docs/store-sales-order-prd.md §11, §16. Transition legality is delegated to
 * StoreSalesOrderStatus::canTransitionTo() so the state machine has exactly one definition.
 * Cancelled requires a reason; every other transition clears any leftover reason.
 */
class UpdateStoreSalesOrderStatusAction
{
    public function execute(StoreSalesOrder $order, StoreSalesOrderStatus $next, ?string $cancellationReason, User $actor): StoreSalesOrder
    {
        $current = $order->operational_status;

        if (! $current->canTransitionTo($next)) {
            throw ValidationException::withMessages([
                'operational_status' => "Status tidak dapat diubah dari {$current->getLabel()} ke {$next->getLabel()}.",
            ]);
        }

        $reason = $next->requiresCancellationReason() ? trim((string) $cancellationReason) : null;

        if ($next->requiresCancellationReason() && $reason === '') {
            throw ValidationException::withMessages([
                'cancellation_reason' => 'Alasan pembatalan wajib diisi.',
            ]);
        }

        return DB::transaction(function () use ($order, $current, $next, $reason, $actor): StoreSalesOrder {
            $order->update([
                'operational_status' => $next,
                'cancellation_reason' => $reason,
                'updated_by' => $actor->id,
            ]);

            $order->activities()->create([
                'activity_type' => StoreSalesOrderActivity::TYPE_STATUS_CHANGED,
                'previous_status' => $current->value,
                'new_status' => $next->value,
                'notes' => $reason,
                'created_by' => $actor->id,
            ]);

            return $order->refresh();
        });
    }
}
