<?php

namespace App\Models;

use Database\Factories\RndInternalMemoBranchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * docs/rnd-internal-memo-multi-branch-prd.md §9.1. One row per (Memo, resolved ESB mapping) pair
 * — a snapshot taken at the moment the branch was added to the Memo. Changes to the underlying
 * Master Branch mapping afterward never silently update this snapshot (§8); that requires the
 * explicit "Perbarui Mapping Branch" action.
 */
class RndInternalMemoBranch extends Model
{
    /** @use HasFactory<RndInternalMemoBranchFactory> */
    use HasFactory;

    protected $fillable = [
        'rnd_internal_memo_id',
        'branch_id',
        'branch_esb_code_id',
        'branch_name_snapshot',
        'company_code_snapshot',
        'branch_code_snapshot',
        'esb_branch_id_snapshot',
        'catalog_sync_status',
        'catalog_synced_at',
        'catalog_sync_error',
    ];

    protected function casts(): array
    {
        return [
            'esb_branch_id_snapshot' => 'integer',
            'catalog_synced_at' => 'datetime',
        ];
    }

    public function memo(): BelongsTo
    {
        return $this->belongsTo(RndInternalMemo::class, 'rnd_internal_memo_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function branchEsbCode(): BelongsTo
    {
        return $this->belongsTo(BranchEsbCode::class);
    }

    public function menus(): BelongsToMany
    {
        return $this->belongsToMany(RndInternalMemoMenu::class, 'rnd_internal_memo_menu_branches');
    }
}
