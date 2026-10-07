<?php

namespace App\Console\Commands;

use App\Actions\Rnd\Bom\UpdateEsbBillOfMaterialAction;
use App\Enums\RndBomChangeLogSource;
use App\Enums\RndBomChangeLogStatus;
use App\Services\EsbBillOfMaterialService;
use App\Services\EsbCompanyBomClient;
use App\Services\EsbCoreClient;
use Illuminate\Console\Command;
use OpenSpout\Reader\XLSX\Reader;
use RuntimeException;
use Throwable;

class EsbReplaceBomUnitsCommand extends Command
{
    protected $signature = 'esb:replace-bom-units
        {file : Path to an .xlsx with Product Code, Product Detail ID Lama and Product Detail ID Baru columns}
        {--company=BLSS : ESB Core company code whose BOMs are updated}
        {--sheet=Flag & Nonaktif Unit Lama : Sheet that holds the old/new product detail ids}
        {--execute : Actually update the BOMs (default is a dry run)}
        {--limit=0 : Only update the first N affected BOMs}
        {--report= : Path of the CSV report (default storage/app/esb-replace-bom-units-{timestamp}.csv)}';

    protected $description = 'Point every active BOM (result product and components) from the old product detail ids to the new unit ids, through the BOM update action so each change is checked, logged and verified.';

