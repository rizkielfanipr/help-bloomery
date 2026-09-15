<?php

namespace App\Filament\Casual\Pages;

use App\Models\GoodsReceipt;
use App\Models\User;
use App\Services\EsbGoodsReceiptService;
use App\Services\InboundGoodsReceiptQcService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Throwable;

class GoodsReceiptPage extends Page
{
    use WithFileUploads;

    protected static bool $shouldRegisterNavigation = false;

    protected static string $layout = 'filament.casual.layouts.bare';

    protected string $view = 'filament.casual.pages.goods-receipt-page';

    public string $search = '';

    public array $purchaseOrders = [];

    public int $purchaseOrderPage = 1;

    public ?array $purchaseOrder = null;

    public array $locations = [];

    public array $items = [];

    public string $goodsReceiptDate = '';

    public string $locationId = '';

    public string $deliveryNumber = '';

    public string $deliveryDate = '';

    public string $invoiceStatus = 'received';

    public string $invoiceNumber = '';

    public string $invoiceDate = '';

    public bool $poDocumentMatch = true;

    public bool $deliveryDocumentMatch = true;

    public bool $invoiceDocumentMatch = true;

    public bool $priceMatch = true;

    public string $documentNotes = '';

    public array $documentEvidencePhotos = [];

    public string $additionalInfo = '';

    public bool $autoClosePo = false;

