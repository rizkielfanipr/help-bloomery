<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Picqer\Barcode\BarcodeGeneratorSVG;

class BarcodeGenerator
{
    public function generate(string $data, string $path): string
    {
        $generator = new BarcodeGeneratorSVG;

        $svg = $generator->getBarcode($data, $generator::TYPE_CODE_128);

        Storage::disk('b2')->put($path, $svg);

        return $path;
    }
}
