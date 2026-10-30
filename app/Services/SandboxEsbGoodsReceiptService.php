<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Local-only stand-in for the ESB Receiving endpoints (config esb.receiving_sandbox). It serves
 * dummy BLSS Purchase Orders and never sends a request to ESB. Scenario POs:
 * PO-DUMMY-FAIL (ESB rejects → failed), PO-DUMMY-TIMEOUT (connection lost → unknown),
 * PO-DUMMY-MOVED (ESB branch changes before submit), PO-DUMMY-CLOSED (status changes before submit).
 */
class SandboxEsbGoodsReceiptService extends EsbGoodsReceiptService
{
    public const COMPANY_CODE = 'BLSS';

    /** ESB branch that ReceivingSandboxSeeder maps to a local branch. */
    public const MAPPED_ESB_BRANCH_ID = 9103;

    private const PURCHASE_ORDER_STATUS_CLOSED = 5;

    public function __construct() {}

    /** @return array<int, array<string, mixed>> */
    public function purchaseOrders(string $companyCode, array $filters = []): array
    {
        if ($companyCode !== self::COMPANY_CODE) {
            return [];
        }

        // Reloading the list restarts every scenario from the "PO just opened" state.
        $orders = collect(self::purchaseOrderDefinitions());
        foreach ($orders as $order) {
            Cache::forget($this->fetchCountKey($order['purchaseNum']));
        }
        $statusId = $filters['statusID'] ?? null;

        return $orders
            ->filter(fn (array $order): bool => $statusId === null || (int) $order['statusID'] === (int) $statusId)
            ->map(fn (array $order): array => collect($order)->except('purchaseDetails')->all())
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    public function purchaseOrder(string $companyCode, string $purchaseNumber): array
    {
        $order = collect(self::purchaseOrderDefinitions())->firstWhere('purchaseNum', $purchaseNumber);
        if ($companyCode !== self::COMPANY_CODE || ! $order) {
            throw new RuntimeException("Sandbox: Purchase Order {$purchaseNumber} tidak ditemukan.");
        }

        // The first fetch opens the PO; later fetches are the re-verification right before submit.
        Cache::add($this->fetchCountKey($purchaseNumber), 0, now()->addDay());
        $isReverification = Cache::increment($this->fetchCountKey($purchaseNumber)) > 1;
        if ($isReverification && $purchaseNumber === 'PO-DUMMY-MOVED') {
            $order = array_replace($order, ['branchID' => 9102, 'branchCode' => 'DMY-SNP', 'branchName' => 'Dummy Senopati']);
        }
        if ($isReverification && $purchaseNumber === 'PO-DUMMY-CLOSED') {
            $order = array_replace($order, ['statusID' => self::PURCHASE_ORDER_STATUS_CLOSED, 'statusName' => 'Closed']);
        }

        return $order;
    }

    /** @return array<int, array<string, mixed>> */
    public function locations(string $companyCode, int $branchId): array
    {
        return match ($branchId) {
            9101 => [['locationID' => 910101, 'locationName' => 'Kemang · Gudang Kering'], ['locationID' => 910102, 'locationName' => 'Kemang · Chiller']],
            9102 => [['locationID' => 910201, 'locationName' => 'Senopati · Gudang Kering'], ['locationID' => 910202, 'locationName' => 'Senopati · Freezer']],
            self::MAPPED_ESB_BRANCH_ID => [['locationID' => 910301, 'locationName' => 'Termapping · Gudang Utama']],
            default => [],
        };
    }

    /** @return array<string, mixed> */
    public function create(string $companyCode, string $referenceNumber, array $payload): array
    {
        return match ($referenceNumber) {
            'PO-DUMMY-FAIL' => throw new RuntimeException('Sandbox: ESB menolak Goods Receipt (simulasi).'),
            'PO-DUMMY-TIMEOUT' => throw new ConnectionException('Sandbox: koneksi terputus setelah request dikirim (simulasi).'),
            default => [
                'result' => ['goodsReceiptNum' => 'GR-DUMMY-'.Str::upper(Str::random(8))],
                'response' => ['code' => 'SANDBOX', 'message' => 'Sandbox lokal: Goods Receipt tidak dikirim ke ESB.'],
            ],
        };
    }

    /** @return list<array<string, mixed>> */
    public static function purchaseOrderDefinitions(): array
    {
        $authorized = ['statusID' => self::PURCHASE_ORDER_STATUS_AUTHORIZED, 'statusName' => 'Authorized'];
        $receiving = ['statusID' => self::PURCHASE_ORDER_STATUS_RECEIVING, 'statusName' => 'Receiving'];
        $kemang = ['branchID' => 9101, 'branchCode' => 'DMY-KMG', 'branchName' => 'Dummy Kemang'];
        $senopati = ['branchID' => 9102, 'branchCode' => 'DMY-SNP', 'branchName' => 'Dummy Senopati'];
        $mapped = ['branchID' => self::MAPPED_ESB_BRANCH_ID, 'branchCode' => 'DMY-MAP', 'branchName' => 'Dummy Cabang Termapping'];
        $dairy = ['supplierID' => 98001, 'supplierName' => 'PT Dummy Susu Segar'];
        $dry = ['supplierID' => 98002, 'supplierName' => 'CV Dummy Bahan Kering'];

        return [
            self::order('PO-DUMMY-001', $authorized + $kemang + $dairy, 1, 2, [
                ['BTR-001', 'Butter Unsalted', 'KG', 10],
                ['MLK-001', 'Fresh Milk', 'L', 24],
                ['SGR-001', 'Gula Pasir', 'KG', 20],
            ]),
            self::order('PO-DUMMY-002', $receiving + $senopati + $dairy, 2, 4, [
                ['CRC-001', 'Cream Cheese', 'PCS', 12],
                ['WPC-001', 'Whipping Cream', 'L', 6],
            ]),
            self::order('PO-DUMMY-003', $authorized + $mapped + $dry, 3, 3, [
                ['FLR-001', 'Tepung Terigu Protein Tinggi', 'KG', 25],
                ['CCO-001', 'Cocoa Powder', 'KG', 5],
            ]),
            self::order('PO-DUMMY-FAIL', $authorized + $kemang + $dry, 4, 5, [['EGG-001', 'Telur Ayam', 'BUTIR', 60]]),
            self::order('PO-DUMMY-TIMEOUT', $authorized + $senopati + $dry, 4, 6, [['SLT-001', 'Garam Halus', 'KG', 4]]),
            self::order('PO-DUMMY-MOVED', $authorized + $kemang + $dry, 5, 7, [['YST-001', 'Ragi Instan', 'PCS', 10]]),
            self::order('PO-DUMMY-CLOSED', $receiving + $senopati + $dairy, 6, 8, [['YGT-001', 'Yogurt Plain', 'L', 8]]),
        ];
    }

    /**
     * @param  array<string, mixed>  $header
     * @param  list<array{0: string, 1: string, 2: string, 3: int|float}>  $lines  [productCode, productName, uomName, qty]
     * @return array<string, mixed>
     */
    private static function order(string $purchaseNumber, array $header, int $purchasedDaysAgo, int $requiredInDays, array $lines): array
    {
        $poIndex = crc32($purchaseNumber) % 1000;

        return ['purchaseNum' => $purchaseNumber] + $header + [
            'purchaseDate' => now()->subDays($purchasedDaysAgo)->toDateString(),
            'requiredDate' => now()->addDays($requiredInDays)->toDateString(),
            'purchaseDetails' => collect($lines)->values()->map(fn (array $line, int $index): array => [
                'ID' => 970000 + $poIndex * 10 + $index,
                'productID' => 960000 + crc32($line[0]) % 10000,
                'productDetailID' => 950000 + crc32($line[0]) % 10000,
                'productCode' => $line[0],
                'productName' => $line[1],
                'uomID' => 1,
                'uomName' => $line[2],
                'qty' => $line[3],
                'deviationVal' => 0,
            ])->all(),
        ];
    }

    private function fetchCountKey(string $purchaseNumber): string
    {
        return "receiving-sandbox:fetches:{$purchaseNumber}";
    }
}