    public ?string $loadError = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('access employee app goods receipt') ?? false;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->goodsReceiptDate = $this->deliveryDate = now()->toDateString();
        $this->loadPurchaseOrders();
    }

    public function getTitle(): string|Htmlable
    {
        return 'Penerimaan Barang';
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
        try {
            $service = app(EsbGoodsReceiptService::class);
            $filters = ['page' => 1, 'limit' => 100, 'sort' => '-purchaseDate'];
            $this->purchaseOrders = collect(array_merge(
                $service->purchaseOrders($filters + ['statusID' => EsbGoodsReceiptService::PURCHASE_ORDER_STATUS_AUTHORIZED]),
                $service->purchaseOrders($filters + ['statusID' => EsbGoodsReceiptService::PURCHASE_ORDER_STATUS_RECEIVING]),
            ))->unique('purchaseNum')->values()->all();
            $this->purchaseOrderPage = 1;
        } catch (Throwable $exception) {
            report($exception);
            $this->loadError = $exception->getMessage();
        }
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

        return collect($this->purchaseOrders)->filter(fn (array $purchaseOrder): bool => $needle === '' || str_contains(
            mb_strtolower(implode(' ', [
                $purchaseOrder['purchaseNum'] ?? '',
                $purchaseOrder['supplierName'] ?? '',
                $purchaseOrder['branchName'] ?? '',
            ])),
            $needle,
        ))->values();
    }

    public function selectPurchaseOrder(string $purchaseNumber): void
    {
        try {
            $service = app(EsbGoodsReceiptService::class);
            $order = $service->purchaseOrder($purchaseNumber);
            abort_unless(in_array((int) data_get($order, 'statusID'), $this->receivableStatusIds(), true), 422, 'PO tidak dapat diterima.');
            $details = data_get($order, 'purchaseDetails', data_get($order, 'purchaseOrderDetails', []));
            $received = GoodsReceipt::query()->where('reference_number', $purchaseNumber)
                ->whereIn('status', [GoodsReceipt::STATUS_SUCCEEDED, GoodsReceipt::STATUS_PARTIAL_SUCCEEDED])
                ->with('items')->get()->flatMap->items->groupBy('product_detail_id')->map->sum('accepted_qty');
            $this->purchaseOrder = $order;
            $this->locations = $service->locations((int) data_get($order, 'branchID'));
            $this->locationId = count($this->locations) === 1 ? (string) data_get($this->locations, '0.locationID') : '';
            $this->items = collect($details)->map(function (array $detail) use ($received): array {
                $ordered = (float) ($detail['qty'] ?? 0);
                $outstanding = max(0, $ordered - (float) ($received[(int) ($detail['productDetailID'] ?? 0)] ?? 0));

                return [
                    'selected' => $outstanding > 0, 'purchaseDetailID' => $detail['ID'] ?? null,
                    'productID' => (int) ($detail['productID'] ?? 0), 'productDetailID' => (int) ($detail['productDetailID'] ?? 0),
                    'productCode' => (string) ($detail['productCode'] ?? ''), 'productName' => (string) ($detail['productName'] ?? 'Produk'),
                    'uomID' => $detail['uomID'] ?? null, 'uomName' => (string) ($detail['uomName'] ?? ''),
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

    public function removeDocumentEvidencePhoto(int $index): void
    {
        array_splice($this->documentEvidencePhotos, $index, 1);
        $this->documentEvidencePhotos = array_values($this->documentEvidencePhotos);
    }

    public function removeItemEvidencePhoto(int $itemIndex, int $photoIndex): void
    {
        array_splice($this->items[$itemIndex]['evidencePhotos'], $photoIndex, 1);
        $this->items[$itemIndex]['evidencePhotos'] = array_values($this->items[$itemIndex]['evidencePhotos']);
    }

    /** @return array<string, mixed> */
    public function itemQcPreview(int $itemIndex): array
    {
        return app(InboundGoodsReceiptQcService::class)->assessItem(
            $this->items[$itemIndex] ?? [],
            $this->goodsReceiptDate ?: now()->toDateString(),
        );
    }

    public function backToList(): void
    {
        $this->reset('purchaseOrder', 'locations', 'items', 'locationId', 'deliveryNumber', 'invoiceNumber', 'invoiceDate', 'documentNotes', 'documentEvidencePhotos', 'additionalInfo', 'autoClosePo');
    }

    public function submit(): void
    {
        $this->validate($this->rules());
        $purchaseNumber = (string) data_get($this->purchaseOrder, 'purchaseNum');
        abort_if($purchaseNumber === '', 422, 'Purchase Order belum dipilih.');
        $latest = app(EsbGoodsReceiptService::class)->purchaseOrder($purchaseNumber);
        abort_unless(in_array((int) data_get($latest, 'statusID'), $this->receivableStatusIds(), true), 422, 'Status PO sudah berubah.');
        $selected = collect($this->items)->filter(fn (array $item): bool => $item['selected'] && (float) $item['physicalQty'] > 0);
        if ($selected->isEmpty()) {
            $this->addError('items', 'Pilih minimal satu barang dengan qty fisik lebih dari 0.');

            return;
        }
        $qc = app(InboundGoodsReceiptQcService::class);
        $assessed = $selected->map(fn (array $item): array => $item + $qc->assessItem($item, $this->goodsReceiptDate));
        $this->validateQc($assessed, $qc);
        $location = collect($this->locations)->firstWhere('locationID', (int) $this->locationId);
        abort_unless($location, 422, 'Lokasi tidak valid untuk cabang PO ini.');
        $paths = [];
        try {
            $documentPhotos = $this->storePhotos($this->documentEvidencePhotos, 'goods-receipts/qc/documents', $paths);
            $assessed = $assessed->map(function (array $item) use (&$paths): array {
                $item['storedEvidencePhotos'] = $this->storePhotos($item['evidencePhotos'], 'goods-receipts/qc/items', $paths);

                return $item;
            });
            $payload = $this->payload($assessed);
            $receipt = DB::transaction(fn (): GoodsReceipt => $this->persist($purchaseNumber, $location, $payload, $assessed, $documentPhotos, $qc));
        } catch (Throwable $exception) {
            Storage::disk('b2')->delete($paths);
            throw $exception;
        }
        $this->notifyPurchasing($receipt);
        if ($payload['goodsReceiptDetail'] === []) {
            Notification::make()->warning()->title('QC tersimpan')->body('Tidak ada qty Accepted yang dikirim ke ESB.')->send();
            $this->backToList();

            return;
        }
        try {
            $response = app(EsbGoodsReceiptService::class)->create($purchaseNumber, $payload);
            $partial = $assessed->contains(fn (array $item): bool => (float) $item['holdQty'] > 0 || (float) $item['rejectedQty'] > 0);
            $receipt->update(['status' => $partial ? GoodsReceipt::STATUS_PARTIAL_SUCCEEDED : GoodsReceipt::STATUS_SUCCEEDED,
                'esb_goods_receipt_number' => data_get($response, 'result.goodsReceiptNum'), 'esb_code' => data_get($response, 'response.code'),
                'esb_message' => data_get($response, 'response.message'), 'response_payload' => $response['response'], 'synced_at' => now()]);
            Notification::make()->success()->title('Penerimaan berhasil')->body('Nomor GR: '.data_get($response, 'result.goodsReceiptNum', '-'))->send();
            $this->backToList();
            $this->loadPurchaseOrders();
        } catch (Throwable $exception) {
            report($exception);
            $receipt->update(['status' => GoodsReceipt::STATUS_FAILED, 'sync_error' => $exception->getMessage()]);
            Notification::make()->danger()->title('Penerimaan gagal dikirim')->body($exception->getMessage())->persistent()->send();
        }
    }

    private function rules(): array
    {
        return [
            'goodsReceiptDate' => ['required', 'date', 'before_or_equal:today'], 'deliveryDate' => ['required', 'date', 'before_or_equal:today'],
            'locationId' => ['required', 'integer'], 'deliveryNumber' => ['required', 'string', 'max:255'],
            'invoiceStatus' => ['required', 'in:received,not_received'], 'invoiceNumber' => ['required_if:invoiceStatus,received', 'nullable', 'string', 'max:255'],
            'invoiceDate' => ['required_if:invoiceStatus,received', 'nullable', 'date', 'before_or_equal:today'],
            'documentNotes' => ['nullable', 'string', 'max:2000'], 'documentEvidencePhotos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
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
            'items.*.notes' => ['nullable', 'string', 'max:1000'], 'items.*.batches' => ['array'],
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
        if (! $documentsPass && $this->documentEvidencePhotos === []) {
            $errors['documentEvidencePhotos'] = 'Foto bukti wajib saat dokumen tidak sesuai.';
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
        return ['goodsReceiptDate' => $this->goodsReceiptDate, 'locationID' => (int) $this->locationId, 'deliveryNum' => $this->deliveryNumber,
            'additionalInfo' => $this->additionalInfo, 'selectedAssetID' => '', 'autoClosePO' => $this->autoClosePo,
            'goodsReceiptDetail' => $items->filter(fn (array $item): bool => (float) $item['acceptedQty'] > 0)->map(fn (array $item): array => [
                'productID' => $item['productID'], 'productDetailID' => $item['productDetailID'], 'qty' => (float) $item['acceptedQty'],
                'deviationVal' => (float) $item['deviationVal'], 'notes' => $item['notes'],
                'expiredDates' => collect($item['batches'])->filter(fn (array $batch): bool => (float) $batch['acceptedQty'] > 0)
                    ->map(fn (array $batch): array => ['expiredDate' => $batch['expiredDate'], 'qty' => (float) $batch['acceptedQty']])->values()->all(),
            ])->values()->all()];
    }

    private function persist(string $po, array $location, array $payload, $items, array $documentPhotos, InboundGoodsReceiptQcService $qc): GoodsReceipt
    {
        $accepted = (float) $items->sum('acceptedQty');
        $hold = (float) $items->sum('holdQty');
        $rejected = (float) $items->sum('rejectedQty');
        $receipt = GoodsReceipt::create([
            'company_code' => 'BLSS', 'reference_number' => $po, 'purchase_date' => data_get($this->purchaseOrder, 'purchaseDate'), 'goods_receipt_date' => $this->goodsReceiptDate,
            'branch_id' => data_get($this->purchaseOrder, 'branchID'), 'branch_name' => data_get($this->purchaseOrder, 'branchName'),
            'supplier_id' => data_get($this->purchaseOrder, 'supplierID'), 'supplier_name' => data_get($this->purchaseOrder, 'supplierName'),
            'location_id' => $location['locationID'], 'location_name' => $location['locationName'], 'delivery_number' => $this->deliveryNumber, 'delivery_date' => $this->deliveryDate,
            'invoice_status' => $this->invoiceStatus, 'invoice_number' => $this->invoiceNumber ?: null, 'invoice_date' => $this->invoiceDate ?: null,
            'po_document_match' => $this->poDocumentMatch, 'delivery_document_match' => $this->deliveryDocumentMatch,
            'invoice_document_match' => $this->invoiceDocumentMatch, 'price_match' => $this->priceMatch, 'document_notes' => $this->documentNotes,
            'document_evidence_photos' => $documentPhotos, 'qc_outcome' => $qc->disposition($accepted, $hold, $rejected), 'qc_completed_at' => now(),
            'additional_info' => $this->additionalInfo, 'auto_close_po' => $this->autoClosePo,
            'status' => $accepted > 0 ? GoodsReceipt::STATUS_PROCESSING : ($rejected > 0 && $hold <= 0 ? GoodsReceipt::STATUS_QC_REJECTED : GoodsReceipt::STATUS_QC_HOLD),
            'submitted_by' => auth()->id(), 'submitted_at' => now(), 'request_payload' => $payload,
        ]);
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
            foreach ($item['batches'] as $batch) {
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
