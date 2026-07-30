<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class RndProjectBom extends Model
{
    protected $fillable = [
        'rnd_project_id',
        'esb_bom_id',
        'bom_code',
        'bom_name',
        'product_name',
        'uom_name',
        'bom_type_name',
        'is_active',
        'sync_status',
        'detail_snapshot',
        'created_by',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'esb_bom_id' => 'integer',
            'is_active' => 'boolean',
            'last_synced_at' => 'datetime',
            'detail_snapshot' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(RndProject::class, 'rnd_project_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(
            RndProjectProduct::class,
            'rnd_project_product_boms',
            'rnd_project_bom_id',
            'rnd_project_product_id',
        )->withPivot(['usage_type', 'notes'])->withTimestamps();
    }
}
