<?php

namespace App\Console\Commands;

use App\Services\EsbCoreClient;
use Illuminate\Console\Command;
use OpenSpout\Reader\XLSX\Reader;
use RuntimeException;
use Throwable;

class EsbSetRecipeUnitCommand extends Command
{
    private const RECIPE_UOM_ID = 16;

    protected $signature = 'esb:set-recipe-unit
        {file : Path to an .xlsx whose first column is Product Code (header in row 1)}
        {--company=BLSS : ESB Core company code}
        {--execute : Actually PUT the changes (default is a dry run)}
        {--unit-column= : Header of a column holding the target unit name per product (e.g. "Unit 27 Agustus"); the Product Code column is then found by its header too. Default: Recipe UOM}
        {--limit=0 : Only process the first N product codes}
        {--report= : Path of the CSV report (default storage/app/esb-set-recipe-unit-{timestamp}.csv)}';

    protected $description = 'Make the active Recipe (Resep) UOM the stock, purchase, transfer and sales unit of the products listed in an Excel file.';

    public function handle(EsbCoreClient $esb): int
    {
        $company = mb_strtoupper(trim((string) $this->option('company')));
        $execute = (bool) $this->option('execute');
        $unitColumn = trim((string) $this->option('unit-column'));
        $codes = $unitColumn === ''
            ? array_fill_keys($this->readProductCodes((string) $this->argument('file')), null)
            : $this->readProductUnits((string) $this->argument('file'), $unitColumn);

        if ((int) $this->option('limit') > 0) {
            $codes = array_slice($codes, 0, (int) $this->option('limit'), true);
        }

        $reportPath = (string) ($this->option('report') ?: storage_path('app/esb-set-recipe-unit-'.now()->format('Ymd-His').'.csv'));
        $report = fopen($reportPath, 'w');
        fputcsv($report, ['product_code', 'product_id', 'status', 'message']);

        $this->info(($execute ? 'EXECUTE' : 'DRY RUN')." {$company}: ".count($codes).' product codes');

        $tally = [];
        foreach ($codes as $code => $unit) {
            $code = (string) $code;
            try {
                [$status, $productId, $message] = $this->process($esb, $company, $code, $execute, $unit);
            } catch (Throwable $exception) {
                [$status, $productId, $message] = ['error', null, $exception->getMessage()];
            }

            $tally[$status] = ($tally[$status] ?? 0) + 1;
            fputcsv($report, [$code, $productId, $status, $message]);
            $this->line("{$code}  {$status}".($message !== '' ? "  {$message}" : ''));
        }

        fclose($report);

        foreach ($tally as $status => $count) {
            $this->info("{$status}: {$count}");
        }
        $this->info("Report: {$reportPath}");

        return ($tally['error'] ?? 0) > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array{0:string, 1:?int, 2:string}
     */
    private function process(EsbCoreClient $esb, string $company, string $code, bool $execute, ?string $unit = null): array
    {
        $list = $esb->request($company, 'get', '/product/list', ['productCode' => $code, 'limit' => 10, 'page' => 1]);
        $result = $esb->successfulResult($list, 'mengambil daftar produk', $company, '/product/list');

        $matches = array_values(array_filter(
            (array) ($result['data'] ?? []),
            fn (array $row): bool => mb_strtolower(trim((string) ($row['productCode'] ?? ''))) === mb_strtolower($code),
        ));

        if (count($matches) !== 1) {
            return [count($matches) === 0 ? 'not_found' : 'ambiguous', null, count($matches).' products matched'];
        }

        $productId = (int) $matches[0]['productID'];
        $path = '/product/'.$productId;
        $product = $esb->successfulResult($esb->request($company, 'get', $path), 'mengambil detail produk', $company, $path);

        $details = (array) ($product['productDetails'] ?? []);
        $targetRows = array_filter($details, fn (array $detail): bool => $unit === null
            ? (int) $detail['uomID'] === self::RECIPE_UOM_ID
            : mb_strtolower($this->uomName($detail)) === mb_strtolower($unit));

        if ($targetRows === []) {
            return [$unit === null ? 'no_recipe_unit' : 'no_matching_unit', $productId, $unit === null ? '' : "unit {$unit} not on product"];
        }

        $activeRecipeRows = array_values(array_filter($targetRows, fn (array $detail): bool => (bool) $detail['flagActive']));

        if (count($activeRecipeRows) !== 1) {
            return ['recipe_unit_not_single_active', $productId, count($activeRecipeRows).' active target units'];
        }

        $recipeDetailId = (int) $activeRecipeRows[0]['productDetailID'];

        $alreadyDone = true;
        $payloadDetails = [];
        foreach ($details as $detail) {
            $isRecipe = (int) $detail['productDetailID'] === $recipeDetailId;
            foreach (['isStock', 'isPurchase', 'isTransfer', 'isSales'] as $flag) {
                $alreadyDone = $alreadyDone && (bool) $detail[$flag] === $isRecipe;
            }

            $payloadDetails[] = [
                'productDetailID' => $detail['productDetailID'],
                'uomID' => $detail['uomID'],
                'qty' => $detail['qty'],
                'basePrice' => $detail['basePrice'],
                'sku' => $detail['SKU'],
                'cubication' => $detail['cubication'],
                'weight' => $detail['weight'],
                'isBase' => $detail['isBase'],
                'isStock' => $isRecipe,
                'isPurchase' => $isRecipe,
                'isTransfer' => $isRecipe,
                'isSales' => $isRecipe,
                'menuID' => $detail['menuID'],
                'flagActive' => $detail['flagActive'],
            ];
        }

        if ($alreadyDone) {
            return ['already_done', $productId, ''];
        }

        if (! $execute) {
            return ['would_update', $productId, ''];
        }

        $payload = [
            'categoryID' => $product['categoryID'],
            'subCategoryID' => $product['subCategoryID'],
            'coretaxProductCodeID' => $product['coretaxProductCodeID'],
            'customFields' => $product['customFields'],
            'flagLuxuryItem' => $product['flagLuxuryItem'],
            'notes' => $product['notes'],
            'productCode' => $product['productCode'],
            'productName' => $product['productName'],
            'purchasable' => $product['purchasable'],
            'requestable' => $product['requestable'],
            'saleable' => $product['saleable'],
            'receiptTolerance' => $product['receiptTolerance'],
            'vat' => $product['VAT'],
            'productDetails' => $payloadDetails,
        ];

        if ((int) ($product['bomID'] ?? 0) > 0) {
            $payload['bomID'] = (int) $product['bomID'];
        }

        $esb->successfulResult($esb->request($company, 'put', $path, $payload), 'mengubah produk', $company, $path);

        return ['updated', $productId, ''];
    }

    /**
     * @param  array<string, mixed>  $detail
     */
    private function uomName(array $detail): string
    {
        return trim((string) ($detail['uomName'] ?? config('esb.core.uoms.'.($detail['uomID'] ?? ''), '')));
    }

    /**
     * @return array<string, string> product code => target unit name
     */
    private function readProductUnits(string $file, string $unitColumn): array
    {
        if (! is_file($file)) {
            throw new RuntimeException("File tidak ditemukan: {$file}");
        }

        $reader = new Reader;
        $reader->open($file);

        $units = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            $codeIndex = $unitIndex = null;
            foreach ($sheet->getRowIterator() as $row) {
                $values = array_map(fn ($value): string => trim((string) $value), $row->toArray());

                if ($codeIndex === null || $unitIndex === null) {
                    $lowered = array_map('mb_strtolower', $values);
                    $foundCodeIndex = array_search('product code', $lowered, true);
                    $foundUnitIndex = array_search(mb_strtolower($unitColumn), $lowered, true);
                    $codeIndex = $foundCodeIndex === false ? null : $foundCodeIndex;
                    $unitIndex = $foundUnitIndex === false ? null : $foundUnitIndex;

                    continue;
                }

                if (($values[$codeIndex] ?? '') !== '' && ($values[$unitIndex] ?? '') !== '') {
                    $units[$values[$codeIndex]] = $values[$unitIndex];
                }
            }
            break;
        }
        $reader->close();

        if ($codeIndex === null || $unitIndex === null) {
            throw new RuntimeException("Kolom Product Code / {$unitColumn} tidak ditemukan.");
        }

        return $units;
    }

    /**
     * @return array<int, string>
     */
    private function readProductCodes(string $file): array
    {
        if (! is_file($file)) {
            throw new RuntimeException("File tidak ditemukan: {$file}");
        }

        $reader = new Reader;
        $reader->open($file);

        $codes = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $index => $row) {
                $code = trim((string) ($row->getCells()[0]?->getValue() ?? ''));
                if ($index > 1 && $code !== '') {
                    $codes[] = $code;
                }
            }
            break;
        }
        $reader->close();

        return array_values(array_unique($codes));
    }
}
