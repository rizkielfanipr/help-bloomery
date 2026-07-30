<?php

namespace App\Http\Controllers\Helpdesk;

use App\Models\RndProductEsbMaterial;
use App\Models\RndProject;
use App\Models\RndProjectProduct;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RndProductEsbMaterialExportController
{
    public function __invoke(Request $request, int $project, int $product): Response|BinaryFileResponse
    {
        $user = $request->user();
        abort_unless($user?->hasRole('SUPERADMIN'), 403);

        $projectRecord = RndProject::query()->findOrFail($project);
        $productRecord = $projectRecord->products()->findOrFail($product);
        $materials = $productRecord->esbMaterials()->with('units')->oldest()->get();
        $format = $request->string('format')->lower()->value();

        if ($format === 'pdf') {
            return Pdf::loadView('exports.rnd-product-esb-materials-pdf', compact(
                'projectRecord',
                'productRecord',
                'materials',
            ))->setPaper('a4', 'landscape')->download($this->filename($productRecord, 'pdf'));
        }

        return $this->excel($projectRecord, $productRecord, $materials);
    }

    private function excel(RndProject $project, RndProjectProduct $product, $materials): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'rnd_esb_material_').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);

        $title = (new Style)->setFontBold()->setFontSize(14)->setBackgroundColor('1D4ED8')->setFontColor(Color::WHITE);
        $info = (new Style)->setFontSize(10)->setBackgroundColor('DBEAFE');
        $header = (new Style)->setFontBold()->setFontSize(10)->setBackgroundColor('2563EB')->setFontColor(Color::WHITE);

        $writer->addRow(Row::fromValues(['DAFTAR BAHAN BARU R&D', '', '', '', '', '', '', '', '', '', '', ''], $title));
        $writer->addRow(Row::fromValues(['Project', $project->name], $info));
        $writer->addRow(Row::fromValues(['Product Release', $product->name], $info));
        $writer->addRow(Row::fromValues(['Dicetak', now()->format('d/m/Y H:i')], $info));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues([
            'No', 'Product Code', 'Product Name', 'Category', 'Sub Category', 'Unit ID',
            'Unit', 'SKU', 'Conversion', 'Base Price', 'Status', 'ESB Product ID', 'Keterangan',
        ], $header));

        foreach ($materials as $index => $material) {
            $writer->addRow(Row::fromValues([
                $index + 1,
                $material->product_code,
                $material->product_name,
                $material->category_name ?: $material->category_id,
                $material->sub_category_name ?: $material->sub_category_id,
                $material->units->pluck('uom_id')->implode(', '),
                $material->units->map(fn ($unit) => $unit->uom_name.($unit->is_base ? ' (Base)' : ''))->implode(', '),
                $material->units->pluck('sku')->implode(', '),
                $material->units->map(fn ($unit) => '1 '.$unit->uom_name.' = '.$unit->conversion_factor.' '.$material->uom_name)->implode('; '),
                (float) $material->base_price,
                RndProductEsbMaterial::STATUSES[$material->status] ?? ucfirst($material->status),
                $material->esb_product_id,
                $material->sync_error ?: $material->notes,
            ]));
        }

        $writer->close();

        return response()->download($path, $this->filename($product, 'xlsx'), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    private function filename(RndProjectProduct $product, string $extension): string
    {
        return 'daftar-bahan-'.Str::slug($product->name).'.'.$extension;
    }
}
