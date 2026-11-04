<?php

namespace App\Models;

use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'employee_code',
        'name',
        'position',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function salesReports(): HasManyThrough
    {
        return $this->hasManyThrough(
            SalesReport::class,
            SalesReportEmployee::class,
            'employee_id',
            'id',
            'id',
            'sales_report_id',
        );
    }

    /**
     * Staff Produksi on ESB Production Order details (docs/products/kitchen/production-staff-assignment/prd.md §14.3).
     */
    public function productionOrderStaffAssignments(): HasMany
    {
        return $this->hasMany(KitchenProductionOrderStaffAssignment::class);
    }
}
