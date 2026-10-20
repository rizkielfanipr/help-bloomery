<?php

namespace App\Models;

use Database\Factories\RndProjectPremixAssemblyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RndProjectPremixAssembly extends Model
{
    /** @use HasFactory<RndProjectPremixAssemblyFactory> */
    use HasFactory;

    protected $fillable = [
        'rnd_project_id', 'esb_bom_id', 'bom_code', 'bom_name', 'product_name', 'uom_name',
        'bom_type_id', 'bom_type_name', 'detail_snapshot', 'catalog_is_active', 'catalog_synced_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'esb_bom_id' => 'integer', 'bom_type_id' => 'integer', 'detail_snapshot' => 'array',
            'catalog_is_active' => 'boolean', 'catalog_synced_at' => 'datetime',
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
}
