<?php

namespace App\Models;

use Database\Factories\KitchenProductionOrderStaffAssignmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One active Staff Produksi on one ESB Production Order detail
 * (docs/products/kitchen/production-staff-assignment/prd.md §14.1). Local data only: it is never sent
 * to ESB and the sync never touches it. Rows are written only by `AssignProductionOrderStaffAction`;
 * the employee and Branch identity is snapshotted so the row stays readable after the employee is
 * deactivated, moves Branch, or is deleted.
 */
class KitchenProductionOrderStaffAssignment extends Model
{
    /** @use HasFactory<KitchenProductionOrderStaffAssignmentFactory> */
    use HasFactory;

    protected $fillable = [
        'esb_production_order_detail_id',
        'employee_id',
        'employee_code',
        'employee_name',
        'employee_position',
        'branch_id',
        'branch_name',
        'assigned_by',
        'assigned_at',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
        ];
    }

    public function detail(): BelongsTo
    {
        return $this->belongsTo(EsbProductionOrderDetail::class, 'esb_production_order_detail_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * Read-only warning for an existing assignment (prd.md §17.1, FR-13), compared against the
     * Branch of the order it belongs to. Null when the employee is still eligible.
     */
    public function historyWarning(?int $orderBranchId): ?string
    {
        return match (true) {
            $this->employee === null => 'Data employee dihapus',
            ! $this->employee->is_active => 'Nonaktif',
            $this->employee->branch_id !== $orderBranchId => 'Branch berubah',
            default => null,
        };
    }
}
