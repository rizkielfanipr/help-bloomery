<?php

namespace App\Models;

use Database\Factories\RndInternalMemoMenuCatalogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RndInternalMemoMenuCatalog extends Model
{
    /** @use HasFactory<RndInternalMemoMenuCatalogFactory> */
    use HasFactory;

    protected $fillable = [
        'company_code',
        'branch_code',
        'menu_id',
        'menu_code',
        'menu_name',
        'bom_id',
        'bom_name',
        'category_detail',
        'flag_active',
        'raw_snapshot',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'menu_id' => 'integer',
            'bom_id' => 'integer',
            'flag_active' => 'boolean',
            'raw_snapshot' => 'array',
            'synced_at' => 'datetime',
        ];
    }
}
