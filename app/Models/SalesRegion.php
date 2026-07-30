<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesRegion extends Model
{
    protected $fillable = ['name', 'code', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function productPrices(): HasMany
    {
        return $this->hasMany(RndProductRegionalPrice::class);
    }
}
