<?php

namespace App\Http\Controllers\Helpdesk;

use App\Http\Controllers\Controller;
use App\Models\RndProject;
use App\Services\RndProjectMaterialForecastService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RndProjectMaterialForecastExportController extends Controller
{
    public function __invoke(Request $request, RndProject $project, RndProjectMaterialForecastService $forecastService): BinaryFileResponse
    {
        abort_unless($request->user()?->can('view material forecast'), 403);

        $project->load([
            'products.boms.documentMaterials',
            'products.salesProjections',
            'boms.documentMaterials',
        ]);

        $selectedProductIds = $this->selectedProductIds($request, $project, $forecastService);
        $menuScope = $this->menuScope($project, $selectedProductIds);

        $path = tempnam(sys_get_temp_dir(), 'rnd_material_forecast_').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);

        foreach (['kitchen' => 'Kitchen', 'store' => 'Store'] as $type => $sheetName) {
            if ($type === 'kitchen') {
                $writer->getCurrentSheet()->setName($sheetName);
            } else {
                $writer->addNewSheetAndMakeItCurrent()->setName($sheetName);
            }

            $this->writeSheet($writer, $project, $forecastService->calculate($project, $type, $selectedProductIds), $sheetName, $menuScope);
        }

        $writer->close();

        return response()->download(
            $path,
            'material-forecast-'.Str::slug($project->name).'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        )->deleteFileAfterSend(true);
    }

    /**
     * Every ID must belong to this Project and have a positive Sales Projection, so a tampered or
     * stale link gets no file at all instead of a partial one. The response names no menu or ID.
     *
     * @return list<int>
     */
    private function selectedProductIds(Request $request, RndProject $project, RndProjectMaterialForecastService $forecastService): array
    {
        $eligibleProductIds = collect($forecastService->menuOptions($project))
            ->where('is_eligible', true)
            ->pluck('id')
            ->all();

        $validator = Validator::make($request->only('menu_ids'), [
            'menu_ids' => ['nullable', 'array'],
            'menu_ids.*' => ['required', 'integer', Rule::in($eligibleProductIds)],
        ]);

        abort_if($validator->fails(), 422, 'Pilihan menu tidak valid untuk Project ini. Muat ulang halaman lalu pilih menu kembali.');

        return collect($request->input('menu_ids'))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /** @param list<int> $selectedProductIds */
    private function menuScope(RndProject $project, array $selectedProductIds): string
    {
        if ($selectedProductIds === []) {
            return 'All Menu';
        }

        return $project->products->whereIn('id', $selectedProductIds)->pluck('name')->implode(', ');
    }

    /** @param array<string, mixed> $forecast */
    private function writeSheet(Writer $writer, RndProject $project, array $forecast, string $sheetName, string $menuScope): void
    {
        $titleStyle = (new Style)->setFontBold()->setFontSize(14)->setBackgroundColor('047857')->setFontColor(Color::WHITE);
        $infoStyle = (new Style)->setFontSize(10)->setBackgroundColor('D1FAE5');
        $headerStyle = (new Style)->setFontBold()->setFontSize(10)->setBackgroundColor('059669')->setFontColor(Color::WHITE);
        $warningStyle = (new Style)->setFontSize(10)->setBackgroundColor('FEF3C7');

        $writer->addRow(Row::fromValues(["MATERIAL FORECAST {$sheetName}", '', '', '', ''], $titleStyle));
        $writer->addRow(Row::fromValues(['Project', $this->safeText($project->name)], $infoStyle));
        $writer->addRow(Row::fromValues(['Scope Menu', $this->safeText($menuScope)], $infoStyle));
        $writer->addRow(Row::fromValues(['Total Sales Projection', (float) $forecast['projected_units'], 'unit'], $infoStyle));
        $writer->addRow(Row::fromValues(['Persentase Forecast', (float) $forecast['forecast_percentage'], '%'], $infoStyle));
        $writer->addRow(Row::fromValues(['Forecast Efektif', (float) $forecast['effective_projected_units'], 'unit'], $infoStyle));
        $writer->addRow(Row::fromValues(['Dibuat', now()->format('d/m/Y H:i')], $infoStyle));
        $writer->addRow(Row::fromValues([]));

        $writer->addRow(Row::fromValues(['DETAIL SALES PROJECTION'], $headerStyle));
        $writer->addRow(Row::fromValues(['Menu', 'Sales Projection', 'Forecast Efektif', 'Status Perhitungan'], $headerStyle));
        foreach ($forecast['projection_details'] as $projection) {
            $writer->addRow(Row::fromValues([
                $this->safeText($projection['name']),
                (float) $projection['quantity'],
                (float) $projection['effective_quantity'],
                $projection['is_calculated']
                    ? 'Dihitung dari '.($sheetName === 'Kitchen' ? 'Main Recipe' : 'BOM Menu')
                    : 'Belum memiliki '.($sheetName === 'Kitchen' ? 'Main Recipe' : 'BOM Menu'),
            ]));
        }

        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues(['KEBUTUHAN MATERIAL'], $headerStyle));
        $writer->addRow(Row::fromValues(['Kode', 'Material', 'Unit', 'Kebutuhan', 'Jumlah Menu'], $headerStyle));
        foreach ($forecast['rows'] as $material) {
            $writer->addRow(Row::fromValues([
                $this->safeText($material['code']),
                $this->safeText($material['name']),
                $this->safeText($material['unit']),
                (float) $material['quantity'],
                (int) $material['product_count'],
            ]));
        }

        if ($forecast['warnings'] !== []) {
            $writer->addRow(Row::fromValues([]));
            $writer->addRow(Row::fromValues(['PERINGATAN'], $warningStyle));
            foreach ($forecast['warnings'] as $warning) {
                $writer->addRow(Row::fromValues([$this->safeText($warning)], $warningStyle));
            }
        }
    }

    private function safeText(mixed $value): string
    {
        $text = (string) ($value ?? '');

        return preg_match('/^[=+\-@]/', $text) === 1 ? "'{$text}" : $text;
    }
}
