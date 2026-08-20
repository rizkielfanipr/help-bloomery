<?php

namespace App\Services;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Facades\Storage;

class QrCodeGenerator
{
    public function generate(string $data, string $path): string
    {
        $qrcode = new QRCode(new QROptions([
            'outputBase64' => false,
        ]));

        $svg = $qrcode->render($data);

        Storage::disk('b2')->put($path, $svg);

        return $path;
    }
}
