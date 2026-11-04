<?php

namespace App\Actions\Kitchen;

use App\Enums\KitchenProductionOrderStaffAssignmentEventType;
use App\Models\Employee;
use App\Models\EsbProductionOrder;
use App\Models\EsbProductionOrderDetail;
use App\Models\KitchenProductionOrderStaffAssignment;
use App\Models\KitchenProductionOrderStaffAssignmentEvent;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Replaces the Staff Produksi of one ESB Production Order detail with the given selection
 * (docs/products/kitchen/production-staff-assignment/prd.md §11, §15, §16). Local data only: ESB is never
 * called and no snapshot field changes. Everything from the browser is re-queried inside one
 * transaction that locks the detail row, so concurrent saves are serialized; only the diff is
 * written, a no-op writes nothing, and one ineligible employee rolls the whole change back.
 */
class AssignProductionOrderStaffAction
{
    public const MAX_STAFF_PER_DETAIL = 50;

    /**
     * @param  array<int, mixed>  $employeeIds  desired selection
     * @param  list<int>|null  $expectedAssignmentIds  assignment IDs the user saw; a mismatch means someone else saved first
     * @return array{assigned: int, unassigned: int}
     *
     * @throws AuthorizationException
     * @throws ModelNotFoundException
     * @throws ValidationException
     */
    public function execute(User $actor, int $detailId, array $employeeIds, ?array $expectedAssignmentIds = null): array
    {
        $selection = $this->normalize($employeeIds);

        return DB::transaction(function () use ($actor, $detailId, $selection, $expectedAssignmentIds): array {
            $detail = EsbProductionOrderDetail::query()->whereKey($detailId)->lockForUpdate()->firstOrFail();

            // Kitchen-Brand only even when Gate::before lets SUPERADMIN past the Policy (production-schedule prd.md §28.6).
            $order = EsbProductionOrder::query()
                ->with('branch:id,brand_id,name,is_active')
                ->whereKey($detail->esb_production_order_id)
                ->whereHas('branch', fn (Builder $query): Builder => $query->kitchenBrand())
                ->firstOrFail();

            if (! $order->branch->is_active) {
                throw new AuthorizationException('Branch Kitchen Production Order ini nonaktif.');
            }

            Gate::forUser($actor)->authorize('assignStaff', $order);

            if ($order->details_synced_at === null) {
                throw ValidationException::withMessages(['employeeIds' => 'Detail Production Order belum selesai disinkronkan dari ESB.']);
            }

            $current = $detail->staffAssignments()->orderBy('id')->lockForUpdate()->get();

            if ($expectedAssignmentIds !== null && collect($expectedAssignmentIds)->map(fn (mixed $id): int => (int) $id)->sort()->values()->all() !== $current->modelKeys()) {
                throw ValidationException::withMessages(['employeeIds' => 'Staff Produksi pada detail ini baru saja diubah pengguna lain. Buka ulang pilihan lalu simpan kembali.']);
            }

            $assignedEmployeeIds = $current->pluck('employee_id')->filter()->all();
            // A row whose employee was deleted can no longer be selected, so any save releases it.
            $toRelease = $current->filter(fn (KitchenProductionOrderStaffAssignment $assignment): bool => $assignment->employee_id === null
                || ! in_array($assignment->employee_id, $selection, true));
            $toAddIds = array_values(array_diff($selection, $assignedEmployeeIds));

            if ($toRelease->isEmpty() && $toAddIds === []) {
                return ['assigned' => 0, 'unassigned' => 0];
            }

            // OD-1: new staff must be active and belong to the order's Branch at transaction time.
            $newEmployees = Employee::query()
                ->whereIn('id', $toAddIds)
                ->where('is_active', true)
                ->where('branch_id', $order->branch_id)
                ->sharedLock()
                ->get(['id', 'branch_id', 'employee_code', 'name', 'position']);

            if ($newEmployees->count() !== count($toAddIds)) {
                throw ValidationException::withMessages(['employeeIds' => 'Sebagian Staff Produksi yang dipilih tidak aktif atau bukan dari Branch Production Order ini.']);
            }

            $metadata = ['detail_finished' => (bool) $detail->is_finished];

            foreach ($toRelease as $assignment) {
                $this->recordEvent($order, $detail, $actor, KitchenProductionOrderStaffAssignmentEventType::Unassigned, [
                    'employee_id' => $assignment->employee_id,
                    'employee_code' => $assignment->employee_code,
                    'employee_name' => $assignment->employee_name,
                    'employee_position' => $assignment->employee_position,
                ], $assignment->employee_id === null ? [...$metadata, 'reason' => 'employee_deleted'] : $metadata);
            }

            KitchenProductionOrderStaffAssignment::query()->whereKey($toRelease->modelKeys())->delete();

            foreach ($newEmployees as $employee) {
                $identity = [
                    'employee_id' => $employee->id,
                    'employee_code' => $employee->employee_code,
                    'employee_name' => $employee->name,
                    'employee_position' => $employee->position,
                ];

                $detail->staffAssignments()->create([
                    ...$identity,
                    'branch_id' => $order->branch_id,
                    'branch_name' => $order->branch->name,
                    'assigned_by' => $actor->id,
                    'assigned_at' => now(),
                ]);

                $this->recordEvent($order, $detail, $actor, KitchenProductionOrderStaffAssignmentEventType::Assigned, $identity, $metadata);
            }

            return ['assigned' => $newEmployees->count(), 'unassigned' => $toRelease->count()];
        });
    }

    /**
     * Rejects non-integer, duplicate, and oversized selections instead of silently fixing them (prd.md §16.8).
     *
     * @param  array<int, mixed>  $employeeIds
     * @return list<int>
     *
     * @throws ValidationException
     */
    private function normalize(array $employeeIds): array
    {
        Validator::make(['employeeIds' => array_values($employeeIds)], [
            'employeeIds' => ['array', 'max:'.self::MAX_STAFF_PER_DETAIL],
            'employeeIds.*' => ['integer', 'min:1', 'distinct'],
        ], [
            'employeeIds.max' => 'Maksimal '.self::MAX_STAFF_PER_DETAIL.' Staff Produksi per detail.',
            'employeeIds.*.integer' => 'Pilihan Staff Produksi tidak valid.',
            'employeeIds.*.min' => 'Pilihan Staff Produksi tidak valid.',
            'employeeIds.*.distinct' => 'Staff Produksi yang sama dipilih lebih dari sekali.',
        ])->validate();

        return array_map(fn (mixed $id): int => (int) $id, array_values($employeeIds));
    }

    /**
     * @param  array{employee_id: ?int, employee_code: string, employee_name: string, employee_position: ?string}  $identity
     * @param  array<string, mixed>  $metadata
     */
    private function recordEvent(
        EsbProductionOrder $order,
        EsbProductionOrderDetail $detail,
        User $actor,
        KitchenProductionOrderStaffAssignmentEventType $event,
        array $identity,
        array $metadata,
    ): void {
        KitchenProductionOrderStaffAssignmentEvent::query()->create([
            ...$identity,
            'esb_production_order_id' => $order->id,
            'esb_production_order_detail_id' => $detail->id,
            'event' => $event,
            'branch_id' => $order->branch_id,
            'branch_name' => $order->branch->name,
            'production_order_num' => $order->production_order_num,
            'bom_name' => $detail->bom_name,
            'product_name' => $detail->product_name,
            'actor_id' => $actor->id,
            'metadata' => $metadata,
        ]);
    }
}
