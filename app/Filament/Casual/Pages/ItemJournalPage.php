<?php

namespace App\Filament\Casual\Pages;

use App\Models\QualityControlItemJournal;
use App\Services\EsbItemJournalService;
use App\Services\EsbService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\WithFileUploads;
use Throwable;

class ItemJournalPage extends Page
{
    use WithFileUploads;

    protected static bool $shouldRegisterNavigation = false;

    protected static string $layout = 'filament.casual.layouts.bare';

    protected string $view = 'filament.casual.pages.item-journal-page';

    public bool $formOpen = false;

    public string $journalDate = '';

    public string $branchId = '';

    public string $companyCode = '';

    public string $locationId = '';

    public string $additionalInfo = '';

    public string $productSearch = '';

    public string $productCodeSearch = '';

    public bool $productPickerOpen = false;

    public ?int $productPickerItemIndex = null;

    public int $productPage = 1;

    public int $productTotal = 0;

    public int $productPerPage = 10;

    public bool $productHasNext = false;

    public array $locations = [];

    public array $esbBranches = [];

    public array $purposes = [];

    public array $productOptions = [];

    public array $selectedProducts = [];

    public array $items = [];

    public array $attachments = [];

    public ?string $loadError = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', QualityControlItemJournal::class) ?? false;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->journalDate = today()->toDateString();
    }

    public function getTitle(): string|Htmlable
    {
        return 'Item Journal';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    #[Computed]
    public function journals(): Collection
    {
        $user = auth()->user();

        return QualityControlItemJournal::query()
            ->with(['details', 'attachments', 'branch'])
            ->visibleTo($user)
            ->latest()
            ->limit(50)
            ->get();
    }

    /** @return array<string, string> */
    public function companyOptions(): array
    {
        return collect((array) config('esb.core.companies'))->filter(
            fn (array $credentials): bool => filled($credentials['username'] ?? null) && filled($credentials['password'] ?? null)
        )->keys()->mapWithKeys(fn (string $code): array => [$code => $code])->all();
    }

    public function openForm(): void
    {
        abort_unless(auth()->user()?->can('create', QualityControlItemJournal::class), 403);
        $this->resetValidation();
        $this->reset(['companyCode', 'branchId', 'locationId', 'additionalInfo', 'esbBranches', 'locations', 'purposes', 'productOptions', 'selectedProducts', 'items', 'attachments', 'loadError', 'productSearch', 'productCodeSearch', 'productPickerOpen', 'productPickerItemIndex', 'productPage', 'productTotal', 'productHasNext']);
        $this->journalDate = today()->toDateString();
        $this->items = [$this->blankItem()];
        $this->formOpen = true;
    }

    public function closeForm(): void
    {
        $this->formOpen = false;
    }

    public function updatedCompanyCode(): void
    {
        $this->branchId = '';
        $this->locationId = '';
        $this->esbBranches = $this->locations = $this->purposes = [];
        $this->productOptions = $this->selectedProducts = [];
        $this->productPickerOpen = false;
        $this->productPickerItemIndex = null;
        foreach ($this->items as $index => $item) {
            $this->items[$index] = array_merge($item, [
                'productDetailID' => '',
                'productCode' => null,
                'productName' => null,
                'unit' => null,
                'purposeID' => '',
            ]);
        }
        $this->loadError = null;
        if ($this->companyCode === '') {
            return;
        }
        try {
            $service = app(EsbItemJournalService::class);
            $this->esbBranches = $service->branches($this->companyCode);
            $this->purposes = $service->purposes($this->companyCode);
        } catch (Throwable $exception) {
            report($exception);
            $this->loadError = $exception->getMessage();
        }
    }

    public function updatedBranchId(): void
    {
        $this->locationId = '';
        $this->locations = [];
        if ($this->companyCode === '' || $this->branchId === '') {
            return;
        }
        try {
            $this->locations = app(EsbItemJournalService::class)->locations($this->companyCode, (int) $this->branchId);
            if (count($this->locations) === 1) {
                $this->locationId = (string) data_get($this->locations, '0.locationID');
            }
        } catch (Throwable $exception) {
            report($exception);
            $this->loadError = $exception->getMessage();
        }
    }

    public function openProductPicker(int $itemIndex): void
    {
        abort_unless(array_key_exists($itemIndex, $this->items), 422);
        abort_if($this->companyCode === '', 422, 'Pilih ESB Comcode terlebih dahulu.');
        $this->productPickerItemIndex = $itemIndex;
        $this->productSearch = '';
        $this->productCodeSearch = '';
        $this->productPage = 1;
        $this->productPickerOpen = true;
    }

    public function closeProductPicker(): void
    {
        $this->productPickerOpen = false;
        $this->productPickerItemIndex = null;
    }

    public function loadProducts(bool $resetPage = false): void
    {
        if ($resetPage) {
            $this->productPage = 1;
        }

        try {
            abort_if($this->companyCode === '', 422, 'Pilih ESB Comcode terlebih dahulu.');
            $list = app(EsbItemJournalService::class)->products($this->companyCode, [
                'page' => $this->productPage,
                'limit' => 20,
                'productName' => trim($this->productSearch),
                'productCode' => trim($this->productCodeSearch),
            ]);
            $this->productOptions = app(EsbService::class)->getActiveProductDetailsByCodes(
                array_column($list['data'], 'productCode'),
            );
            $this->productPage = $list['page'];
            $this->productTotal = $list['count'];
            $this->productPerPage = $list['limit'];
            $this->productHasNext = filled($list['next'])
                || ($this->productPage * $this->productPerPage < $this->productTotal);
            $this->loadError = null;
        } catch (Throwable $exception) {
            report($exception);
            $this->loadError = $exception->getMessage();
            $this->productOptions = [];
        }
    }

    public function updatedProductSearch(): void
    {
        $this->loadProducts(true);
    }

    public function updatedProductCodeSearch(): void
    {
        $this->loadProducts(true);
    }

    public function previousProductPage(): void
    {
        if ($this->productPage > 1) {
            $this->productPage--;
            $this->loadProducts();
        }
    }

    public function nextProductPage(): void
    {
        if ($this->productHasNext) {
            $this->productPage++;
            $this->loadProducts();
        }
    }

    public function selectProduct(int $productDetailId): void
    {
        abort_unless($this->productPickerItemIndex !== null, 422);
        $product = collect($this->productOptions)->firstWhere('productDetailID', $productDetailId);
        abort_unless(is_array($product), 422);

        $this->items[$this->productPickerItemIndex]['productDetailID'] = $productDetailId;
        $this->items[$this->productPickerItemIndex]['productCode'] = $product['productCode'] ?? null;
        $this->items[$this->productPickerItemIndex]['productName'] = $product['productName'] ?? null;
        $this->items[$this->productPickerItemIndex]['unit'] = $product['unit'] ?? null;
        $this->selectedProducts[$productDetailId] = $product;
        $this->closeProductPicker();
    }

    public function addItem(): void
    {
        $this->items[] = $this->blankItem();
    }

    public function removeItem(int $index): void
    {
        array_splice($this->items, $index, 1);
    }

    public function removeAttachment(int $index): void
    {
        array_splice($this->attachments, $index, 1);
    }

    public function submit(): void
    {
        abort_unless(auth()->user()?->can('submit', QualityControlItemJournal::class), 403);
        $availableProducts = collect($this->selectedProducts)->merge($this->productOptions);
        $productIds = $availableProducts->pluck('productDetailID')->map(fn ($id): int => (int) $id)->all();
        $purposeIds = collect($this->purposes)->pluck('purposeID')->map(fn ($id): int => (int) $id)->all();
        $validated = $this->validate([
            'journalDate' => ['required', 'date'], 'companyCode' => ['required', Rule::in(array_keys($this->companyOptions()))], 'branchId' => ['required', 'integer'], 'locationId' => ['required', 'integer'],
            'additionalInfo' => ['nullable', 'string', 'max:1000', 'regex:/^[\x20-\x7E\r\n]*$/'],
            'items' => ['required', 'array', 'min:1'], 'items.*.productDetailID' => ['required', 'integer', Rule::in($productIds), 'distinct'],
            'items.*.purposeID' => ['required', 'integer', Rule::in($purposeIds)], 'items.*.qty' => ['required', 'numeric', 'not_in:0'],
            'items.*.hpp' => ['nullable', 'numeric', 'min:0'], 'attachments' => ['array', 'max:5'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);
        $service = app(EsbItemJournalService::class);
        $esbBranch = collect($this->esbBranches)->firstWhere('branchID', (int) $validated['branchId']);
        abort_unless(is_array($esbBranch), 422, 'Cabang ESB tidak valid untuk company ini.');
        $location = collect($this->locations)->firstWhere('locationID', (int) $validated['locationId']);
        abort_unless(is_array($location), 422, 'Lokasi tidak valid untuk cabang ini.');
        $payloadDetails = collect($validated['items'])->map(fn (array $item): array => ['ID' => -1, 'productDetailID' => (int) $item['productDetailID'], 'purposeID' => (int) $item['purposeID'], 'qty' => (float) $item['qty'], 'hpp' => filled($item['hpp'] ?? null) ? (float) $item['hpp'] : 0])->all();
        $payload = ['itemJournalDate' => $validated['journalDate'], 'branchID' => (int) $esbBranch['branchID'], 'locationID' => (int) $validated['locationId'], 'requestTemplateID' => null, 'additionalInfo' => trim($validated['additionalInfo']) ?: null, 'itemJournalDetails' => $payloadDetails];
        $journal = DB::transaction(function () use ($validated, $payload, $esbBranch, $location, $availableProducts): QualityControlItemJournal {
            $journal = QualityControlItemJournal::query()->create(['branch_id' => null, 'created_by' => auth()->id(), 'esb_branch_id' => $esbBranch['branchID'], 'esb_branch_code' => $esbBranch['branchCode'], 'esb_branch_name' => $esbBranch['branchName'] ?? null, 'esb_comcode' => $validated['companyCode'], 'location_id' => $validated['locationId'], 'location_name' => $location['locationName'] ?? 'Location '.$validated['locationId'], 'journal_date' => $validated['journalDate'], 'additional_info' => trim($validated['additionalInfo']) ?: null, 'status' => 'submitting', 'request_payload' => $payload]);
            foreach ($validated['items'] as $item) {
                $product = $availableProducts->firstWhere('productDetailID', (int) $item['productDetailID']);
                $purpose = collect($this->purposes)->firstWhere('purposeID', (int) $item['purposeID']);
                $journal->details()->create(['product_detail_id' => $item['productDetailID'], 'product_code' => $product['productCode'] ?? null, 'product_name' => $product['productName'] ?? 'Product '.$item['productDetailID'], 'uom_name' => $product['unit'] ?? null, 'purpose_id' => $item['purposeID'], 'purpose_name' => $purpose['purposeName'] ?? null, 'purpose_account' => $purpose['purposeAccount'] ?? null, 'qty' => $item['qty'], 'hpp' => $item['hpp'] ?: 0]);
            }
            foreach ($this->attachments as $file) {
                $path = $file->store('quality-control/item-journals/'.$journal->id, 'b2');
                $journal->attachments()->create(['uploaded_by' => auth()->id(), 'file_path' => $path, 'original_name' => $file->getClientOriginalName(), 'mime_type' => $file->getMimeType(), 'file_size' => $file->getSize()]);
            }

            return $journal;
        });
        try {
            $result = $service->create($validated['companyCode'], $payload);
            $journal->update(['item_journal_number' => $result['itemJournalNum'], 'response_payload' => $result['response'], 'submitted_at' => now(), 'status' => 'succeeded']);
            if ($journal->attachments()->exists()) {
                $this->uploadStoredAttachments($journal, $service);
            }
            $this->formOpen = false;
            unset($this->journals);
            Notification::make()->success()->title('Item Journal berhasil dibuat')->body($result['itemJournalNum'])->send();
        } catch (Throwable $exception) {
            report($exception);
            $journal->update(['status' => $journal->item_journal_number ? 'attachment_failed' : 'verification_required', 'last_error' => $exception->getMessage()]);
            Notification::make()->danger()->title('Item Journal perlu diperiksa')->body($exception->getMessage())->send();
        }
    }

    public function retryAttachments(int $journalId): void
    {
        $journal = $this->authorizedJournal($journalId);
        abort_unless(auth()->user()?->can('retryAttachments', $journal), 403);
        abort_unless(filled($journal->item_journal_number), 422);
        try {
            $this->uploadStoredAttachments($journal, app(EsbItemJournalService::class));
            Notification::make()->success()->title('Attachment berhasil diunggah')->send();
        } catch (Throwable $exception) {
            report($exception);
            $journal->update(['status' => 'attachment_failed', 'last_error' => $exception->getMessage()]);
            Notification::make()->danger()->title('Upload attachment gagal')->body($exception->getMessage())->send();
        }
    }

    public function deleteAllAttachments(int $journalId): void
    {
        $journal = $this->authorizedJournal($journalId);
        abort_unless(auth()->user()?->can('deleteAttachments', $journal), 403);
        app(EsbItemJournalService::class)->deleteAttachments($journal->esb_comcode, $journal->item_journal_number);
        foreach ($journal->attachments as $attachment) {
            Storage::disk('b2')->delete($attachment->file_path);
        }
        $journal->attachments()->delete();
        unset($this->journals);
        Notification::make()->success()->title('Seluruh attachment berhasil dihapus')->send();
    }

    private function uploadStoredAttachments(QualityControlItemJournal $journal, EsbItemJournalService $service): void
    {
        $attachments = $journal->attachments()->get();
        $files = $attachments->map(fn ($attachment): array => ['name' => $attachment->original_name, 'contents' => Storage::disk('b2')->get($attachment->file_path), 'mime' => $attachment->mime_type])->all();
        $urls = $service->uploadAttachments($journal->esb_comcode, $journal->item_journal_number, $files);
        foreach ($attachments->values() as $index => $attachment) {
            $attachment->update(['esb_url' => $urls[$index] ?? null, 'uploaded_to_esb_at' => now()]);
        }
        $journal->update(['status' => 'succeeded', 'last_error' => null]);
        unset($this->journals);
    }

    private function authorizedJournal(int $id): QualityControlItemJournal
    {
        $journal = QualityControlItemJournal::query()->with('attachments')->findOrFail($id);
        abort_unless(auth()->user()?->can('view', $journal), 403);

        return $journal;
    }

    private function blankItem(): array
    {
        return ['productDetailID' => '', 'productCode' => null, 'productName' => null, 'unit' => null, 'purposeID' => '', 'qty' => '', 'hpp' => '0'];
    }
}
