<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentMethodGroupItem extends Model
{
    protected $fillable = [
        'payment_method_group_id',
        'esb_payment_method_id',
        'esb_payment_method_name',
    ];

    /** @return BelongsTo<PaymentMethodGroup, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(PaymentMethodGroup::class, 'payment_method_group_id');
    }
}
