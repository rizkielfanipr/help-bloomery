<?php

namespace App\Filament\Casual\Pages;

use App\Actions\Inventory\Receiving\CreateGoodsReceiptAction;
use App\Enums\DeliveryAssessmentStatus;
use App\Filament\Casual\Concerns\ManagesReceivingItems;
use App\Models\GoodsReceipt;
use App\Models\User;
use App\Models\VendorComplianceIncident;
use App\Services\EsbBranchMappingResolver;
use App\Services\EsbGoodsReceiptService;
use App\Services\InboundGoodsReceiptQcService;
use App\Services\Purchasing\VendorCompliance\DeliveryDelayCalculator;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Throwable;

class GoodsReceiptPage extends Page
{
    use ManagesReceivingItems;
    use WithFileUploads;

    private const COMPANY_CODE = 'BLSS';

    protected static bool $shouldRegisterNavigation = false;

    protected static string $layout = 'filament.casual.layouts.bare';

    protected string $view = 'filament.casual.pages.goods-receipt-page';

    public string $search = '';

    public array $purchaseOrders = [];

    public int $purchaseOrderPage = 1;

    /**
     * docs/products/inventory/receiving-central-blss/prd.md §9: the PO, its Company Code, and the
     * locations of its ESB branch are server-fetched context, locked against client tampering.
     */
    #[Locked]
    public ?array $purchaseOrder = null;

    /**
     * docs/products/inventory/receiving-simplification/prd.md §4.4/§12: the Company Code the open PO was fetched
     * under — set once in selectPurchaseOrder() and reused for every later call (locations,
     * re-verification, submit) so a single PO never silently changes its source context.
     */
    #[Locked]
    public string $sourceCompanyCode = '';

    public string $submissionKey = '';

    #[Locked]
    public array $locations = [];

    public array $items = [];

    /** docs/products/inventory/receiving-simplification/prd.md §5.4: index of the item whose exception detail is open. */
    public ?int $activeItemIndex = null;

    public string $itemSearch = '';

    /** all|ok|problem|unchecked */
    public string $itemFilter = 'all';

    public string $goodsReceiptDate = '';

    public string $locationId = '';

    /** docs/products/inventory/receiving-simplification/prd.md §5.2: one document, not separate delivery/invoice fields. */
    public string $documentType = 'delivery_note';

    public string $documentNumber = '';

    public string $documentDate = '';

    public bool $poDocumentMatch = true;

    public bool $documentMatch = true;

    public bool $priceMatch = true;

    public string $documentNotes = '';

    /** @var array<int, TemporaryUploadedFile> docs/products/inventory/receiving-simplification/prd.md §5.3 "Foto Dokumen". */
    public array $documentPhotos = [];

    /** @var array<int, TemporaryUploadedFile> docs/products/inventory/receiving-simplification/prd.md §5.3 "Foto Barang". */
    public array $goodsPhotos = [];

    public string $additionalInfo = '';

