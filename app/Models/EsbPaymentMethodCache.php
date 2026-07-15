<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EsbPaymentMethodCache extends Model
{
    protected $table = 'esb_payment_method_cache';

    protected $fillable = [
        'esb_payment_method_id',
        'esb_payment_method_name',
        'esb_payment_method_code',
        'esb_payment_method_type_id',
        'esb_payment_method_type_name',
        'branch_code',
        'branch_name',
        'last_seen_at',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
    ];

    /**
     * Upsert a payment method entry from ESB data.
     *
     * @param  array<string, mixed>  $payment
     */
    public static function upsertFromEsb(array $payment, string $branchCode, string $branchName): void
    {
        $id = (int) ($payment['paymentMethodID'] ?? 0);
        if (! $id) {
            return;
        }

        static::upsert([
            [
                'esb_payment_method_id' => $id,
                'esb_payment_method_name' => $payment['paymentMethodName'] ?? '',
                'esb_payment_method_code' => $payment['paymentMethodCode'] ?? null,
                'esb_payment_method_type_id' => (int) ($payment['paymentMethodTypeID'] ?? 0) ?: null,
                'esb_payment_method_type_name' => $payment['paymentMethodTypeName'] ?? null,
                'branch_code' => $branchCode,
                'branch_name' => $branchName,
                'last_seen_at' => now(),
            ],
        ], uniqueBy: ['esb_payment_method_id', 'branch_code'],
            update: ['esb_payment_method_name', 'esb_payment_method_code', 'esb_payment_method_type_id', 'esb_payment_method_type_name', 'branch_name', 'last_seen_at']);
    }
}
