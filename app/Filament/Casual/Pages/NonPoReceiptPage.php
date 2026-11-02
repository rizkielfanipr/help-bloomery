<?php

namespace App\Filament\Casual\Pages;

use App\Actions\Inventory\Receiving\SaveNonPoReceiptDraftAction;
use App\Actions\Inventory\Receiving\SubmitNonPoReceiptAction;
use App\Actions\Purchasing\NonPoReceipt\CancelNonPoReceiptAction;
use App\Enums\NonPoReceiptStatus;
use App\Filament\Casual\Concerns\ManagesEsbStockProductPicker;
use App\Filament\Casual\Concerns\ManagesReceivingItems;
use App\Models\NonPoReceipt;
use App\Models\NonPoReceiptItem;
use App\Services\EsbGoodsReceiptService;
use App\Services\EsbItemJournalService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Throwable;

/**
 * "Tanpa PO" mode of the Receiving tile (docs/products/inventory/non-po-receiving/prd.md §17). Goods
 * and QC are recorded locally and handed to Purchasing; nothing here creates a Goods Receipt.
 */
class NonPoReceiptPage extends Page
{
    use ManagesEsbStockProductPicker;
    use ManagesReceivingItems;
    use WithFileUploads;

    private const COMPANY_CODE = 'BLSS';

    protected static bool $shouldRegisterNavigation = false;

    protected static string $layout = 'filament.casual.layouts.bare';

    protected string $view = 'filament.casual.pages.non-po-receipt-page';

    public bool $formOpen = false;

    #[Locked]
    public string $submissionKey = '';

    public string $goodsReceiptDate = '';

    public string $branchId = '';

    public string $locationId = '';

    public string $supplierText = '';

    public string $supplierContact = '';

    public string $reason = '';

    public string $documentType = 'delivery_note';

    public string $documentNumber = '';

    public string $documentDate = '';

    public bool $documentMatch = true;

    public string $documentNotes = '';

    public string $additionalInfo = '';

    /** @var array<int, TemporaryUploadedFile> */
    public array $documentPhotos = [];

    /** @var array<int, TemporaryUploadedFile> */
    public array $goodsPhotos = [];

    /** @var list<string> Paths kept from the saved draft; the save action re-checks ownership. */
    public array $storedDocumentPhotos = [];

    /** @var list<string> */
    public array $storedGoodsPhotos = [];

    public array $items = [];

    public ?int $activeItemIndex = null;

    /** @var array<int, array<string, mixed>> Server-fetched product data; items only ever copy their snapshot from here. */
    #[Locked]
    public array $verifiedProducts = [];

    #[Locked]
    public array $branches = [];

    #[Locked]
    public array $locations = [];

    #[Locked]
    public ?string $revisionNote = null;

