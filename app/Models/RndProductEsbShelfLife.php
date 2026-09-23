<?php

namespace App\Models;

use Database\Factories\RndProductEsbShelfLifeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Local Shelf Life master (docs/rnd-internal-memo-prd.md §11). Not sourced from ESB; the API
 * does not provide Shelf Life.
 */
class RndProductEsbShelfLife extends Model
{
    /** @use HasFactory<RndProductEsbShelfLifeFactory> */
    use HasFactory;

    use SoftDeletes;

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

    protected function casts(): array
    {
        return [
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
     * The single active master matching a Menu, if any (docs/rnd-internal-memo-prd.md §7.3).
     */
    public static function forMenu(string $companyCode, int $esbMenuId): ?self
    {
        return static::query()
            ->where('company_code', $companyCode)
            ->where('esb_menu_id', $esbMenuId)
            ->where('is_active', true)
            ->latest('id')
            ->first();
    }
}
