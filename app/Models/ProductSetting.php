<?php

namespace App\Models;

use Database\Factories\ProductSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProductSetting extends Model
{
    /** @use HasFactory<ProductSettingFactory> */
    use HasFactory;

    protected $fillable = [
        'product_code',
        'expiry_days',
        'barcode_value',
        'qr_svg_path',
        'barcode_svg_path',
    ];

    protected function casts(): array
    {
        return [
            'expiry_days' => 'integer',
        ];
    }

    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class, 'product_setting_locations', 'product_code', 'location_id', 'product_code', 'id')
            ->withTimestamps();
    }

    public function effectiveBarcodeValue(): string
    {
        return $this->barcode_value ?: $this->product_code;
    }
}
