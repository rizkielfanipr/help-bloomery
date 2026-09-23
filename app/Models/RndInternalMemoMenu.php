<?php

namespace App\Models;

use App\Enums\RndInternalMemoMenuSyncStatus;
use Database\Factories\RndInternalMemoMenuFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * docs/rnd-internal-memo-prd.md §12.2. One row per ESB Menu added to a memo. `esb_bom_id`
 * mirrors the Menu's own `bomID`; a Menu with `bomID = 0` cannot be added at all
 * (§9.4 / §15.1), so this column is never 0 for a persisted row.
 */
class RndInternalMemoMenu extends Model
{
    /** @use HasFactory<RndInternalMemoMenuFactory> */
    use HasFactory;

    protected $fillable = [
        'rnd_internal_memo_id',
        'esb_menu_id',
        'menu_code',
        'menu_name',
        'category_detail',
        'esb_bom_id',
        'bom_name',
        'release_date',
        'forecast_quantity',
        'shelf_life_value',
        'shelf_life_unit',
        'storage_condition',
        'shelf_life_notes',
        'sync_status',
        'synced_at',
        'sync_error',
        'sync_warnings',
        'menu_snapshot',
        'bom_snapshot',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'release_date' => 'date',
            'forecast_quantity' => 'decimal:2',
            'shelf_life_value' => 'decimal:2',
            'sync_status' => RndInternalMemoMenuSyncStatus::class,
            'synced_at' => 'datetime',
            'sync_warnings' => 'array',
            'menu_snapshot' => 'array',
            'bom_snapshot' => 'array',
            'sort_order' => 'integer',
        ];
    }

    public function memo(): BelongsTo
    {
        return $this->belongsTo(RndInternalMemo::class, 'rnd_internal_memo_id');
    }

    public function materials(): HasMany
    {
        return $this->hasMany(RndInternalMemoMaterial::class);
    }

    /** Root-level materials only (packaging and top-level ingredients, depth 0). */
    public function rootMaterials(): HasMany
    {
        return $this->materials()->whereNull('parent_material_id');
    }

    public function hasShelfLife(): bool
    {
        return $this->shelf_life_value !== null && $this->shelf_life_unit !== null;
    }
}
