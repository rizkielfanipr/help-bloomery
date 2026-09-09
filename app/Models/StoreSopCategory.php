<?php

namespace App\Models;

use Database\Factories\StoreSopCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StoreSopCategory extends Model
{
    /** @use HasFactory<StoreSopCategoryFactory> */
    use HasFactory;

    protected $fillable = ['name', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'is_active' => 'boolean'];
    }

    public function storeSops(): HasMany
    {
        return $this->hasMany(StoreSop::class);
    }
}