    public ?string $loadError = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('accessEmployeeApp', GoodsReceipt::class) ?? false;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->goodsReceiptDate = $this->documentDate = now()->toDateString();
        $this->submissionKey = (string) Str::uuid();
        $this->loadPurchaseOrders();
    }

    public function getTitle(): string|Htmlable
    {
        return 'Receiving';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function iconPath(string $icon): string
    {
        return match ($icon) {
            'arrow-left' => 'M15.75 19.5 8.25 12l7.5-7.5',
            'arrow-right' => 'm8.25 4.5 7.5 7.5-7.5 7.5',
            'search' => 'm21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z',
            'sync' => 'M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 2.625c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125m16.5 5.625c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125',
            'purchase-order' => 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25Z',
            'create' => 'M12 9v6m3-3H9m12 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
            default => '',
        };
    }

    public function loadPurchaseOrders(): void
    {
        $this->loadError = null;
        $service = app(EsbGoodsReceiptService::class);
        $filters = ['page' => 1, 'limit' => 100, 'sort' => '-purchaseDate'];
        $orders = collect();
        $errors = [];

        foreach ([self::COMPANY_CODE] as $companyCode) {
            try {
                foreach ([EsbGoodsReceiptService::PURCHASE_ORDER_STATUS_AUTHORIZED, EsbGoodsReceiptService::PURCHASE_ORDER_STATUS_RECEIVING] as $statusId) {
                    $orders = $orders->concat(collect($service->purchaseOrders($companyCode, $filters + ['statusID' => $statusId]))
                        ->map(fn (array $po): array => $po + ['_companyCode' => $companyCode]));
                }
            } catch (Throwable $exception) {
                report($exception);
                $errors[] = "{$companyCode}: {$exception->getMessage()}";
            }
        }

        $this->purchaseOrders = $orders
            ->unique('purchaseNum')
            ->values()->all();
        $this->purchaseOrderPage = 1;
        $this->loadError = $errors === [] ? null : implode(' | ', $errors);
    }

    public function updatedSearch(): void
    {
        $this->purchaseOrderPage = 1;
    }

    public function searchPurchaseOrders(): void
    {
        $this->purchaseOrderPage = 1;
    }

    public function goToPurchaseOrderPage(int $page): void
    {
        $lastPage = max(1, (int) ceil($this->filteredPurchaseOrders()->count() / 10));
        $this->purchaseOrderPage = min(max(1, $page), $lastPage);
    }

    /** @return Collection<int, array<string, mixed>> */
    public function filteredPurchaseOrders(): Collection
    {
        $needle = mb_strtolower(trim($this->search));

        return collect($this->purchaseOrders)
            ->filter(fn (array $purchaseOrder): bool => $needle === '' || str_contains(
                mb_strtolower(implode(' ', [
                    $purchaseOrder['purchaseNum'] ?? '',
                    $purchaseOrder['supplierName'] ?? '',
                    $purchaseOrder['branchName'] ?? '',
                ])),
                $needle,
            ))
            ->sort(function (array $left, array $right): int {
                $dateComparison = $this->requiredDateSortValue($left) <=> $this->requiredDateSortValue($right);

                return $dateComparison !== 0
                    ? $dateComparison
                    : strnatcasecmp((string) ($left['purchaseNum'] ?? ''), (string) ($right['purchaseNum'] ?? ''));
            })
            ->values();
    }

    /** @param array<string, mixed> $purchaseOrder */
    private function requiredDateSortValue(array $purchaseOrder): int
    {
        $requiredDate = $purchaseOrder['requiredDate'] ?? null;
        if (blank($requiredDate)) {
            return PHP_INT_MAX;
        }

        try {
            return Carbon::parse($requiredDate)->startOfDay()->getTimestamp();
        } catch (Throwable) {
            return PHP_INT_MAX;
        }
    }

    /**
     * Preview of the delivery assessment for the opened PO and the chosen receipt date. Display only:
     * submit reads the PO again and CreateGoodsReceiptAction stores the final assessment.
     *
     * @return array{status: DeliveryAssessmentStatus, requiredDate: ?string, delayDays: ?int, note: ?string}
     */
    public function deliveryPreview(): array
    {
        return app(DeliveryDelayCalculator::class)->assess(data_get($this->purchaseOrder, 'requiredDate'), $this->goodsReceiptDate ?: now()->toDateString());
    }

    public function requiredDateLabel(mixed $requiredDate, string $fallback = '-'): string
    {
        return app(DeliveryDelayCalculator::class)->localDate($requiredDate)?->locale('id')->isoFormat('D MMM Y') ?? $fallback;
    }

    public function selectPurchaseOrder(string $purchaseNumber, string $companyCode): void
    {
        try {
            abort_unless($companyCode === self::COMPANY_CODE, 403, 'Company Code Receiving tidak dapat diakses.');

            $service = app(EsbGoodsReceiptService::class);
            $order = $service->purchaseOrder($companyCode, $purchaseNumber);
            abort_unless(in_array((int) data_get($order, 'statusID'), $this->receivableStatusIds(), true), 422, 'PO tidak dapat diterima.');

            $esbBranchId = (int) data_get($order, 'branchID');
            $details = data_get($order, 'purchaseDetails', data_get($order, 'purchaseOrderDetails', []));
            $received = GoodsReceipt::query()->where('reference_number', $purchaseNumber)->where('company_code', $companyCode)
                ->whereIn('status', [GoodsReceipt::STATUS_SUCCEEDED, GoodsReceipt::STATUS_PARTIAL_SUCCEEDED])
                ->with('items')->get()->flatMap->items->groupBy('product_detail_id')->map->sum('accepted_qty');
            $this->purchaseOrder = $order;
            $this->sourceCompanyCode = $companyCode;
            $this->submissionKey = (string) Str::uuid();
            $this->locations = $service->locations($companyCode, $esbBranchId);
            $this->locationId = count($this->locations) === 1 ? (string) data_get($this->locations, '0.locationID') : '';
            $this->items = collect($details)->map(function (array $detail) use ($received): array {
                $ordered = (float) ($detail['qty'] ?? 0);
                $outstanding = max(0, $ordered - (float) ($received[(int) ($detail['productDetailID'] ?? 0)] ?? 0));

                return [
                    'selected' => $outstanding > 0, 'purchaseDetailID' => $detail['ID'] ?? null,
                    'productID' => (int) ($detail['productID'] ?? 0), 'productDetailID' => (int) ($detail['productDetailID'] ?? 0),
                    'productCode' => (string) ($detail['productCode'] ?? ''), 'productName' => (string) ($detail['productName'] ?? 'Produk'),
                    'uomID' => $detail['uomID'] ?? null, 'uomName' => (string) ($detail['uomName'] ?? ''),
                    // docs/products/inventory/receiving-simplification/prd.md §4/§7: 'ok' is the normal/fast path —
                    // visual/cold-chain/shelf-life/sampling sub-fields already default to values
                    // InboundGoodsReceiptQcService treats as pass/not_applicable, so this toggle
                    // only controls which part of the UI is shown, not the QC math itself.
                    'condition' => 'ok',
                    // docs/products/inventory/receiving-simplification/prd.md §9: a quick, row-level ED independent of
                    // the detailed per-batch shelf-life workflow below — available regardless of
                    // condition, matching the PRD's own example table showing ED on a Sesuai row.
                    'expiryDate' => '',
                    'orderedQty' => $ordered, 'outstandingQty' => $outstanding, 'physicalQty' => $outstanding,
                    'acceptedQty' => $outstanding, 'holdQty' => 0, 'rejectedQty' => 0, 'measurementMethod' => 'count',
                    'tolerancePercentage' => 0, 'deviationVal' => (float) ($detail['deviationVal'] ?? 0),
                    'colorResult' => 'pass', 'textureResult' => 'pass', 'packagingResult' => 'pass', 'contaminationResult' => 'pass',
                    'temperatureCategory' => 'ambient', 'actualTemperature' => null, 'minTemperature' => null, 'maxTemperature' => null,
                    'shelfLifeRequired' => false, 'minimumShelfLifePercentage' => 80, 'batches' => [],
                    'samplingRequired' => false, 'samplingMethod' => '', 'samplingResult' => 'pass', 'samplingNotes' => '',
                    'quarantineLocation' => '', 'rejectionCategory' => '', 'rejectionReason' => '', 'evidencePhotos' => [], 'notes' => '',
                ];
            })->filter(fn (array $item): bool => $item['outstandingQty'] > 0)->values()->all();
        } catch (Throwable $exception) {
            report($exception);
            $this->loadError = $exception->getMessage();
            Notification::make()->danger()->title('PO tidak dapat dibuka')->body($exception->getMessage())->send();
        }
    }

    public function removeDocumentPhoto(int $index): void
    {
        array_splice($this->documentPhotos, $index, 1);
        $this->documentPhotos = array_values($this->documentPhotos);
    }

    public function removeGoodsPhoto(int $index): void
    {
        array_splice($this->goodsPhotos, $index, 1);
        $this->goodsPhotos = array_values($this->goodsPhotos);
    }

    /** @return list<int> indexes into $items matching the current search/filter, for the summary list. */
    public function visibleItemIndexes(): array
    {
        $needle = mb_strtolower(trim($this->itemSearch));

        return collect($this->items)->filter(function (array $item) use ($needle): bool {
            if ($needle !== '' && ! str_contains(mb_strtolower($item['productName'].' '.$item['productCode']), $needle)) {
                return false;
            }

            return match ($this->itemFilter) {
                'ok' => $item['selected'] && $item['condition'] === 'ok',
                'problem' => $item['selected'] && $item['condition'] === 'problem',
                'unchecked' => ! $item['selected'],
                default => true,
            };
        })->keys()->all();
    }

    public function selectAllItems(): void
    {
        foreach ($this->items as $index => $item) {
            $this->items[$index]['selected'] = true;
        }
    }

    public function markAllOk(): void
    {
        foreach (array_keys($this->items) as $index) {
            $this->setItemCondition($index, 'ok');
        }
    }

    public function fillQtyFromOutstanding(): void
    {
        foreach ($this->items as $index => $item) {
            if ($item['condition'] !== 'ok') {
                continue;
            }
            $this->items[$index]['physicalQty'] = $item['outstandingQty'];
            $this->items[$index]['acceptedQty'] = $item['outstandingQty'];
        }
    }

    public function clearAllQty(): void
    {
        foreach ($this->items as $index => $item) {
            if ($item['condition'] !== 'ok') {
                continue;
            }
            $this->items[$index]['physicalQty'] = 0;
            $this->items[$index]['acceptedQty'] = 0;
        }
    }

    public function backToList(): void
    {
        $this->reset('purchaseOrder', 'sourceCompanyCode', 'locations', 'items', 'locationId', 'documentNumber', 'documentDate', 'documentNotes', 'documentPhotos', 'goodsPhotos', 'additionalInfo', 'activeItemIndex', 'itemSearch', 'itemFilter');
    }

    public function submit(): void
    {
        abort_unless(auth()->user()?->can('submit', GoodsReceipt::class), 403);
        $this->validate($this->rules());
        $purchaseNumber = (string) data_get($this->purchaseOrder, 'purchaseNum');
        abort_if($purchaseNumber === '', 422, 'Purchase Order belum dipilih.');
        abort_unless($this->sourceCompanyCode === self::COMPANY_CODE, 403, 'Company Code Receiving tidak dapat diakses.');
        $latest = app(EsbGoodsReceiptService::class)->purchaseOrder($this->sourceCompanyCode, $purchaseNumber);
        abort_unless(in_array((int) data_get($latest, 'statusID'), $this->receivableStatusIds(), true), 422, 'Status PO sudah berubah.');
        // docs/products/inventory/receiving-central-blss/prd.md §7: the loaded locations belong to the
        // branch the PO had when it was opened, so a changed destination is never redirected silently.
        if ((int) data_get($latest, 'branchID') !== (int) data_get($this->purchaseOrder, 'branchID')) {
            $message = 'Cabang tujuan PO sudah berubah di ESB. Muat ulang PO lalu pilih ulang lokasi sebelum memproses penerimaan.';
            $this->loadError = $message;
            $this->addError('purchaseOrder', $message);
            Notification::make()
                ->danger()
                ->title('Konteks PO berubah')
                ->body($message)
                ->persistent()
                ->send();

            return;
        }
        // The local branch is optional metadata: Receiving permission covers every BLSS branch.
        $localBranchId = app(EsbBranchMappingResolver::class)->lookup(
            $this->sourceCompanyCode,
            (int) data_get($latest, 'branchID'),
            data_get($latest, 'branchCode'),
        )?->branch_id;
        $selected = collect($this->items)->filter(fn (array $item): bool => $item['selected'] && (float) $item['physicalQty'] > 0)
            ->map(fn (array $item): array => $this->normalizeOkItem($item));
        if ($selected->isEmpty()) {
            $this->addError('items', 'Pilih minimal satu barang dengan qty fisik lebih dari 0.');

            return;
        }
        $qc = app(InboundGoodsReceiptQcService::class);
        $assessed = $selected->map(fn (array $item): array => array_replace($item, $qc->assessItem($item, $this->goodsReceiptDate)));
        $this->validateQc($assessed, $qc);
        $location = collect($this->locations)->firstWhere('locationID', (int) $this->locationId);
        abort_unless($location, 422, 'Lokasi tidak valid untuk cabang PO ini.');
        $paths = [];
        try {
            $documentPhotos = $this->storePhotos($this->documentPhotos, 'goods-receipts/qc/documents', $paths);
            $goodsPhotos = $this->storePhotos($this->goodsPhotos, 'goods-receipts/qc/goods', $paths);
            $assessed = $assessed->map(function (array $item) use (&$paths): array {
                $item['storedEvidencePhotos'] = $this->storePhotos($item['evidencePhotos'], 'goods-receipts/qc/items', $paths);

                return $item;
            });
            $receipt = app(CreateGoodsReceiptAction::class)->record([
                'source' => GoodsReceipt::SOURCE_PURCHASE_ORDER, 'poRequiredDate' => data_get($latest, 'requiredDate'),
                'submissionKey' => $this->submissionKey, 'companyCode' => $this->sourceCompanyCode, 'purchaseNumber' => $purchaseNumber,
                'purchaseDate' => data_get($this->purchaseOrder, 'purchaseDate'), 'goodsReceiptDate' => $this->goodsReceiptDate,
                'esbBranchId' => (int) data_get($this->purchaseOrder, 'branchID'), 'localBranchId' => $localBranchId, 'branchName' => data_get($this->purchaseOrder, 'branchName'),
                'supplierId' => data_get($this->purchaseOrder, 'supplierID'), 'supplierName' => data_get($this->purchaseOrder, 'supplierName'), 'location' => $location,
                'documentType' => $this->documentType, 'documentNumber' => $this->documentNumber, 'documentDate' => $this->documentDate,
                'documentPhotos' => $documentPhotos, 'goodsPhotos' => $goodsPhotos,
                'poDocumentMatch' => $this->poDocumentMatch, 'documentMatch' => $this->documentMatch, 'priceMatch' => $this->priceMatch,
                'documentNotes' => $this->documentNotes, 'additionalInfo' => $this->additionalInfo,
            ], $assessed, (int) auth()->id());
        } catch (Throwable $exception) {
            Storage::disk('b2')->delete($paths);
            throw $exception;
        }
        if (! $receipt->wasRecentlyCreated) {
            Storage::disk('b2')->delete($paths);
            Notification::make()->warning()->title('Penerimaan sudah sedang diproses')->send();

            return;
        }
        $this->notifyPurchasing($receipt);
        if ($receipt->request_payload['goodsReceiptDetail'] === []) {
            Notification::make()->warning()->title('QC tersimpan')->body('Tidak ada qty Accepted yang dikirim ke ESB.')->send();
            $this->backToList();

            return;
        }
        app(CreateGoodsReceiptAction::class)->send($receipt);
        if (in_array($receipt->status, [GoodsReceipt::STATUS_SUCCEEDED, GoodsReceipt::STATUS_PARTIAL_SUCCEEDED], true)) {
            Notification::make()->success()->title('Penerimaan berhasil')->body('Nomor GR: '.($receipt->esb_goods_receipt_number ?? '-'))->send();
            $this->backToList();
            $this->loadPurchaseOrders();
        } else {
            Notification::make()->danger()->title('Penerimaan gagal dikirim')->body((string) $receipt->sync_error)->persistent()->send();
        }
    }

    private function rules(): array
    {
        return [
            'submissionKey' => ['required', 'uuid'],
            'goodsReceiptDate' => ['required', 'date', 'before_or_equal:today'], 'documentDate' => ['required', 'date', 'before_or_equal:today'],
            'locationId' => ['required', 'integer'], 'documentType' => ['required', 'in:delivery_note,invoice'],
            'documentNumber' => ['required', 'string', 'max:255'],
            'documentNotes' => ['nullable', 'string', 'max:2000'],
            'documentPhotos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'goodsPhotos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'additionalInfo' => ['nullable', 'string', 'max:2000'], 'items' => ['required', 'array'], 'items.*.selected' => ['boolean'],
            ...$this->itemRules(),
        ];
    }

    private function validateQc($items, InboundGoodsReceiptQcService $qc): void
    {
        $errors = [];
        $documentsPass = $qc->documentsPass(get_object_vars($this));
        if (! $documentsPass && $this->documentPhotos === []) {
            $errors['documentPhotos'] = 'Foto bukti wajib saat dokumen tidak sesuai.';
        }
        foreach ($items as $index => $item) {
            foreach ($qc->itemViolations($item, $documentsPass) as $field => $message) {
                $errors["items.{$index}.{$field}"] = $message;
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function storePhotos(array $photos, string $directory, array &$paths): array
    {
        return collect($photos)->filter(fn ($photo): bool => $photo instanceof TemporaryUploadedFile)->map(function (TemporaryUploadedFile $photo) use ($directory, &$paths): string {
            $path = $photo->store($directory, 'b2');
            $paths[] = $path;

            return $path;
        })->all();
    }

    private function notifyPurchasing(GoodsReceipt $receipt): void
    {
        // Late Delivery candidates notify their reviewers separately; this message is for QC rejections.
        if (! $receipt->vendorComplianceIncidents()->where('category', '!=', VendorComplianceIncident::CATEGORY_LATE_DELIVERY)->exists()) {
            return;
        }
        $recipients = User::query()->where('is_active', true)->role('PURCHASING_STAFF')->get();
        if ($recipients->isNotEmpty()) {
            Notification::make()->warning()->title('Rejection inbound perlu ditindaklanjuti')
                ->body("PO {$receipt->reference_number} · {$receipt->supplier_name} memiliki demerit vendor baru.")->sendToDatabase($recipients);
        }
    }

    private function receivableStatusIds(): array
    {
        return [EsbGoodsReceiptService::PURCHASE_ORDER_STATUS_AUTHORIZED, EsbGoodsReceiptService::PURCHASE_ORDER_STATUS_RECEIVING];
    }
}
