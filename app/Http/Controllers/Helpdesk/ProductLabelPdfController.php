<?php

namespace App\Http\Controllers\Helpdesk;

use App\Http\Controllers\Controller;
use App\Models\ProductSetting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class ProductLabelPdfController extends Controller
{
    public function single(string $code): Response
    {
        abort_unless(auth()->user()?->can('view product list'), 403);

        $setting = ProductSetting::query()->firstOrCreate(['product_code' => $code]);

        $pdf = Pdf::loadView('exports.product-label', [
            'labels' => [$this->toLabelData($setting)],
        ])->setPaper('a4', 'portrait');

        return $pdf->download("label-produk-{$setting->product_code}.pdf");
    }

    public function bulk(Request $request): Response
    {
        abort_unless(auth()->user()?->can('view product list'), 403);

        $codes = collect($request->query('codes', []))
            ->map(fn ($code): string => (string) $code)
            ->filter()
            ->values();

        abort_if($codes->isEmpty(), 422);

        $labels = $codes->map(fn (string $code) => $this->toLabelData(
            ProductSetting::query()->firstOrCreate(['product_code' => $code]),
        ))->all();

        $pdf = Pdf::loadView('exports.product-label', ['labels' => $labels])->setPaper('a4', 'portrait');

        return $pdf->download('label-produk.pdf');
    }

    /** @return array{code: string, barcode_value: string, qr: ?string, barcode: ?string} */
    private function toLabelData(ProductSetting $setting): array
    {
        return [
            'code' => $setting->product_code,
            'barcode_value' => $setting->effectiveBarcodeValue(),
            'qr' => $this->dataUri($setting->qr_svg_path),
            'barcode' => $this->dataUri($setting->barcode_svg_path),
        ];
    }

    private function dataUri(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $contents = Storage::disk('b2')->get($path);

        if (! is_string($contents) || $contents === '') {
            return null;
        }

        return 'data:image/svg+xml;base64,'.base64_encode($contents);
    }
}
