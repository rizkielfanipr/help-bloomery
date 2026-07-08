<?php

namespace App\Filament\Casual\Pages;

use App\Enums\ServiceRequestStatus;
use App\Models\ServiceRequest;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Livewire\Attributes\Validate;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class TechnicianRequestHistoryPage extends Page
{
    use WithFileUploads;

    protected static bool $shouldRegisterNavigation = false;

    protected static string $layout = 'filament.casual.layouts.bare';

    protected string $view = 'filament.casual.pages.technician-request-history-page';

    public ?int $expandedId = null;

    public ?int $claimingId = null;

    public string $claimNotes = '';

    /** @var array<int, TemporaryUploadedFile> */
    #[Validate(['claimAttachments.*' => 'file|image|max:5120'])]
    public array $claimAttachments = [];

    public function getTitle(): string|Htmlable
    {
        return 'Riwayat Request Teknisi';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function toggleItem(int $id): void
    {
        $this->expandedId = $this->expandedId === $id ? null : $id;
        if ($this->expandedId !== $id) {
            $this->claimingId = null;
            $this->claimNotes = '';
            $this->claimAttachments = [];
        }
    }

    public function startClaim(int $id): void
    {
        $this->claimingId = $id;
        $this->claimNotes = '';
        $this->claimAttachments = [];
    }

    public function cancelClaim(): void
    {
        $this->claimingId = null;
        $this->claimNotes = '';
        $this->claimAttachments = [];
    }

    public function removeClaimAttachment(int $index): void
    {
        array_splice($this->claimAttachments, $index, 1);
        $this->claimAttachments = array_values($this->claimAttachments);
    }

    public function submitClaim(): void
    {
        $this->validate([
            'claimNotes' => ['required', 'string', 'min:10', 'max:2000'],
            'claimAttachments' => ['nullable', 'array'],
            'claimAttachments.*' => ['file', 'image', 'max:5120'],
        ]);

        $request = ServiceRequest::where('id', $this->claimingId)
            ->where('scheduled_by', auth()->id())
            ->where('status', ServiceRequestStatus::Warranty)
            ->firstOrFail();

        $paths = [];
        foreach ($this->claimAttachments as $file) {
            $paths[] = $file->store('service-request-warranty-claims', 'b2');
        }

        $request->update([
            'status' => ServiceRequestStatus::ReSubmitted,
            'technician_id' => null,
            'warranty_claim_notes' => $this->claimNotes,
            'warranty_claim_attachments' => $paths ?: null,
        ]);

        $this->claimingId = null;
        $this->claimNotes = '';
        $this->claimAttachments = [];

        Notification::make()
            ->title('Pengaduan garansi berhasil dikirim')
            ->success()
            ->send();
    }

    public function requests(): Collection
    {
        return ServiceRequest::where('scheduled_by', auth()->id())
            ->with(['technician', 'repairs'])
            ->orderByDesc('created_at')
            ->get();
    }
}
