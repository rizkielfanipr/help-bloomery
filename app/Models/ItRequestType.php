<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ItRequestType extends Model
{
    protected $fillable = ['name', 'priority', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function requests(): HasMany
    {
        return $this->hasMany(ErpRepairRequest::class, 'request_type_id');
    }
}
