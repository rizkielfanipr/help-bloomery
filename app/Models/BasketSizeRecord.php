<?php

namespace App\Models;

use Database\Factories\BasketSizeRecordFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BasketSizeRecord extends Model
{
    /** @use HasFactory<BasketSizeRecordFactory> */
    use HasFactory;

    protected $fillable = [
        'sales_report_id', 'branch_id', 'branch_sales_shift_id', 'report_date',
        'shift_number', 'shift_name', 'shift_start_time', 'shift_end_time',
        'revenue', 'total_pax', 'basket_size', 'staff_count', 'calculated_at', 'finalized_at',
    ];

    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'revenue' => 'decimal:2',
            'basket_size' => 'decimal:2',
            'calculated_at' => 'datetime',
            'finalized_at' => 'datetime',
        ];
    }

    /**
     * Whether the values come from the complete shift window rather than a submit-time snapshot.
     */
    /**
     * Final records that were calculated before the shifts of their branch last changed and that can be recalculated.
     *
     * @param  Builder<BasketSizeRecord>  $query
     * @return Builder<BasketSizeRecord>
     */
    public function scopeStaleAfterShiftChange(Builder $query): Builder
    {
        return $query->whereNotNull('finalized_at')->whereExists(fn ($branch) => $branch
            ->selectRaw('1')
            ->from('branches')
            ->whereColumn('branches.id', 'basket_size_records.branch_id')
            ->whereNotNull('branches.shifts_changed_at')
            ->whereColumn('basket_size_records.calculated_at', '<', 'branches.shifts_changed_at')
            ->whereExists(fn ($esb) => $esb
                ->selectRaw('1')
                ->from('branch_esb_codes')
                ->whereColumn('branch_esb_codes.branch_id', 'branches.id')
                ->where('branch_esb_codes.is_active', true)));
    }

    public function isFinal(): bool
    {
        return $this->finalized_at !== null;
    }

    public function salesReport(): BelongsTo
    {
        return $this->belongsTo(SalesReport::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(BranchSalesShift::class, 'branch_sales_shift_id');
    }

    public function employeeRecords(): HasMany
    {
        return $this->hasMany(BasketSizeEmployeeRecord::class);
    }
}
