<?php

namespace App\Models;

use Database\Factories\BranchSalesShiftFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BranchSalesShift extends Model
{
    /** @use HasFactory<BranchSalesShiftFactory> */
    use HasFactory;

    protected $fillable = ['branch_id', 'shift_number', 'name', 'start_time', 'end_time', 'is_active'];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['shift_number' => 'integer', 'is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::created(fn (self $shift) => $shift->markBranchShiftsChanged());
        static::updated(fn (self $shift) => $shift->markBranchShiftsChanged());
        static::deleted(fn (self $shift) => $shift->markBranchShiftsChanged());
    }

    /**
     * Basket size calculated before this moment has to be recalculated with the new shift hours.
     */
    public function markBranchShiftsChanged(): void
    {
        Branch::query()->whereKey($this->branch_id)->update(['shifts_changed_at' => now()]);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function basketSizeRecords(): HasMany
    {
        return $this->hasMany(BasketSizeRecord::class);
    }
}
