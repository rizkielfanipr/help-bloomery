<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentMethodGroup extends Model
{
    protected $fillable = ['name', 'sort_order'];

    /** @return HasMany<PaymentMethodGroupItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PaymentMethodGroupItem::class);
    }
}
