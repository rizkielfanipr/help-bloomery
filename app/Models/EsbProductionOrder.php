<?php

namespace App\Models;

use Database\Factories\EsbProductionOrderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Read-only snapshot of one ESB Production Order (docs/products/kitchen/production-schedule/prd.md §26, §29.1,
 * §38). It is never written back to ESB.
 */
class EsbProductionOrder extends Model
{
    /** @use HasFactory<EsbProductionOrderFactory> */
    use HasFactory;

    protected $fillable = [
        'branch_esb_code_id', 'branch_id', 'company_code', 'esb_branch_id', 'esb_branch_name',
        'production_order_num', 'production_order_date', 'bom_type_id', 'bom_type_name', 'status_id', 'status_name',
        'additional_info', 'created_by_name', 'esb_created_at', 'edited_by_name', 'esb_edited_at',
        'authorized_by_name', 'esb_authorized_at', 'source_fetched_at', 'source_last_seen_at', 'details_synced_at',
        'payload_hash',
    ];

    protected function casts(): array
    {
        return [
            'esb_branch_id' => 'integer',
            'production_order_date' => 'date',
            'esb_created_at' => 'datetime',
            'esb_edited_at' => 'datetime',
            'esb_authorized_at' => 'datetime',
            'source_fetched_at' => 'datetime',
            'source_last_seen_at' => 'datetime',
            'details_synced_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function mapping(): BelongsTo
    {
        return $this->belongsTo(BranchEsbCode::class, 'branch_esb_code_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(EsbProductionOrderDetail::class);
    }

    /**
     * docs/products/kitchen/production-schedule/prd.md §28.6, §32: Kitchen-Brand Branches only for every user;
     * all-Branch access and `view all branch kitchen production schedules` only lift the Branch
     * assignment limit, never the Brand limit.
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->whereHas('branch', fn (Builder $branchQuery): Builder => $branchQuery->kitchenBrand());

        if (! $user->canAccessAllBranches() && ! $user->can('view all branch kitchen production schedules')) {
            $query->whereIn('branch_id', $user->accessibleBranchIds());
        }
    }

    public function label(): string
    {
        return $this->status_name ?? ($this->status_id !== null ? "Status {$this->status_id}" : 'Tanpa status');
    }
}
