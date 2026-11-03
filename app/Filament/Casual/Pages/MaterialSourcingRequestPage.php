<?php

namespace App\Filament\Casual\Pages;

use App\Actions\Purchasing\MaterialSourcingRequest\ResubmitMaterialSourcingRequestAction;
use App\Actions\Purchasing\MaterialSourcingRequest\SubmitMaterialSourcingRequestAction;
use App\Enums\MaterialSourcingRequestStatus;
use App\Filament\Casual\Concerns\ManagesEsbStockProductPicker;
use App\Models\MaterialSourcingRequest;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Employee App "Request Sourcing" form (docs/products/purchasing/employee-sourcing-request/prd.md §17).
 * It follows the Request ERP layout and reuses the Receiving Tanpa PO product sheet without stock.
 * Picker rows are display data: the Actions read the product again from ESB.
 */
class MaterialSourcingRequestPage extends Page
{
    use ManagesEsbStockProductPicker;
    use WithFileUploads;

    protected static bool $shouldRegisterNavigation = false;

    protected static string $layout = 'filament.casual.layouts.bare';

    protected string $view = 'filament.casual.pages.material-sourcing-request-page';

    #[Url(as: 'request')]
    public ?int $requestId = null;

    #[Locked]
    public string $submissionKey = '';

    #[Locked]
    public ?int $editingRequestId = null;

    #[Locked]
    public ?string $revisionReason = null;

    public string $productCode = '';

    public ?int $productDetailId = null;

    /** @var array<string, mixed>|null Display snapshot of the picked row. */
    #[Locked]
    public ?array $selectedProduct = null;

    public string $reason = '';

    public string $estimatedQuantity = '';

    public string $targetDate = '';

    public string $notes = '';

    /** @var array<int, TemporaryUploadedFile> */
    public array $attachments = [];

    /** @var list<array{path: string, name: string, mime: string, size: int}> */
    #[Locked]
    public array $storedAttachments = [];

    /** @var list<string> */
    public array $removedAttachmentPaths = [];

    public bool $submitted = false;

    #[Locked]
    public ?string $requestNumber = null;

    #[Locked]
    public ?string $submittedStatusLabel = null;

    public ?string $loadError = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('accessEmployeeApp', MaterialSourcingRequest::class) ?? false;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->submissionKey = (string) Str::uuid();

        if ($this->requestId) {
            $this->loadRevision($this->requestId);
        }
    }

    public function getTitle(): string|Htmlable
    {
        return 'Request Sourcing';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [];
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

    public function selectProduct(int $productDetailId): void
    {
        $product = collect($this->productOptions)->firstWhere('productDetailID', $productDetailId);
        abort_unless(is_array($product) && filled($product['productCode'] ?? null), 422, 'Produk tidak ada pada hasil Master Product.');

        $this->productCode = (string) $product['productCode'];
        $this->productDetailId = $productDetailId;
        $this->selectedProduct = [
            'productCode' => (string) $product['productCode'],
            'productName' => (string) ($product['productName'] ?? ''),
            'categoryName' => (string) ($product['categoryName'] ?? ''),
            'subCategoryName' => (string) ($product['subCategoryName'] ?? ''),
            'unit' => (string) (($product['unit'] ?? '') ?: ($product['baseUnit'] ?? '')),
        ];
        $this->resetValidation(['productCode', 'productDetailId']);
        $this->closeProductPicker();
    }

    public function removeAttachment(int $index): void
    {
        array_splice($this->attachments, $index, 1);
        $this->attachments = array_values($this->attachments);
    }

    public function toggleStoredAttachment(string $path): void
    {
        abort_unless(in_array($path, array_column($this->storedAttachments, 'path'), true), 422);

        $this->removedAttachmentPaths = in_array($path, $this->removedAttachmentPaths, true)
            ? array_values(array_diff($this->removedAttachmentPaths, [$path]))
            : [...$this->removedAttachmentPaths, $path];
    }

    public function submit(): void
    {
        $user = auth()->user();
        $input = [
            'submissionKey' => $this->submissionKey,
            'productCode' => $this->productCode,
            'productDetailId' => $this->productDetailId,
            'reason' => $this->reason,
            'estimatedQuantity' => $this->estimatedQuantity,
            'targetDate' => $this->targetDate,
            'notes' => $this->notes,
            'attachments' => $this->attachments,
            'removedAttachmentPaths' => $this->removedAttachmentPaths,
        ];

        $request = $this->editingRequestId
            ? app(ResubmitMaterialSourcingRequestAction::class)->execute($user, $this->ownedRequest($this->editingRequestId), $input)
            : app(SubmitMaterialSourcingRequestAction::class)->execute($user, $input);

        $this->requestNumber = $request->request_number;
        $this->submittedStatusLabel = $request->status->getLabel();
        $this->submitted = true;
        $this->resetForm();

        Notification::make()->success()->title('Request Sourcing terkirim')->body("{$request->request_number} masuk antrean Purchasing.")->send();
    }

    public function startNewRequest(): void
    {
        $this->submitted = false;
        $this->requestNumber = null;
        $this->submittedStatusLabel = null;
        $this->resetForm();
    }

    private function loadRevision(int $requestId): void
    {
        $request = MaterialSourcingRequest::query()->ownedBy(auth()->user())->find($requestId);
        abort_unless($request && $request->status === MaterialSourcingRequestStatus::RevisionRequested, 404);

        $this->editingRequestId = $request->id;
        $this->revisionReason = $request->last_decision_reason;
        $this->productCode = $request->product_code;
        $this->productDetailId = $request->esb_product_detail_id;
        $this->selectedProduct = [
            'productCode' => $request->product_code,
            'productName' => $request->product_name,
            'categoryName' => (string) $request->category_name,
            'subCategoryName' => (string) $request->sub_category_name,
            'unit' => $request->uom_name,
        ];
        $this->reason = $request->reason;
        $this->estimatedQuantity = $request->estimated_quantity !== null ? rtrim(rtrim((string) $request->estimated_quantity, '0'), '.') : '';
        $this->targetDate = (string) $request->target_date?->toDateString();
        $this->notes = (string) $request->notes;
        $this->storedAttachments = $request->attachmentList();
    }

    private function ownedRequest(int $requestId): MaterialSourcingRequest
    {
        return MaterialSourcingRequest::query()->ownedBy(auth()->user())->findOrFail($requestId);
    }

    private function resetForm(): void
    {
        $this->reset('requestId', 'editingRequestId', 'revisionReason', 'productCode', 'productDetailId', 'selectedProduct', 'reason',
            'estimatedQuantity', 'targetDate', 'notes', 'attachments', 'storedAttachments', 'removedAttachmentPaths',
            'productPickerOpen', 'productSearch', 'productCodeSearch', 'productOptions', 'loadError');
        $this->resetValidation();
        $this->submissionKey = (string) Str::uuid();
    }

    private function pickerShowsStock(): bool
    {
        return false;
    }

    private function pickerCompanyCode(): string
    {
        return MaterialSourcingRequest::COMPANY_CODE;
    }

    /** @return array<int, array<string, mixed>> */
    private function pickerBranches(): array
    {
        return [];
    }
}
