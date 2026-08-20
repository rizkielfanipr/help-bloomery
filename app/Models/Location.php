<?php

namespace App\Models;

use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    /** @use HasFactory<LocationFactory> */
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'parent_id',
        'name',
        'type',
        'segment',
        'code',
        'depth',
        'sort_order',
        'pos_x',
        'pos_y',
        'width',
        'height',
        'rotation',
        'is_active',
        'qr_svg_path',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'depth' => 'integer',
            'sort_order' => 'integer',
            'pos_x' => 'float',
            'pos_y' => 'float',
            'width' => 'float',
            'height' => 'float',
            'rotation' => 'float',
            'is_active' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function isDrawable(): bool
    {
        return $this->width !== null && $this->height !== null;
    }

    /** @return array<int, self> */
    public function ancestors(): array
    {
        $ancestors = [];
        $node = $this->parent;

        while ($node !== null) {
            array_unshift($ancestors, $node);
            $node = $node->parent;
        }

        return $ancestors;
    }
}
