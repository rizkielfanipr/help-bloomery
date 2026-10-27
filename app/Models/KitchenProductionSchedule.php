<?php

namespace App\Models;

use App\Enums\KitchenProductionScheduleStatus;
use Database\Factories\KitchenProductionScheduleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One Kitchen production plan for one Branch on one local production date
 * (docs/products/kitchen/production-schedule/prd.md §11.1). Status changes only through the
 * Kitchen Actions, which lock the row. There is no global Branch scope:
 * callers apply `scopeVisibleTo()` explicitly.
 */
class KitchenProductionSchedule extends Model
{
    /** @use HasFactory<KitchenProductionScheduleFactory> */
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'production_date',
        'status',
        'notes',
        'created_by',
        'updated_by',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected $attributes = [
        'status' => 'scheduled',
    ];

    protected function casts(): array
    {
        return [
            'production_date' => 'immutable_date',
            'status' => KitchenProductionScheduleStatus::class,
            'cancelled_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(KitchenProductionScheduleItem::class)->orderBy('sequence');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * Branch scope in SQL, mirroring `KitchenProductionSchedulePolicy::view()`: everything for
     * all-branch users, otherwise only the user's accessible Branches.
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->canAccessAllBranches() || $user->can('view all branch kitchen production schedules')) {
            return;
        }

        $query
            ->whereHas('branch', fn (Builder $branchQuery): Builder => $branchQuery->kitchenBrand())
            ->whereIn('branch_id', $user->accessibleBranchIds());
    }
}
