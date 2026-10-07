<?php

namespace App\Console\Commands;

use App\Services\EsbCoreClient;
use Illuminate\Console\Command;
use OpenSpout\Reader\XLSX\Reader;
use RuntimeException;
use Throwable;

class EsbReplaceProductUnitsCommand extends Command
{
    protected $signature = 'esb:replace-product-units
        {file : Path to an .xlsx with Product Code, Unit Asal, Conversion Factor, Unit UOM and SKU New columns (first sheet)}
        {--company=BLSS : ESB Core company code}
        {--sheet= : Sheet name to read (default: the first sheet)}
        {--deactivate-old : Also set the old unit inactive (default keeps it active, only its flags move)}
        {--finalize : For products that already have the new unit: put the stock/purchase/transfer/sales flags on the new unit only and deactivate the old unit}
        {--execute : Actually PUT the changes (default is a dry run)}
        {--limit=0 : Only change the first N qualifying products}
        {--report= : Path of the CSV report (default storage/app/esb-replace-product-units-{timestamp}.csv)}';

    protected $description = 'Add the new unit (e.g. Resep@100GR) to each product, move the stock/purchase/transfer/sales flags from the old unit to it, and optionally deactivate the old unit.';

    public function handle(EsbCoreClient $esb): int
    {
        $company = mb_strtoupper(trim((string) $this->option('company')));
        $execute = (bool) $this->option('execute');
        $rows = $this->readRows((string) $this->argument('file'));

        $units = $esb->successfulResult(
            $esb->request($company, 'get', '/units', ['limit' => 1000, 'page' => 1]),
            'mengambil daftar unit', $company, '/units',
        );
        $unitIds = [];
        foreach ((array) ($units['data'] ?? []) as $unit) {
            $unitIds[mb_strtolower(trim((string) $unit['uomName']))] = (int) $unit['uomID'];
        }

        $reportPath = (string) ($this->option('report') ?: storage_path('app/esb-replace-product-units-'.now()->format('Ymd-His').'.csv'));
        $report = fopen($reportPath, 'w');
        fputcsv($report, ['product_code', 'product_id', 'status', 'old_unit', 'old_product_detail_id', 'new_unit', 'new_product_detail_id', 'message'], ',', '"', '');

        $this->info(($execute ? 'EXECUTE' : 'DRY RUN')." {$company}: ".count($rows).' products');

        $limit = (int) $this->option('limit');
        $changed = 0;
        $tally = [];

        foreach ($rows as $row) {
            if ($limit > 0 && $changed >= $limit) {
                break;
            }

            try {
                $result = $this->process($esb, $company, $row, $unitIds, $execute);
            } catch (Throwable $exception) {
                $result = ['status' => 'error', 'message' => $exception->getMessage()];
            }

            if (in_array($result['status'], ['replaced', 'would_replace', 'finalized', 'would_finalize'], true)) {
                $changed++;
            }

            $tally[$result['status']] = ($tally[$result['status']] ?? 0) + 1;
            fputcsv($report, [
                $row['code'], $result['productId'] ?? null, $result['status'], $row['oldUnit'],
                $result['oldDetailId'] ?? null, $row['newUnit'], $result['newDetailId'] ?? null, $result['message'] ?? '',
            ], ',', '"', '');
            $this->line("{$row['code']}  {$result['status']}".(($result['message'] ?? '') !== '' ? "  {$result['message']}" : ''));
        }

        fclose($report);

        foreach ($tally as $status => $count) {
            $this->info("{$status}: {$count}");
        }
        $this->info("Report: {$reportPath}");

        return ($tally['error'] ?? 0) > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  array{code:string, oldUnit:string, factor:float, newUnit:string, sku:string}  $row
     * @param  array<string, int>  $unitIds
     * @return array{status:string, productId?:int, oldDetailId?:int, newDetailId?:int, message?:string}
     */
    private function process(EsbCoreClient $esb, string $company, array $row, array $unitIds, bool $execute): array
    {
        $list = $esb->request($company, 'get', '/product/list', ['productCode' => $row['code'], 'limit' => 10, 'page' => 1]);
        $result = $esb->successfulResult($list, 'mengambil daftar produk', $company, '/product/list');

        $matches = array_values(array_filter(
            (array) ($result['data'] ?? []),
            fn (array $item): bool => mb_strtolower(trim((string) ($item['productCode'] ?? ''))) === mb_strtolower($row['code']),
        ));

        if (count($matches) !== 1) {
            return ['status' => count($matches) === 0 ? 'not_found' : 'ambiguous', 'message' => count($matches).' products matched'];
        }

        $productId = (int) $matches[0]['productID'];
        $path = '/product/'.$productId;
        $product = $esb->successfulResult($esb->request($company, 'get', $path), 'mengambil detail produk', $company, $path);
        $details = (array) ($product['productDetails'] ?? []);

        $named = fn (array $detail): string => mb_strtolower($this->unitName($detail));

        $existingNew = array_values(array_filter($details, fn (array $detail): bool => $named($detail) === mb_strtolower($row['newUnit'])));

        if ((bool) $this->option('finalize')) {
            return $this->finalize($esb, $company, $productId, $path, $product, $row, $existingNew, $execute);
        }

        if ($existingNew !== []) {
            return ['status' => 'already_done', 'productId' => $productId];
        }

        $sources = array_values(array_filter(
            $details,
            fn (array $detail): bool => $named($detail) === mb_strtolower($row['oldUnit']) && (bool) $detail['flagActive'],
        ));

        if (count($sources) !== 1) {
            return ['status' => 'no_single_active_source_unit', 'productId' => $productId, 'message' => count($sources).' active '.$row['oldUnit'].' units'];
        }

        $source = $sources[0];
        $oldDetailId = (int) $source['productDetailID'];

        if (abs((float) $source['qty'] - $row['factor']) > 0.000001) {
            return ['status' => 'factor_mismatch', 'productId' => $productId, 'oldDetailId' => $oldDetailId, 'message' => "ESB qty {$source['qty']} vs report {$row['factor']}"];
        }

        if (! isset($unitIds[mb_strtolower($row['newUnit'])])) {
            return ['status' => 'uom_missing', 'productId' => $productId, 'oldDetailId' => $oldDetailId, 'message' => "unit {$row['newUnit']} does not exist"];
        }

        if (($source['menuID'] ?? null) !== null) {
            return ['status' => 'has_menu_mapping', 'productId' => $productId, 'oldDetailId' => $oldDetailId, 'message' => 'old unit is mapped to a menu; decide manually'];
        }

        if (! $execute) {
            return ['status' => 'would_replace', 'productId' => $productId, 'oldDetailId' => $oldDetailId];
        }

        $deactivate = (bool) $this->option('deactivate-old');
        $payloadDetails = [];
        foreach ($details as $detail) {
            $isSource = (int) $detail['productDetailID'] === $oldDetailId;
            $payloadDetails[] = $this->detailPayload($detail, [
                'isStock' => $isSource ? false : $detail['isStock'],
                'isPurchase' => $isSource ? false : $detail['isPurchase'],
                'isTransfer' => $isSource ? false : $detail['isTransfer'],
                'isSales' => $isSource ? false : $detail['isSales'],
                'flagActive' => $isSource && $deactivate ? false : $detail['flagActive'],
            ]);
        }

        $payloadDetails[] = [
            'uomID' => $unitIds[mb_strtolower($row['newUnit'])],
            'qty' => $source['qty'],
            'basePrice' => $source['basePrice'],
            'sku' => $row['sku'],
            'cubication' => $source['cubication'],
            'weight' => $source['weight'],
            'isBase' => false,
            'isStock' => $source['isStock'],
            'isPurchase' => $source['isPurchase'],
            'isTransfer' => $source['isTransfer'],
            'isSales' => $source['isSales'],
            'menuID' => null,
            'flagActive' => true,
        ];

        $payload = $this->productPayload($product, $payloadDetails);

        $esb->successfulResult($esb->request($company, 'put', $path, $payload), 'mengubah produk', $company, $path);

        $after = $esb->successfulResult($esb->request($company, 'get', $path), 'memverifikasi produk', $company, $path);
        $newDetail = collect((array) ($after['productDetails'] ?? []))
            ->first(fn (array $detail): bool => $named($detail) === mb_strtolower($row['newUnit']));

        if ($newDetail === null) {
            return ['status' => 'unverified', 'productId' => $productId, 'oldDetailId' => $oldDetailId, 'message' => 'PUT accepted but the new unit is not on the product'];
        }

        return ['status' => 'replaced', 'productId' => $productId, 'oldDetailId' => $oldDetailId, 'newDetailId' => (int) $newDetail['productDetailID']];
    }

    /**
     * @param  array<string, mixed>  $product
     * @param  array{code:string, oldUnit:string, factor:float, newUnit:string, sku:string}  $row
     * @param  array<int, array<string, mixed>>  $newDetails
     * @return array{status:string, productId?:int, oldDetailId?:int, newDetailId?:int, message?:string}
     */
    private function finalize(EsbCoreClient $esb, string $company, int $productId, string $path, array $product, array $row, array $newDetails, bool $execute): array
    {
        $flags = ['isStock' => 'stock', 'isPurchase' => 'purchase', 'isTransfer' => 'transfer', 'isSales' => 'sales'];

        if (count($newDetails) !== 1) {
            return ['status' => 'new_unit_missing', 'productId' => $productId, 'message' => count($newDetails).' units named '.$row['newUnit']];
        }

        $newId = (int) $newDetails[0]['productDetailID'];
        $details = (array) ($product['productDetails'] ?? []);
        $oldDetails = array_values(array_filter($details, fn (array $detail): bool => mb_strtolower($this->unitName($detail)) === mb_strtolower($row['oldUnit'])
            && abs((float) $detail['qty'] - $row['factor']) <= 0.000001
            && (bool) $detail['flagActive']));
        $oldDetailId = $oldDetails === [] ? null : (int) $oldDetails[0]['productDetailID'];

        $before = [];
        foreach ($flags as $key => $label) {
            $holders = array_map(fn (array $detail): string => $this->unitName($detail), array_filter($details, fn (array $detail): bool => (bool) $detail[$key]));
            $before[] = $label.'='.implode('+', $holders);
        }

        $alreadyFinal = $oldDetails === [];
        foreach ($details as $detail) {
            foreach (array_keys($flags) as $key) {
                $alreadyFinal = $alreadyFinal && (bool) $detail[$key] === ((int) $detail['productDetailID'] === $newId);
            }
        }

        $context = ['productId' => $productId, 'oldDetailId' => $oldDetailId, 'newDetailId' => $newId, 'message' => 'before: '.implode(' ', $before)];

        if ($alreadyFinal) {
            return ['status' => 'already_final'] + $context;
        }

        if (! $execute) {
            return ['status' => 'would_finalize'] + $context;
        }

        $payloadDetails = [];
        foreach ($details as $detail) {
            $isNew = (int) $detail['productDetailID'] === $newId;
            $isOld = $oldDetailId !== null && (int) $detail['productDetailID'] === $oldDetailId;
            $payloadDetails[] = $this->detailPayload($detail, [
                'isStock' => $isNew,
                'isPurchase' => $isNew,
                'isTransfer' => $isNew,
                'isSales' => $isNew,
                'flagActive' => $isOld ? false : $detail['flagActive'],
            ]);
        }

        $esb->successfulResult($esb->request($company, 'put', $path, $this->productPayload($product, $payloadDetails)), 'mengubah produk', $company, $path);

        $after = $esb->successfulResult($esb->request($company, 'get', $path), 'memverifikasi produk', $company, $path);
        $afterNew = collect((array) ($after['productDetails'] ?? []))->first(fn (array $detail): bool => (int) $detail['productDetailID'] === $newId);
        $afterOld = collect((array) ($after['productDetails'] ?? []))->first(fn (array $detail): bool => $oldDetailId !== null && (int) $detail['productDetailID'] === $oldDetailId);

        $verified = $afterNew !== null && $afterNew['isStock'] && $afterNew['isPurchase'] && $afterNew['isTransfer'] && $afterNew['isSales']
            && ($afterOld === null || ! $afterOld['flagActive']);

        return ['status' => $verified ? 'finalized' : 'unverified'] + $context;
    }

    /**
     * @param  array<string, mixed>  $product
     * @param  array<int, array<string, mixed>>  $details
     * @return array<string, mixed>
     */
    private function productPayload(array $product, array $details): array
    {
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
            'productDetails' => $details,
        ];

        if ((int) ($product['bomID'] ?? 0) > 0) {
            $payload['bomID'] = (int) $product['bomID'];
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $detail
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function detailPayload(array $detail, array $overrides): array
    {
        return array_merge([
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
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $detail
     */
    private function unitName(array $detail): string
    {
        return trim((string) ($detail['uomName'] ?? config('esb.core.uoms.'.($detail['uomID'] ?? ''), '')));
    }

    /**
     * @return array<int, array{code:string, oldUnit:string, factor:float, newUnit:string, sku:string}>
     */
    private function readRows(string $file): array
    {
        if (! is_file($file)) {
            throw new RuntimeException("File tidak ditemukan: {$file}");
        }

        $reader = new Reader;
        $reader->open($file);

        $headers = ['product code', 'unit asal', 'conversion factor', 'unit uom', 'sku new'];
        $rows = [];
        $indexes = null;
        foreach ($reader->getSheetIterator() as $sheet) {
            if ((string) $this->option('sheet') !== '' && $sheet->getName() !== (string) $this->option('sheet')) {
                continue;
            }

            foreach ($sheet->getRowIterator() as $row) {
                $values = array_map(fn ($value): string => trim((string) $value), $row->toArray());

                if ($indexes === null) {
                    $lowered = array_map('mb_strtolower', $values);
                    $found = array_map(fn (string $header) => array_search($header, $lowered, true), $headers);
                    $indexes = in_array(false, $found, true) ? null : $found;

                    continue;
                }

                [$code, $oldUnit, $factor, $newUnit, $sku] = array_map(fn (int $index): string => $values[$index] ?? '', $indexes);

                if ($code !== '' && $newUnit !== '') {
                    $rows[] = ['code' => $code, 'oldUnit' => $oldUnit, 'factor' => (float) $factor, 'newUnit' => $newUnit, 'sku' => $sku];
                }
            }
            break;
        }
        $reader->close();

        if ($indexes === null) {
            throw new RuntimeException('Kolom '.implode(', ', $headers).' tidak ditemukan.');
        }

        return $rows;
    }
}
