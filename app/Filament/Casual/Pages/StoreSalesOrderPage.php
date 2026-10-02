<?php

namespace App\Filament\Casual\Pages;

use App\Actions\StoreSalesOrder\CreateStoreSalesOrderAction;
use App\Actions\StoreSalesOrder\LookupStoreSalesOrderAction;
use App\Enums\StoreSalesOrderEventType;
use App\Enums\StoreSalesOrderProductType;
use App\Models\Branch;
use App\Models\StoreSalesOrder;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Validate;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * docs/store-sales-order-prd.md §10, §12, §17. No separate Riwayat tile for this feature (§12
 * "Tidak dibuat tile Riwayat terpisah") — the last five accessible records are rendered on this
 * same page. Mutation and the ESB lookup both go through Actions (never raw HTTP/Eloquent here);
 * this Page only owns UI state and upload handling, same division of responsibility as
 * CustomerComplaintPage.
 */
class StoreSalesOrderPage extends Page
{
    use WithFileUploads;

    protected static bool $shouldRegisterNavigation = false;

    protected static string $layout = 'filament.casual.layouts.bare';

    protected string $view = 'filament.casual.pages.store-sales-order-page';

    private const MAX_ATTACHMENTS = 5;

    public ?int $branchId = null;

    public string $productSalesNumber = '';

    public bool $isLookingUp = false;

    /** @var array<string, mixed>|null */
    public ?array $lookupResult = null;

    public string $phoneNumber = '';

    public string $orderedBy = '';

    public string $eventType = '';

    public string $eventTypeOther = '';

    public string $deliveryTime = '';

    public string $preparationNotes = '';

    /** @var list<array{product_type: string, custom_detail: string, quantity: string, notes: string}> */
    public array $items = [];

    /** @var array<int, TemporaryUploadedFile> */
    #[Validate(['attachments.*' => 'file|mimes:jpg,jpeg,png,webp,pdf|max:5120'])]
    public array $attachments = [];

    public bool $isSubmitting = false;

    public bool $submitted = false;

    public ?string $submittedNumber = null;

    public function mount(): void
    {
        $this->branchId = auth()->user()->primaryBranchId();
        $this->items = [$this->emptyItem()];
    }