    public function handle(): int
    {
        $company = mb_strtoupper(trim((string) $this->option('company')));
        $boms = $company === 'BLSS'
            ? app(EsbBillOfMaterialService::class)
            : new EsbBillOfMaterialService(new EsbCompanyBomClient(app(EsbCoreClient::class), $company));
        $action = $company === 'BLSS'
            ? app(UpdateEsbBillOfMaterialAction::class)
            : app(UpdateEsbBillOfMaterialAction::class, ['bomService' => $boms]);
        $execute = (bool) $this->option('execute');
        $map = $this->readMap((string) $this->argument('file'), (string) $this->option('sheet'));

        $reportPath = (string) ($this->option('report') ?: storage_path('app/esb-replace-bom-units-'.now()->format('Ymd-His').'.csv'));
        $report = fopen($reportPath, 'w');
        fputcsv($report, ['bom_id', 'bom_name', 'bom_type', 'status', 'result_replaced', 'components_replaced', 'other_references', 'message'], ',', '"', '');

        $all = $boms->getAllBillOfMaterials();
        $this->info(($execute ? 'EXECUTE' : 'DRY RUN')." {$company}: ".count($map).' unit mappings, '.count($all).' active BOMs');

        $limit = (int) $this->option('limit');
        $changed = 0;
        $tally = [];

        foreach ($all as $summary) {
            if ($limit > 0 && $changed >= $limit) {
                break;
            }

            $bomId = (int) $summary['bomID'];

            try {
                $result = $this->process($boms, $action, $bomId, $map, $execute);
            } catch (Throwable $exception) {
                $result = ['status' => 'error', 'message' => $exception->getMessage()];
            }

            if ($result['status'] === 'unaffected') {
                continue;
            }

            if (in_array($result['status'], ['updated', 'would_update'], true)) {
                $changed++;
            }

            $tally[$result['status']] = ($tally[$result['status']] ?? 0) + 1;
            fputcsv($report, [
                $bomId, $result['name'] ?? ($summary['bomName'] ?? ''), $result['type'] ?? '', $result['status'],
                $result['result'] ?? 0, $result['components'] ?? 0, $result['other'] ?? 0, $result['message'] ?? '',
            ], ',', '"', '');
            $this->line("{$bomId}  {$result['status']}".(($result['message'] ?? '') !== '' ? "  {$result['message']}" : ''));
        }

        fclose($report);

        foreach ($tally as $status => $count) {
            $this->info("{$status}: {$count}");
        }
        $this->info("Report: {$reportPath}");

        return ($tally['error'] ?? 0) > 0 || ($tally['failed'] ?? 0) > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  array<int, int>  $map  old product detail id => new product detail id
     * @return array<string, mixed>
     */
    private function process(EsbBillOfMaterialService $boms, UpdateEsbBillOfMaterialAction $action, int $bomId, array $map, bool $execute): array
    {
        $bom = $boms->getBillOfMaterial($bomId);
        $isMenu = (int) ($bom['bomTypeID'] ?? 0) === 3;

        $resultReplaced = ! $isMenu && isset($map[(int) ($bom['productDetailID'] ?? 0)]);
        $componentIds = array_map(fn (array $item): int => (int) $item['productDetailID'], (array) ($bom['bomDetails'] ?? []));
        $componentsReplaced = count(array_filter($componentIds, fn (int $id): bool => isset($map[$id])));

        $info = ['name' => $bom['bomName'] ?? '', 'type' => $bom['bomTypeName'] ?? '', 'result' => (int) $resultReplaced, 'components' => $componentsReplaced];

        if (! $resultReplaced && $componentsReplaced === 0) {
            return ['status' => 'unaffected'];
        }

        $info['other'] = $this->otherReferences($bom, $map);

        if ($info['other'] > 0) {
            return ['status' => 'other_references'] + $info + ['message' => 'old unit id also appears outside result/component ids (e.g. substitution); decide manually'];
        }

        if (! $execute) {
            return ['status' => 'would_update'] + $info;
        }

        $draft = [
            'productDetailID' => $resultReplaced ? $map[(int) $bom['productDetailID']] : (int) ($bom['productDetailID'] ?? 0),
            'bomDetails' => array_map(function (array $item) use ($map): array {
                $id = (int) $item['productDetailID'];

                return [
                    'ID' => (int) $item['ID'],
                    'productDetailID' => $map[$id] ?? $id,
                    'lastHPP' => (float) ($item['lastHpp'] ?? $item['lastHPP'] ?? 0),
                    'qty' => (float) $item['qty'],
                    'yieldPercent' => (float) ($item['yieldPercent'] ?? 0),
                    'printGroup' => (string) ($item['printGroup'] ?? ''),
                    'tolerancePercent' => (float) ($item['tolerancePercent'] ?? 0),
                    'subtitution' => is_array($item['subtitution'] ?? null) ? $item['subtitution'] : [],
                ];
            }, (array) $bom['bomDetails']),
        ];

        $log = $action->execute($bomId, $draft, $bom['editedDate'] ?? null, 'Migrasi unit Resep ke UOM baru (Resep@…)', RndBomChangeLogSource::BomAdjustment, null);

        return [
            'status' => $log->status === RndBomChangeLogStatus::Success ? 'updated' : 'failed',
            'message' => $log->status === RndBomChangeLogStatus::Success ? '' : $log->status->value.': '.$log->error_message,
        ] + $info;
    }

    /**
     * Count old ids used as substitutes, which this command does not remap.
     *
     * @param  array<string, mixed>  $bom
     * @param  array<int, int>  $map
     */
    private function otherReferences(array $bom, array $map): int
    {
        $count = 0;
        foreach ((array) ($bom['bomDetails'] ?? []) as $item) {
            foreach ((array) ($item['subtitutionManufacturing'] ?? []) as $substitute) {
                if (isset($map[(int) ($substitute['productDetailID'] ?? 0)]) && (int) $substitute['productDetailID'] !== (int) $item['productDetailID']) {
                    $count++;
                }
            }
            foreach ((array) ($item['subtitution'] ?? []) as $substitute) {
                $substituteId = is_array($substitute) ? (int) ($substitute['productDetailID'] ?? 0) : (int) $substitute;
                if (isset($map[$substituteId])) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * @return array<int, int>
     */
    private function readMap(string $file, string $sheetName): array
    {
        if (! is_file($file)) {
            throw new RuntimeException("File tidak ditemukan: {$file}");
        }

        $reader = new Reader;
        $reader->open($file);

        $map = [];
        $indexes = null;
        foreach ($reader->getSheetIterator() as $sheet) {
            if ($sheet->getName() !== $sheetName) {
                continue;
            }

            foreach ($sheet->getRowIterator() as $row) {
                $values = array_map(fn ($value): string => trim((string) $value), $row->toArray());

                if ($indexes === null) {
                    $lowered = array_map('mb_strtolower', $values);
                    $found = array_map(fn (string $header) => array_search($header, $lowered, true), ['product detail id lama', 'product detail id baru']);
                    $indexes = in_array(false, $found, true) ? null : $found;

                    continue;
                }

                [$oldId, $newId] = [(int) ($values[$indexes[0]] ?? 0), (int) ($values[$indexes[1]] ?? 0)];

                if ($oldId > 0 && $newId > 0) {
                    $map[$oldId] = $newId;
                }
            }
            break;
        }
        $reader->close();

        if ($indexes === null) {
            throw new RuntimeException("Kolom Product Detail ID Lama/Baru tidak ditemukan di sheet {$sheetName}.");
        }

        return $map;
    }
}
