<?php

namespace App\Console\Commands;

use App\Services\EsbCoreClient;
use Illuminate\Console\Command;
use OpenSpout\Reader\XLSX\Reader;
use RuntimeException;
use Throwable;

class EsbRenameProductsCommand extends Command
{
    protected $signature = 'esb:rename-products
        {file : Path to an .xlsx with Product Code, Product Name and Proposed Product Name columns}
        {--company=BLSS : ESB Core company code}
        {--sheet=Update Product : Sheet that holds the rename rows}
        {--execute : Actually PUT the changes (default is a dry run)}
        {--limit=0 : Only rename the first N products that qualify}
        {--report= : Path of the CSV report (default storage/app/esb-rename-products-{timestamp}.csv)}';

    protected $description = 'Rename ESB products to the Proposed Product Name in an Excel file, matched by product code and only when the current ESB name equals the sheet\'s Product Name.';

    public function handle(EsbCoreClient $esb): int
    {
        $company = mb_strtoupper(trim((string) $this->option('company')));
        $execute = (bool) $this->option('execute');
        $rows = $this->readRows((string) $this->argument('file'), (string) $this->option('sheet'));

        $reportPath = (string) ($this->option('report') ?: storage_path('app/esb-rename-products-'.now()->format('Ymd-His').'.csv'));
        $report = fopen($reportPath, 'w');
        fputcsv($report, ['product_code', 'product_id', 'status', 'old_name', 'new_name', 'message']);

        $this->info(($execute ? 'EXECUTE' : 'DRY RUN')." {$company}: ".count($rows).' product codes');

        $limit = (int) $this->option('limit');
        $processed = 0;
        $claimedNames = [];
        $tally = [];

        foreach ($rows as $code => [$oldName, $newName]) {
            $code = (string) $code;
            $productId = null;
            $message = '';

            if ($oldName === $newName) {
                $status = 'unchanged';
            } elseif (isset($claimedNames[mb_strtolower($newName)])) {
                $status = 'duplicate_proposed_name';
                $message = 'same new name as '.$claimedNames[mb_strtolower($newName)];
            } elseif ($limit > 0 && $processed >= $limit) {
                continue;
            } else {
                $claimedNames[mb_strtolower($newName)] = $code;

                try {
                    [$status, $productId, $message] = $this->process($esb, $company, $code, $oldName, $newName, $execute);
                } catch (Throwable $exception) {
                    [$status, $productId, $message] = ['error', null, $exception->getMessage()];
                }

                if (in_array($status, ['updated', 'would_update'], true)) {
                    $processed++;
                }
            }

            $tally[$status] = ($tally[$status] ?? 0) + 1;
            fputcsv($report, [$code, $productId, $status, $oldName, $newName, $message]);

            if ($status !== 'unchanged') {
                $this->line("{$code}  {$status}".($message !== '' ? "  {$message}" : ''));
            }
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
    private function process(EsbCoreClient $esb, string $company, string $code, string $oldName, string $newName, bool $execute): array
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

        $currentName = trim((string) $product['productName']);

        if ($currentName === $newName) {
            return ['already_done', $productId, ''];
        }

        if (mb_strtolower($currentName) !== mb_strtolower($oldName)) {
            return ['name_differs', $productId, "ESB name: {$currentName}"];
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
            'productName' => $newName,
            'purchasable' => $product['purchasable'],
            'requestable' => $product['requestable'],
            'saleable' => $product['saleable'],
            'receiptTolerance' => $product['receiptTolerance'],
            'vat' => $product['VAT'],
            'productDetails' => array_map(fn (array $detail): array => [
                'productDetailID' => $detail['productDetailID'],
                'uomID' => $detail['uomID'],
                'qty' => $detail['qty'],
                'basePrice' => $detail['basePrice'],
                'sku' => $detail['SKU'],
                'cubication' => $detail['cubication'],
                'weight' => $detail['weight'],
                'isBase' => $detail['isBase'],
                'isStock' => $detail['isStock'],
                'isPurchase' => $detail['isPurchase'],
                'isTransfer' => $detail['isTransfer'],
                'isSales' => $detail['isSales'],
                'menuID' => $detail['menuID'],
                'flagActive' => $detail['flagActive'],
            ], (array) ($product['productDetails'] ?? [])),
        ];

        if ((int) ($product['bomID'] ?? 0) > 0) {
            $payload['bomID'] = (int) $product['bomID'];
        }

        $esb->successfulResult($esb->request($company, 'put', $path, $payload), 'mengubah produk', $company, $path);

        return ['updated', $productId, ''];
    }

    /**
     * @return array<string, array{0:string, 1:string}> product code => [old name, new name]
     */
    private function readRows(string $file, string $sheetName): array
    {
        if (! is_file($file)) {
            throw new RuntimeException("File tidak ditemukan: {$file}");
        }

        $reader = new Reader;
        $reader->open($file);

        $rows = [];
        $indexes = null;
        foreach ($reader->getSheetIterator() as $sheet) {
            if ($sheet->getName() !== $sheetName) {
                continue;
            }

            foreach ($sheet->getRowIterator() as $row) {
                $values = array_map(fn ($value): string => trim((string) $value), $row->toArray());

                if ($indexes === null) {
                    $lowered = array_map('mb_strtolower', $values);
                    $found = array_map(fn (string $header) => array_search($header, $lowered, true), ['product code', 'product name', 'proposed product name']);

                    if (! in_array(false, $found, true)) {
                        $indexes = $found;
                    }

                    continue;
                }

                [$codeIndex, $oldIndex, $newIndex] = $indexes;
                $code = $values[$codeIndex] ?? '';

                if ($code !== '' && ($values[$newIndex] ?? '') !== '') {
                    $rows[$code] ??= [$values[$oldIndex] ?? '', $values[$newIndex]];
                }
            }
            break;
        }
        $reader->close();

        if ($indexes === null) {
            throw new RuntimeException("Kolom Product Code / Product Name / Proposed Product Name tidak ditemukan di sheet {$sheetName}.");
        }

        return $rows;
    }
}
