<?php

namespace App\Filament\Casual\Pages;

use App\Models\GoodsReceipt;
use App\Models\User;
use App\Services\EsbBranchMappingResolver;
use App\Services\EsbGoodsReceiptService;
use App\Services\InboundGoodsReceiptQcService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Throwable;

class GoodsReceiptPage extends Page
{
    use WithFileUploads;

    private const COMPANY_CODE = 'BLSS';

    protected static bool $shouldRegisterNavigation = false;

    protected static string $layout = 'filament.casual.layouts.bare';

    protected string $view = 'filament.casual.pages.goods-receipt-page';

    public string $search = '';

    public array $purchaseOrders = [];

    public int $purchaseOrderPage = 1;

    public ?array $purchaseOrder = null;

    /**
     * docs/receiving-simplification-prd.md §4.4/§12: the Company Code the open PO was fetched
     * under — set once in selectPurchaseOrder() and reused for every later call (locations,
     * re-verification, submit) so a single PO never silently changes its source context.
     */
    public string $sourceCompanyCode = '';

    public string $submissionKey = '';

    public array $locations = [];

    public array $items = [];

    /** docs/receiving-simplification-prd.md §5.4: index of the item whose exception detail is open. */
    public ?int $activeItemIndex = null;

    public string $itemSearch = '';

    /** all|ok|problem|unchecked */
    public string $itemFilter = 'all';

    public string $goodsReceiptDate = '';

    public string $locationId = '';

    /** docs/receiving-simplification-prd.md §5.2: one document, not separate delivery/invoice fields. */
    public string $documentType = 'delivery_note';

    public string $documentNumber = '';

    public string $documentDate = '';

    public bool $poDocumentMatch = true;

    public bool $documentMatch = true;

    public bool $priceMatch = true;

    public string $documentNotes = '';

    /** @var array<int, TemporaryUploadedFile> docs/receiving-simplification-prd.md §5.3 "Foto Dokumen". */
    public array $documentPhotos = [];

    /** @var array<int, TemporaryUploadedFile> docs/receiving-simplification-prd.md §5.3 "Foto Barang". */
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
                    // docs/receiving-simplification-prd.md §4/§7: 'ok' is the normal/fast path —
                    // visual/cold-chain/shelf-life/sampling sub-fields already default to values
                    // InboundGoodsReceiptQcService treats as pass/not_applicable, so this toggle
                    // only controls which part of the UI is shown, not the QC math itself.
                    'condition' => 'ok',
                    // docs/receiving-simplification-prd.md §9: a quick, row-level ED independent of
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

    public function addBatch(int $item): void
    {
        $this->items[$item]['batches'][] = ['batchNumber' => '', 'manufacturedDate' => '', 'expiredDate' => '', 'quantity' => 0, 'acceptedQty' => 0, 'holdQty' => 0, 'rejectedQty' => 0];
    }

