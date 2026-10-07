<?php

namespace App\Models;

use App\Enums\RndShelfLifeUnit;
use App\Enums\RndStorageCondition;
use Database\Factories\RndProductEsbShelfLifeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Local Shelf Life master per WIP / Product Detail (docs/rnd-wip-shelf-life-prd.md §9.1, §12).
 * Identity is `company_code + esb_product_detail_id`; `product_code`/`product_name` are display
 * snapshots only. Rows that only carry the legacy `esb_menu_id` (the retired Menu master,
 * docs/rnd-internal-memo-prd.md §11) never take part in WIP lookups. Never synced to ESB.
 */
class RndProductEsbShelfLife extends Model
{
    /** @use HasFactory<RndProductEsbShelfLifeFactory> */
    use HasFactory;

    use LogsActivity;
    use SoftDeletes;

    /**
     * Company context of BOM Adjustment and R&D local masters on the MVP (§9.2).
     */
    public const DEFAULT_COMPANY_CODE = 'BLSS';

    public const MAX_SHELF_LIFE_VALUE = 9999.99;

    protected $table = 'rnd_esb_product_shelf_lives';

    protected $fillable = [
        'company_code',
        'esb_product_id',
        'esb_product_detail_id',
        'esb_menu_id',
        'product_code',
        'product_name',
        'shelf_life_value',
        'shelf_life_unit',
        'storage_condition',
        'notes',
        'effective_from',
        'effective_until',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $attributes = [
        'company_code' => self::DEFAULT_COMPANY_CODE,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'esb_product_detail_id' => 'integer',
            'shelf_life_value' => 'decimal:2',
            'effective_from' => 'date',
            'effective_until' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * WIP masters only — legacy Menu-only rows (no Product Detail ID) are excluded.
     *
     * @param  Builder<RndProductEsbShelfLife>  $query
     */
    public function scopeWipMaster(Builder $query, string $companyCode = self::DEFAULT_COMPANY_CODE): void
    {
        $query->where('company_code', $companyCode)->whereNotNull('esb_product_detail_id');
    }

    /**
     * @param  Builder<RndProductEsbShelfLife>  $query
     */
    public function scopeActiveWipMaster(Builder $query, string $companyCode = self::DEFAULT_COMPANY_CODE): void
    {
        $query->wipMaster($companyCode)->where('is_active', true);
    }

    /**
     * The stored unit as the enum; legacy labels are mapped explicitly, unknown values give null.
     */
    public function shelfLifeUnit(): ?RndShelfLifeUnit
    {
        return RndShelfLifeUnit::fromLegacy($this->shelf_life_unit);
    }

    public function storageCondition(): ?RndStorageCondition
    {
        return RndStorageCondition::tryFrom(mb_strtolower(trim((string) $this->storage_condition)));
    }

    /**
     * Human label such as "3 Hari"; falls back to the raw stored unit for unmapped legacy data.
     */
    public function shelfLifeLabel(): string
    {
        $value = rtrim(rtrim((string) $this->shelf_life_value, '0'), '.');

        return trim($value.' '.($this->shelfLifeUnit()?->getLabel() ?? $this->shelf_life_unit));
    }

    public function storageConditionLabel(): string
    {
        return $this->storageCondition()?->getLabel() ?? (string) $this->storage_condition;
    }

    /**
     * Only business fields are audited (§23); actor columns and timestamps are not.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['shelf_life_value', 'shelf_life_unit', 'storage_condition', 'notes', 'is_active'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
