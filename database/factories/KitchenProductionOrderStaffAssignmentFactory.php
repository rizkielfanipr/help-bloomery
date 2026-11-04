<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EsbProductionOrderDetail;
use App\Models\KitchenProductionOrderStaffAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KitchenProductionOrderStaffAssignment>
 */
class KitchenProductionOrderStaffAssignmentFactory extends Factory
{
    protected $model = KitchenProductionOrderStaffAssignment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'esb_production_order_detail_id' => EsbProductionOrderDetail::factory(),
            'employee_id' => Employee::factory(),
            'employee_code' => fake()->unique()->numerify('EMP######'),
            'employee_name' => fake()->name(),
            'employee_position' => 'Staff Produksi',
            'branch_id' => null,
            'branch_name' => 'Kitchen Contoh',
            'assigned_by' => null,
            'assigned_at' => now(),
        ];
    }

    /**
     * Snapshot the given employee as the Action does.
     */
    public function forEmployee(Employee $employee): static
    {
        return $this->state(fn (): array => [
            'employee_id' => $employee->id,
            'employee_code' => $employee->employee_code,
            'employee_name' => $employee->name,
            'employee_position' => $employee->position,
            'branch_id' => $employee->branch_id,
            'branch_name' => $employee->branch?->name ?? '-',
        ]);
    }
}
