<?php

namespace App\Services\Kitchen;

use App\Models\Employee;
use App\Models\EsbProductionOrder;
use App\Models\EsbProductionOrderDetail;
use App\Models\EsbProductionOrderSyncRun;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Gate;

/**
 * Read-only detail of one ESB Production Order snapshot (docs/products/kitchen/production-schedule/prd.md §32,
 * §34.3) with its local Staff Produksi (docs/products/kitchen/production-staff-assignment/prd.md §17). The
 * record is re-queried by its snapshot ID and re-authorized on every call; nothing from the browser
 * other than that ID is used, and ESB is never called here.
 */
class ProductionOrderDetailBuilder
{
    public const STAFF_CANDIDATE_LIMIT = 30;

    /**
     * @return array{order: EsbProductionOrder, notSeenInLatestSync: bool, detailsPending: bool, canAssignStaff: bool, staffedDetails: int}
     *
     * @throws AuthorizationException
     */
    public function build(User $user, int $orderId): array
    {
        $order = EsbProductionOrder::query()
            ->with([
                'branch:id,brand_id,name,is_active',
                'details' => fn (HasMany $query): HasMany => $query->orderBy('sequence')->orderBy('id'),
                'details.staffAssignments' => fn (HasMany $query): HasMany => $query->orderBy('employee_name')->orderBy('id'),
                'details.staffAssignments.employee:id,branch_id,is_active',
                'details.staffAssignments.assignedBy:id,name',
            ])
            // Kitchen-Brand only even when Gate::before lets SUPERADMIN past the Policy (prd.md §28.6).
            ->whereHas('branch', fn (Builder $query): Builder => $query->kitchenBrand())
            ->findOrFail($orderId);

        Gate::forUser($user)->authorize('view', $order);

        return [
            'order' => $order,
            'notSeenInLatestSync' => $this->notSeenInLatestSync($order),
            'detailsPending' => $order->details_synced_at === null,
            'canAssignStaff' => $order->details_synced_at !== null
                && $order->branch->is_active
                && Gate::forUser($user)->allows('assignStaff', $order),
            'staffedDetails' => $order->details->filter(fn (EsbProductionOrderDetail $detail): bool => $detail->staffAssignments->isNotEmpty())->count(),
        ];
    }

    /**
     * One detail for the Staff Produksi picker, authorized for assignment (staff-assignment prd.md §16).
     *
     * @throws AuthorizationException
     * @throws ModelNotFoundException
     */
    public function staffPickerDetail(User $user, int $detailId): EsbProductionOrderDetail
    {
        $detail = EsbProductionOrderDetail::query()
            ->with([
                'order.branch:id,brand_id,name,is_active',
                'staffAssignments' => fn (HasMany $query): HasMany => $query->orderBy('employee_name')->orderBy('id'),
                'staffAssignments.employee:id,branch_id,is_active',
            ])
            ->whereHas('order.branch', fn (Builder $query): Builder => $query->kitchenBrand())
            ->findOrFail($detailId);

        if ($detail->order->details_synced_at === null || ! $detail->order->branch->is_active) {
            throw new AuthorizationException;
        }

        Gate::forUser($user)->authorize('assignStaff', $detail->order);

        return $detail;
    }

    /**
     * Eligible new Staff Produksi (OD-1): active employees of the order's Branch, not yet assigned,
     * searched by name, employee code, or position, capped at one page.
     *
     * @return array{employees: EloquentCollection<int, Employee>, hasMore: bool}
     */
    public function staffCandidates(EsbProductionOrderDetail $detail, string $search): array
    {
        $term = mb_substr(trim($search), 0, 100);

        $employees = Employee::query()
            ->select(['id', 'employee_code', 'name', 'position'])
            ->where('branch_id', $detail->order->branch_id)
            ->where('is_active', true)
            ->whereNotIn('id', $detail->staffAssignments->pluck('employee_id')->filter()->all())
            ->when($term !== '', function (Builder $query) use ($term): void {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $query->where(fn (Builder $match): Builder => $match
                    ->where('name', 'like', $like)
                    ->orWhere('employee_code', 'like', $like)
                    ->orWhere('position', 'like', $like));
            })
            ->orderBy('name')
            ->orderBy('id')
            ->limit(self::STAFF_CANDIDATE_LIMIT + 1)
            ->get();

        return [
            'employees' => $employees->take(self::STAFF_CANDIDATE_LIMIT),
            'hasMore' => $employees->count() > self::STAFF_CANDIDATE_LIMIT,
        ];
    }

    /**
     * True when a complete list read of the order's mapping, covering its date, started after the
     * order was last seen — i.e. ESB no longer returned it (decision K11). It stays visible.
     */
    private function notSeenInLatestSync(EsbProductionOrder $order): bool
    {
        if ($order->branch_esb_code_id === null || $order->source_last_seen_at === null) {
            return false;
        }

        return EsbProductionOrderSyncRun::query()
            ->where('branch_esb_code_id', $order->branch_esb_code_id)
            ->whereDate('date_from', '<=', $order->production_order_date->toDateString())
            ->whereDate('date_to', '>=', $order->production_order_date->toDateString())
            ->whereNotNull('list_completed_at')
            ->where('started_at', '>', $order->source_last_seen_at)
            ->exists();
    }
}
