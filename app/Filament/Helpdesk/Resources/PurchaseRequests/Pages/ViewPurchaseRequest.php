<?php

namespace App\Filament\Helpdesk\Resources\PurchaseRequests\Pages;

use App\Enums\PurchaseRequestStatus;
use App\Filament\Helpdesk\Resources\PurchaseRequests\PurchaseRequestResource;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\Rule;

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
        $this->record->load(['user', 'branch', 'processedBy']);
        $this->status = $this->record->status->value;
        $this->adminNotes = (string) ($this->record->admin_notes ?? '');
    }

    public function saveFollowUp(): void
    {
        abort_unless(auth()->user()?->can('edit purchase requests'), 403);
        $data = $this->validate([
            'status' => ['required', Rule::enum(PurchaseRequestStatus::class)],
            'adminNotes' => ['nullable', 'string', 'max:5000'],
        ]);

        $this->record->update([
            'status' => $data['status'],
            'admin_notes' => trim($data['adminNotes']) ?: null,
            'processed_by' => auth()->id(),
        ]);
        $this->record->refresh()->load(['user', 'branch', 'processedBy']);

        Notification::make()->title('Tindak lanjut purchasing disimpan')->success()->send();
    }

    public function getTitle(): string|Htmlable
    {
        return 'Detail Pengajuan Pembelian';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
