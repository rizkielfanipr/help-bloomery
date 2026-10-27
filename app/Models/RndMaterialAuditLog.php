<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Append-only Master Bahan workflow audit: events and technical field changes (§19.2, §27).
 */
class RndMaterialAuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'rnd_product_esb_material_id',
        'rnd_material_review_id',
        'event',
        'field_path',
        'before_value',
        'after_value',
        'from_status',
        'to_status',
        'actor_id',
        'reason',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'before_value' => 'array',
            'after_value' => 'array',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Audit log Master Bahan bersifat append-only.'));
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(RndProductEsbMaterial::class, 'rnd_product_esb_material_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
