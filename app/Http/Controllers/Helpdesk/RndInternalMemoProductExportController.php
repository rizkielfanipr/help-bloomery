<?php

namespace App\Http\Controllers\Helpdesk;

use App\Http\Controllers\Controller;
use App\Models\RndInternalMemo;
use App\Models\RndProductEsbShelfLife;
use App\Services\Rnd\InternalMemo\InternalMemoConsolidationService;
use App\Services\Rnd\InternalMemo\InternalMemoItemIdentity;
use App\Services\Rnd\InternalMemo\InternalMemoShelfLifeLookup;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Exports one "Product Active" section of a Memo (Store or Kitchen) as .xlsx: one sheet for its
 * WIP and one for its RAW items, with the same columns as the Memo page. Local data only.
 */
class RndInternalMemoProductExportController extends Controller
{
    public function __invoke(int $memo, string $scope, InternalMemoConsolidationService $consolidation, InternalMemoShelfLifeLookup $shelfLifeLookup): BinaryFileResponse
    {
        abort_unless(in_array($scope, [InternalMemoItemIdentity::SCOPE_STORE, InternalMemoItemIdentity::SCOPE_KITCHEN], true), 404);

        $memoRecord = RndInternalMemo::query()->findOrFail($memo);
        abort_unless(auth()->user()?->can('view', $memoRecord), 403);

        $summary = $consolidation->consolidateForSummary($memoRecord)[$scope];
        $shelfLives = $shelfLifeLookup->masters($memoRecord);
        $label = $scope === InternalMemoItemIdentity::SCOPE_KITCHEN ? 'Kitchen' : 'Store';

        $path = tempnam(sys_get_temp_dir(), 'rnd_memo_products_').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);

        $this->writeSheet($writer, $memoRecord, "WIP {$label}", $summary['wip'], $shelfLives, true);
        $writer->addNewSheetAndMakeItCurrent();
        $this->writeSheet($writer, $memoRecord, "RAW {$label}", $summary['bahan'], $shelfLives, false);
        $writer->close();

        $number = Str::slug(Str::of($memoRecord->memo_number)->replace(['/', '\\'], ' '));

        return response()->download($path, 'product-active-'.Str::lower($label).'-'.($number !== '' ? $number : $memoRecord->id).'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  Collection<int, RndProductEsbShelfLife>  $shelfLives
     */
    private function writeSheet(Writer $writer, RndInternalMemo $memo, string $title, array $rows, Collection $shelfLives, bool $withShelfLife): void
    {
        $writer->getCurrentSheet()->setName($title);

        $titleStyle = (new Style)->setFontBold()->setFontSize(14)->setBackgroundColor('1D4ED8')->setFontColor(Color::WHITE);
        $info = (new Style)->setFontSize(10)->setBackgroundColor('DBEAFE');
        $header = (new Style)->setFontBold()->setFontSize(10)->setBackgroundColor('2563EB')->setFontColor(Color::WHITE);

        $writer->addRow(Row::fromValues(['PRODUCT ACTIVE · '.mb_strtoupper($title)], $titleStyle));
        $writer->addRow(Row::fromValues(['Memo', $memo->title], $info));
        $writer->addRow(Row::fromValues(['Nomor Memo', $memo->memo_number], $info));
        $writer->addRow(Row::fromValues(['Bulan Memo', $memo->period_month->translatedFormat('F Y')], $info));
        $writer->addRow(Row::fromValues(['Brand', $memo->brandLabel() ?? 'Belum ditentukan'], $info));
        $writer->addRow(Row::fromValues(['Dicetak', now()->format('d/m/Y H:i')], $info));
        $writer->addRow(Row::fromValues([]));

        $writer->addRow(Row::fromValues([
            'No', 'Product Code', 'Product Name', 'UOM BOM', 'Purchase UOM',
            ...($withShelfLife ? ['Shelf Life', 'Storage'] : []),
            'Minimum Order', 'UOM Minimum Order', 'Sumber',
        ], $header));

        foreach ($rows as $index => $row) {
            $shelfLife = $withShelfLife && $row['product_detail_id'] ? $shelfLives->get($row['product_detail_id']) : null;

            $writer->addRow(Row::fromValues([
                $index + 1,
                $row['product_code'],
                $row['product_name'],
                $row['uom_name'],
                $row['has_purchase_uom'] ? $row['purchase_uom_name'] : '',
                ...($withShelfLife ? [
                    match (true) {
                        $shelfLife === null => 'Belum diisi',
                        ! $shelfLife->is_active => 'Tidak Aktif',
                        default => $shelfLife->shelfLifeLabel(),
                    },
                    $shelfLife?->is_active ? $shelfLife->storageConditionLabel() : '',
                ] : []),
                $row['minimum_order'],
                $row['minimum_order'] !== null ? $row['uom_name'] : '',
                ($row['source'] ?? 'bom') === 'manual' ? 'Manual' : 'BOM Menu',
            ]));
        }
    }
}