    public ?string $loadError = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('accessEmployeeApp', NonPoReceipt::class) ?? false;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public function getTitle(): string|Htmlable
    {
        return 'Receiving Tanpa PO';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    /** @return Collection<int, NonPoReceipt> */
    #[Computed]
    public function receipts(): Collection
    {
        return NonPoReceipt::query()
            ->with('goodsReceipt:id,esb_goods_receipt_number')
            ->withCount('items')
            ->with(['statusHistories' => fn ($query) => $query->latest('id')->limit(1)])
            ->where('company_code', self::COMPANY_CODE)
            ->latest('id')
            ->limit(50)
            ->get();
    }

    public function openForm(): void
    {
        abort_unless(auth()->user()?->can('create', NonPoReceipt::class), 403);
        $this->resetForm();
        $this->submissionKey = (string) Str::uuid();
        $this->goodsReceiptDate = $this->documentDate = today()->toDateString();
        $this->formOpen = true;
        $this->loadBranches();
    }

    public function closeForm(): void
    {
        $this->resetForm();
        $this->formOpen = false;
    }

    public function editReceipt(int $receiptId): void
    {
        $receipt = NonPoReceipt::query()->with(['items.batches', 'statusHistories' => fn ($query) => $query->latest('id')])->findOrFail($receiptId);
        abort_unless(auth()->user()?->can('update', $receipt), 403);

        $this->resetForm();
        $this->submissionKey = $receipt->submission_key;
        $this->goodsReceiptDate = $receipt->receipt_date->toDateString();
        $this->documentDate = (string) $receipt->document_date?->toDateString();
        $this->branchId = (string) $receipt->esb_branch_id;
        $this->locationId = (string) $receipt->location_id;
        $this->supplierText = (string) $receipt->supplier_text;
        $this->supplierContact = (string) $receipt->supplier_contact;
        $this->reason = (string) $receipt->reason;
        $this->documentType = $receipt->document_type;
        $this->documentNumber = (string) $receipt->document_number;
        $this->documentMatch = $receipt->document_match;
        $this->documentNotes = (string) $receipt->document_notes;
        $this->additionalInfo = (string) $receipt->additional_info;
        $this->storedDocumentPhotos = $receipt->document_photos ?? [];
        $this->storedGoodsPhotos = $receipt->goods_photos ?? [];
        $this->items = $receipt->items->map(fn (NonPoReceiptItem $item): array => array_replace($item->toReceivingItem(), ['evidencePhotos' => []]))->all();
        $this->verifiedProducts = $receipt->items->mapWithKeys(fn (NonPoReceiptItem $item): array => [(int) $item->product_detail_id => [
            'productID' => (int) $item->product_id, 'productCode' => (string) $item->product_code,
            'productName' => $item->product_name, 'unit' => $item->uom_name,
        ]])->all();
        $this->revisionNote = $receipt->status === NonPoReceiptStatus::RevisionRequested ? $receipt->statusHistories->first()?->notes : null;
        $this->formOpen = true;
        $this->loadBranches();
        $this->loadLocations(keepSelection: true);
    }

    public function updatedBranchId(): void
    {
        $this->locationId = '';
        $this->loadLocations();
    }

    public function openProductPicker(): void
    {
        $this->productSearch = '';
        $this->productCodeSearch = '';
        $this->productPage = 1;
        $this->productPickerOpen = true;
    }

    public function closeProductPicker(): void
    {
        $this->productPickerOpen = false;
    }

    /** Options are display data here too: SubmitNonPoReceiptAction verifies every product again. */
    public function addProduct(int $productDetailId): void
    {
        $product = collect($this->productOptions)->firstWhere('productDetailID', $productDetailId);
        abort_unless(is_array($product), 422, 'Produk tidak ada pada hasil Master Product.');
        $this->closeProductPicker();

        if (collect($this->items)->contains(fn (array $item): bool => (int) $item['productDetailID'] === $productDetailId
            && NonPoReceiptItem::normalizeUom($item['uomName']) === NonPoReceiptItem::normalizeUom($product['unit'] ?? ''))) {
            Notification::make()->warning()->title('Produk sudah dipilih')->body("{$product['productName']} ({$product['unit']}) sudah ada di daftar.")->send();

            return;
        }

        $this->verifiedProducts[$productDetailId] = $product;
        $this->items[] = array_replace(NonPoReceiptItem::defaultQcData(), [
            'selected' => true, 'purchaseDetailID' => null, 'uomID' => null,
            'productID' => (int) $product['productID'], 'productDetailID' => $productDetailId,
            'productCode' => (string) $product['productCode'], 'productName' => (string) $product['productName'], 'uomName' => (string) $product['unit'],
            'orderedQty' => 0, 'outstandingQty' => null, 'physicalQty' => 0, 'acceptedQty' => 0, 'holdQty' => 0, 'rejectedQty' => 0,
            'invoicePrice' => null, 'deviationVal' => 0, 'batches' => [], 'evidencePhotos' => [], 'storedEvidencePhotos' => [], 'notes' => '',
        ]);
    }

    public function removeItem(int $index): void
    {
        array_splice($this->items, $index, 1);
        $this->activeItemIndex = null;
    }

    public function removeDocumentPhoto(int $index): void
    {
        array_splice($this->documentPhotos, $index, 1);
    }

    public function removeGoodsPhoto(int $index): void
    {
        array_splice($this->goodsPhotos, $index, 1);
    }

    public function removeStoredDocumentPhoto(int $index): void
    {
        array_splice($this->storedDocumentPhotos, $index, 1);
    }

    public function removeStoredGoodsPhoto(int $index): void
    {
        array_splice($this->storedGoodsPhotos, $index, 1);
    }

    public function saveDraft(): void
    {
        $this->save();
        Notification::make()->success()->title('Draft tersimpan')->send();
    }

    public function submit(): void
    {
        $receipt = $this->save();
        $receipt = app(SubmitNonPoReceiptAction::class)->execute(auth()->user(), $receipt);

        Notification::make()->success()->title(match ($receipt->status) {
            NonPoReceiptStatus::QcHold => 'QC Hold tersimpan',
            NonPoReceiptStatus::QcRejected => 'Penerimaan ditutup sebagai Rejected',
            default => 'Terkirim ke Purchasing',
        })->body($receipt->status === NonPoReceiptStatus::AwaitingPurchasing
            ? "{$receipt->receipt_number} menunggu PO. Barang tetap hold sampai Goods Receipt berhasil."
            : (string) $receipt->receipt_number)->send();

        $this->closeForm();
    }

    public function cancelReceipt(int $receiptId, string $reason): void
    {
        $receipt = NonPoReceipt::query()->findOrFail($receiptId);
        app(CancelNonPoReceiptAction::class)->execute(auth()->user(), $receipt, $reason);
        Notification::make()->success()->title('Penerimaan dibatalkan')->send();
    }

    private function save(): NonPoReceipt
    {
        $this->validate($this->rules());
        abort_unless($this->submissionKey !== '', 422);

        $items = $this->verifiedItems();
        $newPaths = [];
        try {
            $documentPhotos = [...$this->storedDocumentPhotos, ...$this->storePhotos($this->documentPhotos, 'documents', $newPaths)];
            $goodsPhotos = [...$this->storedGoodsPhotos, ...$this->storePhotos($this->goodsPhotos, 'goods', $newPaths)];
            $items = array_map(function (array $item) use (&$newPaths): array {
                $item['storedEvidencePhotos'] = [...($item['storedEvidencePhotos'] ?? []), ...$this->storePhotos($item['evidencePhotos'] ?? [], 'items', $newPaths)];

                return $item;
            }, $items);

            $branch = collect($this->branches)->firstWhere('branchID', (int) $this->branchId);
            $location = collect($this->locations)->firstWhere('locationID', (int) $this->locationId);

            $receipt = app(SaveNonPoReceiptDraftAction::class)->execute(auth()->user(), $this->submissionKey, [
                'receipt_date' => $this->goodsReceiptDate,
                'esb_branch_id' => $branch['branchID'] ?? null, 'esb_branch_code' => $branch['branchCode'] ?? null, 'branch_name' => $branch['branchName'] ?? null,
                'location_id' => $location['locationID'] ?? null, 'location_name' => $location['locationName'] ?? null,
                'supplier_text' => trim($this->supplierText) ?: null, 'supplier_contact' => trim($this->supplierContact) ?: null, 'reason' => trim($this->reason) ?: null,
                'document_type' => $this->documentType, 'document_number' => trim($this->documentNumber) ?: null, 'document_date' => $this->documentDate ?: null,
                'document_match' => $this->documentMatch, 'document_notes' => $this->documentNotes ?: null, 'additional_info' => $this->additionalInfo ?: null,
                'document_photos' => $documentPhotos, 'goods_photos' => $goodsPhotos,
            ], $items, $newPaths);
        } catch (Throwable $exception) {
            Storage::disk('b2')->delete($newPaths);
            throw $exception;
        }

        $this->documentPhotos = $this->goodsPhotos = [];
        $this->storedDocumentPhotos = $receipt->document_photos ?? [];
        $this->storedGoodsPhotos = $receipt->goods_photos ?? [];
        $this->items = array_map(fn (array $item): array => array_replace($item, ['evidencePhotos' => []]), $items);

        return $receipt;
    }

    /**
     * Product fields always come from data this page fetched from the server; the browser only
     * contributes quantities and QC.
     *
     * @return list<array<string, mixed>>
     */
    private function verifiedItems(): array
    {
        return collect($this->items)->values()->map(function (array $item, int $index): array {
            $product = $this->verifiedProducts[(int) ($item['productDetailID'] ?? 0)] ?? null;
            if ($product === null) {
                throw ValidationException::withMessages(["items.{$index}.productDetailID" => 'Produk harus dipilih dari Master Product.']);
            }

            return $this->normalizeOkItem(array_replace($item, [
                'productID' => (int) $product['productID'], 'productCode' => (string) $product['productCode'],
                'productName' => (string) $product['productName'], 'uomName' => (string) $product['unit'],
                'physicalQty' => (float) ($item['physicalQty'] ?? 0), 'acceptedQty' => (float) ($item['acceptedQty'] ?? 0),
                'holdQty' => (float) ($item['holdQty'] ?? 0), 'rejectedQty' => (float) ($item['rejectedQty'] ?? 0),
                'storedEvidencePhotos' => $item['condition'] === 'ok' ? [] : ($item['storedEvidencePhotos'] ?? []),
            ]));
        })->all();
    }

    private function pickerShowsStock(): bool
    {
        return false;
    }

    private function pickerCompanyCode(): string
    {
        return self::COMPANY_CODE;
    }

    /** @return array<int, array<string, mixed>> */
    private function pickerBranches(): array
    {
        return $this->branches;
    }

    private function loadBranches(): void
    {
        try {
            $this->branches = app(EsbItemJournalService::class)->branches(self::COMPANY_CODE);
            $this->loadError = null;
        } catch (Throwable $exception) {
            report($exception);
            $this->loadError = $exception->getMessage();
        }
    }

    private function loadLocations(bool $keepSelection = false): void
    {
        $this->locations = [];
        if ($this->branchId === '') {
            return;
        }

        try {
            $this->locations = app(EsbGoodsReceiptService::class)->locations(self::COMPANY_CODE, (int) $this->branchId);
            if (! $keepSelection && count($this->locations) === 1) {
                $this->locationId = (string) data_get($this->locations, '0.locationID');
            }
            $this->loadError = null;
        } catch (Throwable $exception) {
            report($exception);
            $this->loadError = $exception->getMessage();
        }
    }

    /**
     * Drafts may be incomplete, so only shape is checked here; SubmitNonPoReceiptAction enforces the
     * required fields on what was stored.
     *
     * @return array<string, array<int, string>>
     */
    private function rules(): array
    {
        return [
            'goodsReceiptDate' => ['required', 'date', 'before_or_equal:today'], 'documentDate' => ['nullable', 'date', 'before_or_equal:today'],
            'branchId' => ['nullable', 'integer'], 'locationId' => ['nullable', 'integer'],
            'supplierText' => ['nullable', 'string', 'max:255'], 'supplierContact' => ['nullable', 'string', 'max:255'], 'reason' => ['nullable', 'string', 'max:2000'],
            'documentType' => ['required', 'in:delivery_note,invoice'], 'documentNumber' => ['nullable', 'string', 'max:255'], 'documentNotes' => ['nullable', 'string', 'max:2000'],
            'additionalInfo' => ['nullable', 'string', 'max:2000'], 'documentPhotos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'], 'goodsPhotos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'items' => ['array'], 'items.*.invoicePrice' => ['nullable', 'numeric', 'min:0'],
            ...$this->itemRules(),
        ];
    }

    /**
     * @param  array<int, mixed>  $photos
     * @param  list<string>  $paths
     * @return list<string>
     */
    private function storePhotos(array $photos, string $directory, array &$paths): array
    {
        return collect($photos)->filter(fn ($photo): bool => $photo instanceof TemporaryUploadedFile)->map(function (TemporaryUploadedFile $photo) use ($directory, &$paths): string {
            $path = $photo->store("non-po-receipts/{$this->submissionKey}/{$directory}", 'b2');
            $paths[] = $path;

            return $path;
        })->values()->all();
    }

    private function resetForm(): void
    {
        $this->resetValidation();
        $this->reset('submissionKey', 'goodsReceiptDate', 'branchId', 'locationId', 'supplierText', 'supplierContact', 'reason', 'documentType', 'documentNumber', 'documentDate',
            'documentMatch', 'documentNotes', 'additionalInfo', 'documentPhotos', 'goodsPhotos', 'storedDocumentPhotos', 'storedGoodsPhotos', 'items', 'activeItemIndex',
            'productPickerOpen', 'productSearch', 'productCodeSearch', 'productPage', 'productTotal', 'productHasNext', 'productOptions', 'verifiedProducts', 'branches', 'locations', 'revisionNote', 'loadError');
    }
}