    public function removeBatch(int $item, int $batch): void
    {
        unset($this->items[$item]['batches'][$batch]);
        $this->items[$item]['batches'] = array_values($this->items[$item]['batches']);
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

    public function removeItemEvidencePhoto(int $itemIndex, int $photoIndex): void
    {
        array_splice($this->items[$itemIndex]['evidencePhotos'], $photoIndex, 1);
        $this->items[$itemIndex]['evidencePhotos'] = array_values($this->items[$itemIndex]['evidencePhotos']);
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

    public function openItemDetail(int $index): void
    {
        $this->items[$index]['condition'] = 'problem';
        $this->activeItemIndex = $index;
        $this->dispatch('open-modal', 'item-detail');
    }

    public function closeItemDetail(): void
    {
        $this->activeItemIndex = null;
        $this->dispatch('close-modal', 'item-detail');
    }

    /**
     * docs/receiving-simplification-prd.md §7: switching back to "Sesuai" clears any exception
     * data entered while "Bermasalah" was selected, so a stale Hold/Rejected/rejection reason
     * never survives a condition change the user no longer intends.
     */
    public function setItemCondition(int $index, string $condition): void
    {
        $this->items[$index]['condition'] = $condition;

        if ($condition === 'ok') {
            $this->items[$index] = array_replace($this->items[$index], [
                'acceptedQty' => $this->items[$index]['physicalQty'], 'holdQty' => 0, 'rejectedQty' => 0,
                'colorResult' => 'pass', 'textureResult' => 'pass', 'packagingResult' => 'pass', 'contaminationResult' => 'pass',
                'temperatureCategory' => 'ambient', 'actualTemperature' => null, 'minTemperature' => null, 'maxTemperature' => null,
                'shelfLifeRequired' => false, 'batches' => [],
                'samplingRequired' => false, 'samplingMethod' => '', 'samplingResult' => 'pass', 'samplingNotes' => '',
                'quarantineLocation' => '', 'rejectionCategory' => '', 'rejectionReason' => '', 'evidencePhotos' => [],
            ]);
            $this->closeItemDetail();
        }
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

    /** @return array<string, mixed> */
    public function itemQcPreview(int $itemIndex): array
    {
        return app(InboundGoodsReceiptQcService::class)->assessItem(
            $this->items[$itemIndex] ?? [],
            $this->goodsReceiptDate ?: now()->toDateString(),
        );
    }

    /** @return array{days: int, label: string, color: string}|null */
    public function itemExpiryStatus(int $itemIndex): ?array
    {
        $expiryDate = $this->items[$itemIndex]['expiryDate'] ?? '';

        if (blank($expiryDate)) {
            return null;
        }

        return app(InboundGoodsReceiptQcService::class)->expiryStatus(
            $expiryDate,
            $this->goodsReceiptDate ?: now()->toDateString(),
        );
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
        abort_if(blank($this->sourceCompanyCode), 422, 'Company Code sumber Purchase Order tidak tersedia.');
        $latest = app(EsbGoodsReceiptService::class)->purchaseOrder($this->sourceCompanyCode, $purchaseNumber);
        abort_unless(in_array((int) data_get($latest, 'statusID'), $this->receivableStatusIds(), true), 422, 'Status PO sudah berubah.');
        $mapping = app(EsbBranchMappingResolver::class)->resolve(
            $this->sourceCompanyCode,
            (int) data_get($latest, 'branchID'),
            data_get($latest, 'branchCode'),
        );
        $user = auth()->user();
        abort_unless($user instanceof User && $mapping && ($user->canAccessAllBranches() || $user->canAccessBranch($mapping->branch_id)), 403, 'Cabang PO ini tidak dapat diakses oleh akun Anda.');
        $selected = collect($this->items)->filter(fn (array $item): bool => $item['selected'] && (float) $item['physicalQty'] > 0)
            // Server-side defense in depth: "Sesuai" always means the full qty is Accepted and
            // the exception fields are blank, regardless of whatever the client last held for
            // them (docs/receiving-simplification-prd.md §6-7).
            ->map(fn (array $item): array => $item['condition'] === 'ok' ? array_replace($item, [
                'acceptedQty' => $item['physicalQty'], 'holdQty' => 0, 'rejectedQty' => 0,
                'colorResult' => 'pass', 'textureResult' => 'pass', 'packagingResult' => 'pass', 'contaminationResult' => 'pass',
                'temperatureCategory' => 'ambient', 'shelfLifeRequired' => false, 'batches' => [],
                'samplingRequired' => false, 'samplingResult' => 'pass',
                'quarantineLocation' => '', 'rejectionCategory' => '', 'rejectionReason' => '', 'evidencePhotos' => [],
            ]) : $item);
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
            $payload = $this->payload($assessed);
            $receipt = DB::transaction(fn (): GoodsReceipt => $this->persist($purchaseNumber, $location, $payload, $assessed, $documentPhotos, $goodsPhotos, $qc));
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
        if ($payload['goodsReceiptDetail'] === []) {
            Notification::make()->warning()->title('QC tersimpan')->body('Tidak ada qty Accepted yang dikirim ke ESB.')->send();
            $this->backToList();

            return;
        }
        try {
            $response = app(EsbGoodsReceiptService::class)->create($this->sourceCompanyCode, $purchaseNumber, $payload);
            $partial = $assessed->contains(fn (array $item): bool => (float) $item['holdQty'] > 0 || (float) $item['rejectedQty'] > 0);
            $receipt->update(['status' => $partial ? GoodsReceipt::STATUS_PARTIAL_SUCCEEDED : GoodsReceipt::STATUS_SUCCEEDED,
                'esb_goods_receipt_number' => data_get($response, 'result.goodsReceiptNum'), 'esb_code' => data_get($response, 'response.code'),
                'esb_message' => data_get($response, 'response.message'), 'response_payload' => $response['response'], 'synced_at' => now()]);
            Notification::make()->success()->title('Penerimaan berhasil')->body('Nomor GR: '.data_get($response, 'result.goodsReceiptNum', '-'))->send();
            $this->backToList();
            $this->loadPurchaseOrders();
        } catch (Throwable $exception) {
            report($exception);
            $receipt->update(['status' => $this->isConnectionFailure($exception) ? GoodsReceipt::STATUS_UNKNOWN : GoodsReceipt::STATUS_FAILED, 'sync_error' => $exception->getMessage()]);
            Notification::make()->danger()->title('Penerimaan gagal dikirim')->body($exception->getMessage())->persistent()->send();
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
            'items.*.physicalQty' => ['nullable', 'numeric', 'min:0'], 'items.*.acceptedQty' => ['nullable', 'numeric', 'min:0'],
            'items.*.holdQty' => ['nullable', 'numeric', 'min:0'], 'items.*.rejectedQty' => ['nullable', 'numeric', 'min:0'],
            'items.*.measurementMethod' => ['required', 'in:count,weigh,measure'], 'items.*.tolerancePercentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'items.*.colorResult' => ['required', 'in:pass,fail,not_applicable'], 'items.*.textureResult' => ['required', 'in:pass,fail,not_applicable'],
            'items.*.packagingResult' => ['required', 'in:pass,fail,not_applicable'], 'items.*.contaminationResult' => ['required', 'in:pass,fail,not_applicable'],
            'items.*.temperatureCategory' => ['required', 'in:ambient,chilled,frozen'], 'items.*.actualTemperature' => ['nullable', 'numeric'],
            'items.*.minTemperature' => ['nullable', 'numeric'], 'items.*.maxTemperature' => ['nullable', 'numeric'],
            'items.*.minimumShelfLifePercentage' => ['required', 'numeric', 'min:0', 'max:100'], 'items.*.samplingMethod' => ['nullable', 'string', 'max:255'],
            'items.*.samplingResult' => ['required', 'in:pass,fail,pending'], 'items.*.samplingNotes' => ['nullable', 'string', 'max:1000'],
            'items.*.quarantineLocation' => ['nullable', 'string', 'max:255'], 'items.*.rejectionCategory' => ['nullable', 'in:document,quantity,quality,packaging,cold_chain,shelf_life,sampling,other'],
            'items.*.rejectionReason' => ['nullable', 'string', 'max:2000'], 'items.*.evidencePhotos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'items.*.notes' => ['nullable', 'string', 'max:1000'], 'items.*.expiryDate' => ['nullable', 'date'],
            'items.*.batches' => ['array'],
            'items.*.batches.*.batchNumber' => ['nullable', 'string', 'max:255'], 'items.*.batches.*.manufacturedDate' => ['nullable', 'date'],
            'items.*.batches.*.expiredDate' => ['nullable', 'date'], 'items.*.batches.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.batches.*.acceptedQty' => ['nullable', 'numeric', 'min:0'], 'items.*.batches.*.holdQty' => ['nullable', 'numeric', 'min:0'],
            'items.*.batches.*.rejectedQty' => ['nullable', 'numeric', 'min:0'],
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
            $physical = (float) $item['physicalQty'];
            $accepted = (float) $item['acceptedQty'];
            $hold = (float) $item['holdQty'];
            $rejected = (float) $item['rejectedQty'];
            if (abs($accepted + $hold + $rejected - $physical) > 0.0001) {
                $errors["items.{$index}.physicalQty"] = 'Accepted + Hold + Rejected harus sama dengan qty fisik.';
            }
            if ($accepted > (float) $item['outstandingQty']) {
                $errors["items.{$index}.acceptedQty"] = 'Qty Accepted melebihi sisa PO.';
            }
            if ($accepted > 0 && (! $documentsPass || ! $item['canAccept'])) {
                $errors["items.{$index}.acceptedQty"] = 'Qty tidak dapat Accepted karena pemeriksaan QC gagal atau pending.';
            }
            if (($hold > 0 || $rejected > 0) && (blank($item['quarantineLocation']) || blank($item['rejectionCategory']) || blank($item['rejectionReason']) || empty($item['evidencePhotos']))) {
                $errors["items.{$index}.rejectionReason"] = 'Lokasi karantina, kategori, alasan, dan foto wajib untuk Hold/Rejected.';
            }
            if (($item['temperatureCategory'] ?? 'ambient') !== 'ambient' && (! is_numeric($item['actualTemperature']) || ! is_numeric($item['minTemperature']) || ! is_numeric($item['maxTemperature']))) {
                $errors["items.{$index}.actualTemperature"] = 'Suhu aktual dan rentang cold chain wajib diisi.';
            }
            if (! empty($item['shelfLifeRequired'])) {
                if ($item['batches'] === []) {
                    $errors["items.{$index}.batches"] = 'Minimal satu batch wajib diisi.';
                } else {
                    foreach (['quantity' => $physical, 'acceptedQty' => $accepted, 'holdQty' => $hold, 'rejectedQty' => $rejected] as $field => $expected) {
                        if (abs((float) collect($item['batches'])->sum($field) - $expected) > 0.0001) {
                            $errors["items.{$index}.batches"] = 'Total pembagian qty batch harus sama dengan qty item.';
                        }
                    }
                }
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function payload($items): array
    {
        return ['goodsReceiptDate' => $this->goodsReceiptDate, 'locationID' => (int) $this->locationId, 'deliveryNum' => $this->documentNumber,
            'additionalInfo' => $this->additionalInfo, 'selectedAssetID' => '',
            // docs/receiving-simplification-prd.md §11/§17 Phase 1: computed automatically in
            // Phase 5 once outstanding-qty-across-the-whole-PO logic exists; false is the safe
            // interim (never auto-closes, matching today's default before the UI checkbox existed).
            'autoClosePO' => false,
            'goodsReceiptDetail' => $items->filter(fn (array $item): bool => (float) $item['acceptedQty'] > 0)->map(fn (array $item): array => [
                'productID' => $item['productID'], 'productDetailID' => $item['productDetailID'], 'qty' => (float) $item['acceptedQty'],
                'deviationVal' => (float) $item['deviationVal'], 'notes' => $item['notes'],
                'expiredDates' => collect($item['batches'])->filter(fn (array $batch): bool => (float) $batch['acceptedQty'] > 0)
                    ->map(fn (array $batch): array => ['expiredDate' => $batch['expiredDate'], 'qty' => (float) $batch['acceptedQty']])->values()->all(),
            ])->values()->all()];
    }

    private function persist(string $po, array $location, array $payload, $items, array $documentPhotos, array $goodsPhotos, InboundGoodsReceiptQcService $qc): GoodsReceipt
    {
        $accepted = (float) $items->sum('acceptedQty');
        $hold = (float) $items->sum('holdQty');
        $rejected = (float) $items->sum('rejectedQty');
        $esbBranchId = (int) data_get($this->purchaseOrder, 'branchID');
        $branchMapping = app(EsbBranchMappingResolver::class)->resolve(
            $this->sourceCompanyCode,
            $esbBranchId,
            data_get($this->purchaseOrder, 'branchCode'),
        );
        $user = auth()->user();
        abort_unless($user instanceof User && ($user->canAccessAllBranches() || ($branchMapping && $user->canAccessBranch($branchMapping->branch_id))), 403, 'Cabang PO belum terhubung atau tidak dapat diakses oleh akun Anda.');

        $payloadHash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
        $receipt = GoodsReceipt::query()->createOrFirst(['submission_key' => $this->submissionKey], [
            'company_code' => $this->sourceCompanyCode, 'reference_number' => $po, 'purchase_date' => data_get($this->purchaseOrder, 'purchaseDate'), 'goods_receipt_date' => $this->goodsReceiptDate,
            'esb_branch_id' => $esbBranchId, 'local_branch_id' => $branchMapping?->branch_id, 'branch_name' => data_get($this->purchaseOrder, 'branchName'),
            'supplier_id' => data_get($this->purchaseOrder, 'supplierID'), 'supplier_name' => data_get($this->purchaseOrder, 'supplierName'),
            'location_id' => $location['locationID'], 'location_name' => $location['locationName'],
            'document_type' => $this->documentType, 'document_number' => $this->documentNumber, 'document_date' => $this->documentDate,
            'document_photos' => $documentPhotos, 'goods_photos' => $goodsPhotos,
            // Legacy columns stay populated so reports/exports reading them directly keep working
            // during the transition (docs/receiving-simplification-prd.md §15).
            'delivery_number' => $this->documentType === 'delivery_note' ? $this->documentNumber : null,
            'delivery_date' => $this->documentType === 'delivery_note' ? $this->documentDate : null,
            'invoice_status' => $this->documentType === 'invoice' ? 'received' : 'not_received',
            'invoice_number' => $this->documentType === 'invoice' ? $this->documentNumber : null,
            'invoice_date' => $this->documentType === 'invoice' ? $this->documentDate : null,
            'po_document_match' => $this->poDocumentMatch, 'delivery_document_match' => $this->documentMatch,
            'invoice_document_match' => $this->documentMatch, 'price_match' => $this->priceMatch, 'document_notes' => $this->documentNotes,
            'document_evidence_photos' => array_merge($documentPhotos, $goodsPhotos), 'qc_outcome' => $qc->disposition($accepted, $hold, $rejected), 'qc_completed_at' => now(),
            'additional_info' => $this->additionalInfo, 'auto_close_po' => false,
            'status' => $accepted > 0 ? GoodsReceipt::STATUS_PROCESSING : ($rejected > 0 && $hold <= 0 ? GoodsReceipt::STATUS_QC_REJECTED : GoodsReceipt::STATUS_QC_HOLD),
            'payload_hash' => $payloadHash, 'attempted_at' => now(),
            'submitted_by' => auth()->id(), 'submitted_at' => now(), 'request_payload' => $payload,
        ]);
        if (! $receipt->wasRecentlyCreated) {
            return $receipt;
        }
        foreach ($items as $item) {
            $record = $receipt->items()->create([
                'purchase_detail_id' => $item['purchaseDetailID'], 'product_id' => $item['productID'], 'product_detail_id' => $item['productDetailID'],
                'product_code' => $item['productCode'], 'product_name' => $item['productName'], 'uom_id' => $item['uomID'], 'uom_name' => $item['uomName'],
                'ordered_qty' => $item['orderedQty'], 'outstanding_qty' => $item['outstandingQty'], 'received_qty' => $item['acceptedQty'],
                'physical_qty' => $item['physicalQty'], 'accepted_qty' => $item['acceptedQty'], 'hold_qty' => $item['holdQty'], 'rejected_qty' => $item['rejectedQty'],
                'deviation_value' => $item['deviationVal'], 'measurement_method' => $item['measurementMethod'], 'variance_percentage' => $item['variancePercentage'],
                'tolerance_percentage' => $item['tolerancePercentage'], 'quantity_check_result' => $item['quantityResult'], 'color_check_result' => $item['colorResult'],
                'texture_check_result' => $item['textureResult'], 'packaging_check_result' => $item['packagingResult'], 'contamination_check_result' => $item['contaminationResult'],
                'temperature_category' => $item['temperatureCategory'], 'actual_temperature' => $item['actualTemperature'], 'min_temperature' => $item['minTemperature'],
                'max_temperature' => $item['maxTemperature'], 'cold_chain_result' => $item['coldChainResult'], 'shelf_life_required' => $item['shelfLifeRequired'],
                'minimum_shelf_life_percentage' => $item['minimumShelfLifePercentage'], 'shelf_life_result' => $item['shelfLifeResult'], 'sampling_required' => $item['samplingRequired'],
                'sampling_method' => $item['samplingMethod'], 'sampling_result' => $item['samplingResult'], 'sampling_notes' => $item['samplingNotes'],
                'disposition' => $qc->disposition((float) $item['acceptedQty'], (float) $item['holdQty'], (float) $item['rejectedQty']),
                'quarantine_location' => $item['quarantineLocation'], 'rejection_category' => $item['rejectionCategory'], 'rejection_reason' => $item['rejectionReason'],
                'evidence_photos' => $item['storedEvidencePhotos'], 'notes' => $item['notes'], 'qc_inspected_by' => auth()->id(), 'qc_inspected_at' => now(),
            ]);
            // docs/receiving-simplification-prd.md §9/§15 "Pertahankan hasil QC rinci di
            // database": a quick row-level ED entered without going through the full
            // Bermasalah/batch workflow still needs to survive the save, so it becomes a single
            // implicit expiry record covering the whole accepted qty — only when the item has no
            // explicit batches of its own, so it never duplicates data the detailed workflow
            // already captured.
            $batches = $item['batches'] !== [] ? $item['batches'] : (filled($item['expiryDate']) ? [[
                'batchNumber' => null, 'manufacturedDate' => null, 'expiredDate' => $item['expiryDate'],
                'quantity' => $item['physicalQty'], 'acceptedQty' => $item['acceptedQty'], 'holdQty' => $item['holdQty'], 'rejectedQty' => $item['rejectedQty'],
                'shelfLifePercentage' => null,
            ]] : []);
            foreach ($batches as $batch) {
                $record->expiries()->create(['batch_number' => $batch['batchNumber'] ?: null, 'manufactured_date' => $batch['manufacturedDate'] ?: null,
                    'expired_date' => $batch['expiredDate'], 'quantity' => $batch['quantity'], 'accepted_quantity' => $batch['acceptedQty'], 'hold_quantity' => $batch['holdQty'],
                    'rejected_quantity' => $batch['rejectedQty'], 'shelf_life_remaining_percentage' => $batch['shelfLifePercentage'],
                    'qc_result' => $batch['shelfLifePercentage'] !== null && $batch['shelfLifePercentage'] >= (float) $item['minimumShelfLifePercentage'] ? 'pass' : 'fail']);
            }
            if ((float) $item['rejectedQty'] > 0) {
                $points = $qc->demeritPoints($item['rejectionCategory']);
                $record->vendorComplianceIncident()->create(['goods_receipt_id' => $receipt->id, 'supplier_id' => $receipt->supplier_id, 'supplier_name' => $receipt->supplier_name,
                    'category' => $item['rejectionCategory'], 'severity' => $points >= 20 ? 'high' : ($points >= 10 ? 'medium' : 'low'), 'demerit_points' => $points,
                    'affected_quantity' => $item['rejectedQty'], 'status' => 'open', 'description' => $item['rejectionReason'],
                    'evidence_photos' => $item['storedEvidencePhotos'], 'reported_by' => auth()->id(), 'occurred_at' => now()]);
            }
        }

        return $receipt;
    }

    private function isConnectionFailure(Throwable $exception): bool
    {
        do {
            if ($exception instanceof ConnectionException) {
                return true;
            }

            $exception = $exception->getPrevious();
        } while ($exception instanceof Throwable);

        return false;
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
        if (! $receipt->vendorComplianceIncidents()->exists()) {
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
