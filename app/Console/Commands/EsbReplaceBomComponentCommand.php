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

class EsbReplaceBomComponentCommand extends Command
{
    protected $signature = 'esb:replace-bom-component
        {file : Audit .xlsx with Comcode, BOM ID and a status column per BOM}
        {--sheet=Menu Memakai BBMK061 : Sheet that lists the BOMs}
        {--from=BBMK061 : Product code of the component to replace}
        {--to=BBMK501 : Product code of the replacement component}
        {--only-status=Langsung : Only BOMs whose status column starts with this text}
        {--company= : Only this comcode (default: every comcode in the file)}
        {--execute : Actually update the BOMs (default is a dry run)}
        {--limit=0 : Only update the first N BOMs}
        {--report= : Path of the CSV report (default storage/app/esb-replace-bom-component-{timestamp}.csv)}';

    protected $description = 'Swap one component product for another (same unit, same qty) in the BOMs listed in an audit file, through the BOM update action so each change is checked, logged and verified.';

    public function handle(EsbCoreClient $esb): int
    {
        $from = mb_strtoupper(trim((string) $this->option('from')));
        $to = mb_strtoupper(trim((string) $this->option('to')));
        $execute = (bool) $this->option('execute');
        $targets = $this->readTargets((string) $this->argument('file'), (string) $this->option('sheet'), (string) $this->option('only-status'));

        if ((string) $this->option('company') !== '') {
            $targets = array_intersect_key($targets, [mb_strtoupper((string) $this->option('company')) => true]);
        }

        $reportPath = (string) ($this->option('report') ?: storage_path('app/esb-replace-bom-component-'.now()->format('Ymd-His').'.csv'));
        $report = fopen($reportPath, 'w');
        fputcsv($report, ['company', 'bom_id', 'bom_name', 'status', 'old_detail_id', 'new_detail_id', 'unit', 'qty', 'message'], ',', '"', '');

        $this->info(($execute ? 'EXECUTE' : 'DRY RUN')." {$from} -> {$to}: ".array_sum(array_map('count', $targets)).' BOMs in '.count($targets).' comcodes');

        $limit = (int) $this->option('limit');
        $changed = 0;
        $tally = [];
        $stop = false;

        foreach ($targets as $company => $bomIds) {
            $boms = new EsbBillOfMaterialService(new EsbCompanyBomClient($esb, $company));
            $action = app(UpdateEsbBillOfMaterialAction::class, ['bomService' => $boms]);

            try {
                $replacement = $this->resolveProduct($esb, $company, $to);
            } catch (Throwable $exception) {
                $replacement = null;
                $this->warn("{$company}: {$exception->getMessage()}");
            }

            foreach ($bomIds as $bomId) {
                if ($stop || ($limit > 0 && $changed >= $limit)) {
                    break 2;
                }

                try {
                    $result = $replacement === null
                        ? ['status' => 'target_product_missing', 'message' => "{$to} not found in {$company}"]
                        : $this->process($boms, $action, $bomId, $from, $to, $replacement, $execute);
                } catch (Throwable $exception) {
                    $result = ['status' => 'error', 'message' => $exception->getMessage()];
                }

                if (in_array($result['status'], ['updated', 'would_update'], true)) {
                    $changed++;
                }

                $stop = in_array($result['status'], ['failed', 'error'], true);
                $tally[$result['status']] = ($tally[$result['status']] ?? 0) + 1;
                fputcsv($report, [$company, $bomId, $result['name'] ?? '', $result['status'], $result['old'] ?? '', $result['new'] ?? '', $result['unit'] ?? '', $result['qty'] ?? '', $result['message'] ?? ''], ',', '"', '');
                $this->line("{$company} {$bomId}  {$result['status']}  ".($result['name'] ?? '').(($result['message'] ?? '') !== '' ? "  {$result['message']}" : ''));
            }
        }

        fclose($report);

        foreach ($tally as $status => $count) {
            $this->info("{$status}: {$count}");
        }
        $this->info("Report: {$reportPath}");

        return $stop ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $replacement  active product details of the replacement product
     * @return array<string, mixed>
     */
    private function process(EsbBillOfMaterialService $boms, UpdateEsbBillOfMaterialAction $action, int $bomId, string $from, string $to, array $replacement, bool $execute): array
    {
        $bom = $boms->getBillOfMaterial($bomId);
        $info = ['name' => $bom['bomName'] ?? ''];

        if (mb_strtolower((string) ($bom['bomTypeName'] ?? '')) !== 'menu') {
            return ['status' => 'not_menu_bom'] + $info;
        }

        $components = (array) ($bom['bomDetails'] ?? []);
        $matches = array_values(array_filter($components, fn (array $item): bool => mb_strtoupper(trim((string) ($item['productCode'] ?? ''))) === $from));

        if (count($matches) !== 1) {
            return ['status' => count($matches) === 0 ? 'component_not_found' : 'component_repeated', 'message' => count($matches)." rows with {$from}"] + $info;
        }

        $old = $matches[0];
        $unit = trim((string) ($old['uomName'] ?? ''));
        $detail = collect($replacement['details'])->first(fn (array $d): bool => mb_strtolower($d['unit']) === mb_strtolower($unit));
        $info += ['old' => (int) $old['productDetailID'], 'unit' => $unit, 'qty' => $old['qty']];

        if ($detail === null) {
            return ['status' => 'no_matching_unit', 'message' => "{$to} has no active unit {$unit}"] + $info;
        }

        $info['new'] = $detail['id'];

        if (collect($components)->contains(fn (array $item): bool => (int) $item['productDetailID'] === $detail['id'])) {
            return ['status' => 'target_already_in_bom', 'message' => "BOM already has {$to} {$unit}; merge manually"] + $info;
        }

        if ($this->usedAsSubstitute($components, (int) $old['productDetailID'])) {
            return ['status' => 'used_as_substitute', 'message' => "{$from} is also a substitute in this BOM"] + $info;
        }

        if (! $execute) {
            return ['status' => 'would_update'] + $info;
        }

        $draft = [
            'productDetailID' => (int) ($bom['productDetailID'] ?? 0),
            'bomDetails' => array_map(function (array $item) use ($old, $detail): array {
                $isOld = (int) $item['productDetailID'] === (int) $old['productDetailID'];

                return [
                    'ID' => (int) $item['ID'],
                    'productDetailID' => $isOld ? $detail['id'] : (int) $item['productDetailID'],
                    'lastHPP' => $isOld && $detail['price'] > 0 ? $detail['price'] : (float) ($item['lastHpp'] ?? $item['lastHPP'] ?? 0),
                    'qty' => (float) $item['qty'],
                    'yieldPercent' => (float) ($item['yieldPercent'] ?? 0),
                    'printGroup' => (string) ($item['printGroup'] ?? ''),
                    'tolerancePercent' => (float) ($item['tolerancePercent'] ?? 0),
                    'subtitution' => is_array($item['subtitution'] ?? null) ? $item['subtitution'] : [],
                ];
            }, $components),
        ];

        $log = $action->execute($bomId, $draft, $bom['editedDate'] ?? null, "Ganti komponen {$from} ke {$to}", RndBomChangeLogSource::BomAdjustment, null);

        return [
            'status' => $log->status === RndBomChangeLogStatus::Success ? 'updated' : 'failed',
            'message' => $log->status === RndBomChangeLogStatus::Success ? '' : $log->status->value.': '.$log->error_message,
        ] + $info;
    }

    /**
     * @param  array<int, array<string, mixed>>  $components
     */
    private function usedAsSubstitute(array $components, int $detailId): bool
    {
        foreach ($components as $item) {
            foreach ((array) ($item['subtitution'] ?? []) as $substitute) {
                $id = is_array($substitute) ? (int) ($substitute['productDetailID'] ?? 0) : (int) $substitute;
                if ($id === $detailId) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return array{details: array<int, array{id:int, unit:string, price:float}>}
     */
    private function resolveProduct(EsbCoreClient $esb, string $company, string $code): array
    {
        $result = $esb->successfulResult($esb->request($company, 'get', '/product/list', ['productCode' => $code, 'limit' => 10, 'page' => 1]), 'mengambil produk', $company, '/product/list');
        $matches = array_values(array_filter((array) ($result['data'] ?? []), fn (array $row): bool => strcasecmp(trim((string) ($row['productCode'] ?? '')), $code) === 0));

        if (count($matches) !== 1) {
            throw new RuntimeException("{$code}: ".count($matches).' products matched');
        }

        $path = '/product/'.(int) $matches[0]['productID'];
        $product = $esb->successfulResult($esb->request($company, 'get', $path), 'mengambil detail produk', $company, $path);

        return ['details' => array_values(array_map(fn (array $d): array => [
            'id' => (int) $d['productDetailID'],
            'unit' => trim((string) ($d['uomName'] ?? config('esb.core.uoms.'.($d['uomID'] ?? ''), ''))),
            'price' => (float) ($d['basePrice'] ?? 0),
        ], array_filter((array) ($product['productDetails'] ?? []), fn (array $d): bool => (bool) $d['flagActive'])))];
    }

    /**
     * @return array<string, array<int, int>> company => BOM ids
     */
    private function readTargets(string $file, string $sheetName, string $onlyStatus): array
    {
        if (! is_file($file)) {
            throw new RuntimeException("File tidak ditemukan: {$file}");
        }

        $reader = new Reader;
        $reader->open($file);

        $targets = [];
        $indexes = null;
        foreach ($reader->getSheetIterator() as $sheet) {
            if ($sheet->getName() !== $sheetName) {
                continue;
            }

            foreach ($sheet->getRowIterator() as $row) {
                $values = array_map(fn ($value): string => trim((string) $value), $row->toArray());

                if ($indexes === null) {
                    $lowered = array_map('mb_strtolower', $values);
                    $found = [array_search('comcode', $lowered, true), array_search('bom id', $lowered, true), array_search('memakai bbmk061 (matcha powder)', $lowered, true)];
                    $indexes = in_array(false, $found, true) ? null : $found;

                    continue;
                }

                [$company, $bomId, $status] = [mb_strtoupper($values[$indexes[0]] ?? ''), (int) ($values[$indexes[1]] ?? 0), $values[$indexes[2]] ?? ''];

                if ($company !== '' && $bomId > 0 && str_starts_with($status, $onlyStatus)) {
                    $targets[$company][$bomId] = $bomId;
                }
            }
            break;
        }
        $reader->close();

        if ($indexes === null) {
            throw new RuntimeException("Kolom Comcode / BOM ID / status tidak ditemukan di sheet {$sheetName}.");
        }

        return array_map('array_values', $targets);
    }
}
