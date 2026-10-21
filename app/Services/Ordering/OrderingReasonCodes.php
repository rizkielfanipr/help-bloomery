<?php

namespace App\Services\Ordering;

/**
 * Reason codes stored on every forecast item, with their operational labels. Blocking reasons
 * stop the recommendation quantity; the others lower confidence
 * (docs/ordering-recommendation-prd.md §16).
 */
class OrderingReasonCodes
{
    /** @var list<string> */
    public const BLOCKING = ['demand_not_available', 'stock_not_available', 'stock_location_unconfirmed', 'stock_stale', 'uom_conflict', 'identity_invalid', 'branch_mismatch'];

    /** @var array<string, string> */
    private const LABELS = [
        'demand_not_available' => 'Data Daily Usage tidak tersedia',
        'stock_not_available' => 'Stok tidak tersedia',
        'stock_location_unconfirmed' => 'Lokasi stok Store belum dikonfirmasi',
        'stock_stale' => 'Stok stale',
        'uom_conflict' => 'UOM berbeda antar sumber; tidak dapat dinormalisasi',
        'identity_invalid' => 'Identitas produk tidak valid',
        'branch_mismatch' => 'Data berasal dari branch lain',
        'inbound_not_available' => 'Inbound belum diperhitungkan',
        'waste_not_available' => 'Waste belum dapat dihitung',
        'sparse_weekday_history' => 'Histori per hari masih sedikit',
        'negative_stock' => 'Stok negatif (anomali)',
        'category_unconfirmed' => 'Kategori orderable belum dikonfirmasi',
        'usage_partial' => 'Sebagian hari Daily Usage tidak lengkap',
        'stock_partial' => 'Sebagian data Stock Movement tidak lengkap',
        'no_demand_in_period' => 'Tidak ada demand pada periode',
    ];

    public static function label(string $code): string
    {
        return self::LABELS[$code] ?? $code;
    }

    public static function isBlocking(string $code): bool
    {
        return in_array($code, self::BLOCKING, true);
    }
}
