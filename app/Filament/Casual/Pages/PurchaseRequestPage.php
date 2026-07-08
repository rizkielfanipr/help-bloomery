<?php

namespace App\Filament\Casual\Pages;

use App\Enums\PurchaseRequestStatus;
use App\Enums\PurchaseType;
use App\Models\PurchaseRequest;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Validate;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class PurchaseRequestPage extends Page
{
    use WithFileUploads;

    protected static bool $shouldRegisterNavigation = false;

    protected static string $layout = 'filament.casual.layouts.bare';

    protected string $view = 'filament.casual.pages.purchase-request-page';

    public string $itemName = '';

    public int|string $quantity = '';

    public string $purchaseReason = '';

    public string $purchaseType = '';

    public string $journalItemNumber = '';

    public string $purchaseRequestNumber = '';

    public string $ecommerceLink = '';

    /** @var array<int, TemporaryUploadedFile> */
    #[Validate(['attachments.*' => 'file|image|max:5120'])]
    public array $attachments = [];

    public function getTitle(): string|Htmlable
    {
        return 'Pengajuan Pembelian';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function updatedPurchaseType(): void
    {
        if ($this->purchaseType !== PurchaseType::Broken->value) {
            $this->journalItemNumber = '';
        }
    }

    public function removeAttachment(int $index): void
    {
        array_splice($this->attachments, $index, 1);
        $this->attachments = array_values($this->attachments);
    }

    public function submit(): void
    {
        $user = auth()->user();

        if (! $user->branch_id) {
            Notification::make()->title('Cabang belum diatur')->body('Hubungi admin untuk mengatur cabang Anda.')->warning()->send();

            return;
        }

        $this->validate([
            'itemName' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1'],
            'purchaseReason' => ['required', 'string'],
            'purchaseType' => ['required', 'in:new,broken'],
            'journalItemNumber' => [$this->purchaseType === 'broken' ? 'required' : 'nullable', 'string', 'max:255'],
            'purchaseRequestNumber' => ['nullable', 'string', 'max:255'],
            'ecommerceLink' => ['nullable', 'url', 'max:500'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'image', 'max:5120'],
        ]);

        $paths = [];
        foreach ($this->attachments as $file) {
            $paths[] = $file->store('purchase-requests', 'b2');
        }

        PurchaseRequest::create([
            'user_id' => $user->id,
            'branch_id' => $user->branch_id,
            'division' => $user->branch->name,
            'item_name' => $this->itemName,
            'quantity' => (int) $this->quantity,
            'purchase_reason' => $this->purchaseReason,
            'purchase_type' => $this->purchaseType,
            'journal_item_number' => $this->journalItemNumber ?: null,
            'purchase_request_number' => $this->purchaseRequestNumber ?: null,
            'ecommerce_link' => $this->ecommerceLink ?: null,
            'attachment_paths' => $paths ?: null,
            'status' => PurchaseRequestStatus::Submitted->value,
        ]);

        $this->reset(['itemName', 'quantity', 'purchaseReason', 'purchaseType',
            'journalItemNumber', 'purchaseRequestNumber', 'ecommerceLink', 'attachments']);

        Notification::make()
            ->title('Pengajuan berhasil dikirim')
            ->success()
            ->send();
    }
}
