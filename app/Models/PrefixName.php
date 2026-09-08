<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PrefixName extends Model
{
    protected $fillable = ['code', 'label', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function prefixCategories(): BelongsToMany
    {
        return $this->belongsToMany(PrefixCategory::class)->withTimestamps();
    }
}