    public function getTitle(): string|Htmlable
    {
        return 'Store Sales Order';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    /** @return Collection<int, Branch> */
    public function getAccessibleBranches(): Collection
    {
        return Branch::query()->whereIn('id', auth()->user()->accessibleBranchIds())->orderBy('name')->get();
    }

    /** @return list<StoreSalesOrderEventType> */
    public function getEventTypes(): array
    {
        return StoreSalesOrderEventType::cases();
    }

    /** @return list<StoreSalesOrderProductType> */
    public function getProductTypes(): array
    {
        return StoreSalesOrderProductType::cases();
    }

    /** @return Collection<int, StoreSalesOrder> */
    public function getRecentOrders(): Collection
    {
        $user = auth()->user();

        $query = StoreSalesOrder::query()->with('items')->latest();

        if ($user->can('view store sales orders')) {
            $query->where(function ($inner) use ($user): void {
                $inner->where('submitted_by', $user->id)
                    ->orWhereIn('branch_id', $user->accessibleBranchIds());
            });
        } else {
            $query->where('submitted_by', $user->id);
        }

        return $query->take(5)->get();
    }

    /** @return array{product_type: string, custom_detail: string, quantity: string, notes: string} */
    private function emptyItem(): array
    {
        return ['product_type' => '', 'custom_detail' => '', 'quantity' => '', 'notes' => ''];
    }

    public function updatedBranchId(): void
    {
        $this->clearLookup();
    }

    public function updatedProductSalesNumber(): void
    {
        $this->clearLookup();
    }

    public function clearLookup(): void
    {
        $this->lookupResult = null;
        $this->resetErrorBag('product_sales_number');
    }

    public function lookupEsb(LookupStoreSalesOrderAction $action): void
    {
        if ($this->isLookingUp) {
            return;
        }

        $this->isLookingUp = true;
        $this->lookupResult = null;

        try {
            $validated = $this->validate([
                'branchId' => ['required', 'integer'],
                'productSalesNumber' => ['required', 'string', 'max:100'],
            ]);

            if (! auth()->user()->canAccessBranch((int) $validated['branchId'])) {
                $this->addError('branchId', 'Branch yang dipilih tidak dapat diakses.');

                return;
            }

            $branch = Branch::query()->findOrFail($validated['branchId']);
            $number = trim($validated['productSalesNumber']);

            try {
                $result = $action->execute($branch, $number);
                $this->lookupResult = $result['snapshot'];
            } catch (ValidationException $exception) {
                foreach ($exception->errors() as $field => $messages) {
                    $this->addError($field, $messages[0]);
                }
            }
        } finally {
            $this->isLookingUp = false;
        }
    }

    public function addItem(): void
    {
        $this->items[] = $this->emptyItem();
    }

    public function removeItem(int $index): void
    {
        if (count($this->items) <= 1) {
            return;
        }

        array_splice($this->items, $index, 1);
        $this->items = array_values($this->items);
    }

    public function removeAttachment(int $index): void
    {
        array_splice($this->attachments, $index, 1);
        $this->attachments = array_values($this->attachments);
    }

    public function submit(CreateStoreSalesOrderAction $action): void
    {
        if ($this->isSubmitting) {
            return;
        }

        $this->isSubmitting = true;

        try {
            if (! $this->lookupResult) {
                $this->addError('product_sales_number', 'Cari Sales Order di ESB terlebih dahulu sebelum submit.');

                return;
            }

            $this->authorize('create', StoreSalesOrder::class);

            $validated = $this->validate([
                'branchId' => ['required', 'integer'],
                'productSalesNumber' => ['required', 'string', 'max:100'],
                'phoneNumber' => ['nullable', 'string', 'max:50'],
                'orderedBy' => ['nullable', 'string', 'max:150'],
                'eventType' => ['nullable', Rule::enum(StoreSalesOrderEventType::class)],
                'eventTypeOther' => ['nullable', 'string', 'max:150'],
                'deliveryTime' => ['nullable', 'date_format:H:i'],
                'preparationNotes' => ['nullable', 'string', 'max:2000'],
                'items' => ['required', 'array', 'min:1'],
                'items.*.product_type' => ['required', Rule::enum(StoreSalesOrderProductType::class)],
                'items.*.custom_detail' => ['nullable', 'string', 'max:500'],
                'items.*.quantity' => ['required', 'numeric', 'gt:0'],
                'items.*.notes' => ['nullable', 'string', 'max:500'],
                'attachments' => ['array', 'max:'.self::MAX_ATTACHMENTS],
                'attachments.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            ]);

            foreach ($validated['items'] as $index => $item) {
                if ($item['product_type'] === StoreSalesOrderProductType::Custom->value && blank($item['custom_detail'] ?? null)) {
                    $this->addError("items.{$index}.custom_detail", 'Detail Custom wajib diisi.');
                }
            }

            if ($this->getErrorBag()->isNotEmpty()) {
                return;
            }

            $paths = [];
            foreach ($this->attachments as $file) {
                $paths[] = $file->store('store-sales-orders/'.$validated['branchId'], 'b2');
            }

            try {
                $order = $action->execute([
                    'branch_id' => $validated['branchId'],
                    'product_sales_number' => $validated['productSalesNumber'],
                    'phone_number' => $validated['phoneNumber'] ?: null,
                    'ordered_by' => $validated['orderedBy'] ?: null,
                    'event_type' => $validated['eventType'] ?: null,
                    'event_type_other' => $validated['eventTypeOther'] ?: null,
                    'delivery_time' => $validated['deliveryTime'] ?: null,
                    'preparation_notes' => $validated['preparationNotes'] ?: null,
                    'attachment_paths' => $paths ?: null,
                    'items' => $validated['items'],
                ], auth()->user());
            } catch (\Throwable $exception) {
                Storage::disk('b2')->delete($paths);
                throw $exception;
            }

            $this->submittedNumber = $order->product_sales_number;
            $this->submitted = true;

            $this->reset([
                'productSalesNumber', 'lookupResult', 'phoneNumber', 'orderedBy', 'eventType',
                'eventTypeOther', 'deliveryTime', 'preparationNotes', 'items', 'attachments',
            ]);
            $this->items = [$this->emptyItem()];

            Notification::make()
                ->title('Store Sales Order berhasil disimpan')
                ->body("Nomor Sales Order {$order->product_sales_number} telah dicatat.")
                ->success()
                ->send();
        } finally {
            $this->isSubmitting = false;
        }
    }

    public function startNew(): void
    {
        $this->submitted = false;
        $this->submittedNumber = null;
    }
}
