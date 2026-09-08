<?php

namespace App\Http\Controllers\Helpdesk;

use App\Http\Controllers\Controller;
use App\Models\RndProjectProduct;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ShelfLifeExportController extends Controller
{
    public function __invoke(Request $request): BinaryFileResponse
    {
        abort_unless(auth()->user()?->can('view rnd projects'), 403);

        $query = RndProjectProduct::query()
            ->with('project:id,name')
            ->when(trim($request->string('search')->toString()) !== '', function (Builder $query) use ($request): void {
                $search = '%'.trim($request->string('search')->toString()).'%';
                $query->where(fn (Builder $query) => $query
                    ->where('name', 'like', $search)
                    ->orWhere('product_code', 'like', $search)
                    ->orWhereHas('project', fn (Builder $query) => $query->where('name', 'like', $search)));
            })
            ->when($request->filled('storage_condition'), fn (Builder $query) => $query->where('storage_condition', $request->string('storage_condition')->toString()))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')->toString()))
            ->orderBy('name');

        $tempPath = tempnam(sys_get_temp_dir(), 'shelf_life_export_').'.xlsx';
        $headerStyle = (new Style)
            ->setFontBold()
            ->setFontSize(11)
            ->setBackgroundColor('2563EB')
            ->setFontColor(Color::WHITE);

        $writer = new Writer;
        $writer->openToFile($tempPath);
        $writer->addRow(Row::fromValues([
            'Nama Produk',
            'SKU Produk',
            'Project R&D',
            'Shelf Life',
            'Nilai Shelf Life',
            'Satuan',
            'Kondisi Penyimpanan',
            'Catatan Penyimpanan',
            'Tanggal Rilis',
            'Status',
        ], $headerStyle));

        $query->chunk(500, function ($products) use ($writer): void {
            foreach ($products as $product) {
                $unit = RndProjectProduct::SHELF_LIFE_UNITS[$product->shelf_life_unit] ?? '';
                $writer->addRow(Row::fromValues([
                    $product->name,
                    $product->product_code ?? '',
                    $product->project?->name ?? '',
                    $product->shelf_life_value ? $product->shelf_life_value.' '.$unit : 'Belum diatur',
                    $product->shelf_life_value ?? '',
                    $unit,
                    RndProjectProduct::STORAGE_CONDITIONS[$product->storage_condition] ?? '',
                    $product->storage_notes ?? '',
                    $product->release_date?->format('d/m/Y') ?? '',
                    RndProjectProduct::STATUSES[$product->status] ?? $product->status,
                ]));
            }
        });

        $writer->close();

        return response()->download($tempPath, 'shelf-life-products-'.now()->format('Y-m-d').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}
