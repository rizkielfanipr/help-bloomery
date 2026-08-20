<?php

namespace App\Observers;

use App\Models\ProductSetting;
use App\Services\BarcodeGenerator;
use App\Services\QrCodeGenerator;

class ProductSettingObserver
{
    public function __construct(
        protected QrCodeGenerator $qrCodeGenerator,
        protected BarcodeGenerator $barcodeGenerator,
    ) {}

    public function saved(ProductSetting $productSetting): void
    {
        $dirty = false;

        if ($productSetting->getOriginal('product_code') !== $productSetting->product_code) {
            $productSetting->qr_svg_path = $this->qrCodeGenerator->generate(
                $productSetting->product_code,
                "products/{$productSetting->product_code}/qr.svg",
            );
            $dirty = true;
        }

        $oldBarcodeValue = $productSetting->getOriginal('barcode_value') ?: $productSetting->getOriginal('product_code');
        $newBarcodeValue = $productSetting->effectiveBarcodeValue();

        if ($oldBarcodeValue !== $newBarcodeValue || $productSetting->barcode_svg_path === null) {
            $productSetting->barcode_svg_path = $this->barcodeGenerator->generate(
                $newBarcodeValue,
                "products/{$productSetting->product_code}/barcode.svg",
            );
            $dirty = true;
        }

        if ($dirty) {
            $productSetting->saveQuietly();
        }
    }
}
