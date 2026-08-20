<?php

namespace App\Models;

use Database\Factories\QualityControlChecklistItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QualityControlChecklistItem extends Model
{
    /** @use HasFactory<QualityControlChecklistItemFactory> */
    use HasFactory;

    protected $fillable = [
        'section_code', 'section_name', 'question', 'check_procedure', 'points',
        'is_critical', 'requires_photo', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'is_critical' => 'boolean',
            'requires_photo' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function auditItems(): HasMany
    {
        return $this->hasMany(QualityControlAuditItem::class);
    }
}
