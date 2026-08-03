<?php

namespace App\Filament\Helpdesk\Resources\PurchaseRequests\Pages;

use App\Enums\PurchaseRequestStatus;
use App\Filament\Helpdesk\Resources\PurchaseRequests\PurchaseRequestResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ViewPurchaseRequest extends ViewRecord
{
    protected static string $resource = PurchaseRequestResource::class;

    protected string $view = 'filament.helpdesk.purchase-requests.view';

    protected Width|string|null $maxContentWidth = Width::Full;

    public string $status = '';

    public string $adminNotes = '';

    public function mount(int|string $record): void
    {
        parent::mount($record);
        $this->record->load(['branch', 'processedBy', 'statusHistories.changedBy']);
        $this->status = $this->record->status->value;
        $this->adminNotes = '';
    }

    public function saveFollowUp(): void
    {
        abort_unless(auth()->user()?->can('edit purchase requests'), 403);
        $currentStatus = $this->record->status;
        $data = $this->validate([
            'status' => ['required', Rule::enum(PurchaseRequestStatus::class)],
            'adminNotes' => ['nullable', 'string', 'max:5000'],
        ]);

        $newStatus = PurchaseRequestStatus::from($data['status']);
        if (! $currentStatus->canTransitionTo($newStatus)) {
            throw ValidationException::withMessages([
                'status' => 'Status hanya dapat diubah mengikuti urutan proses.',
            ]);
        }

        if ($newStatus === PurchaseRequestStatus::Rejected && blank(trim($data['adminNotes'] ?? ''))) {
            throw ValidationException::withMessages([
                'adminNotes' => 'Alasan penolakan wajib diisi.',
            ]);
        }

        $this->record->update([
            'status' => $data['status'],
            'admin_notes' => trim($data['adminNotes']) ?: null,
            'processed_by' => auth()->id(),
        ]);
        $this->record->refresh()->load(['branch', 'processedBy', 'statusHistories.changedBy']);
        $this->adminNotes = '';

        Notification::make()->title('Tindak lanjut purchasing disimpan')->success()->send();
    }

    public function transitionTo(string $status): void
    {
        $this->status = $status;
        $this->saveFollowUp();
    }

    /** @return array<string, string> */
    public function statusOptions(): array
    {
        return collect($this->record->status->allowedTransitions())
            ->mapWithKeys(fn (PurchaseRequestStatus $status): array => [$status->value => $status->getLabel()])
            ->all();
    }

    public function getTitle(): string|Htmlable
    {
        return 'Detail Pengajuan Pembelian';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }
}
