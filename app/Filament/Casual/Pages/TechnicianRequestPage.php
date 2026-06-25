<?php

namespace App\Filament\Casual\Pages;

use App\Enums\ServiceRequestStatus;
use App\Models\ServiceRequest;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Validate;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class TechnicianRequestPage extends Page
{
    use WithFileUploads;

    protected static bool $shouldRegisterNavigation = false;

    protected static string $layout = 'filament.casual.layouts.bare';

    protected string $view = 'filament.casual.pages.technician-request-page';

    public string $scheduledDate = '';

    public string $requestorNotes = '';

    /** @var array<int, TemporaryUploadedFile> */
    #[Validate(['attachments.*' => 'file|image|max:5120'])]
    public array $attachments = [];

    public function getTitle(): string|Htmlable
    {
        return new HtmlString('');
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function removeAttachment(int $index): void
    {
        array_splice($this->attachments, $index, 1);
        $this->attachments = array_values($this->attachments);
    }

    public function submit(): void
    {
        $this->validate([
            'scheduledDate' => ['required', 'date', 'after_or_equal:today'],
            'requestorNotes' => ['required', 'string', 'max:2000'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'image', 'max:5120'],
        ]);

        $paths = [];
        foreach ($this->attachments as $file) {
            $paths[] = $file->store('service-request-attachments', 'public');
        }

        ServiceRequest::create([
            'scheduled_by' => auth()->id(),
            'technician_id' => null,
            'scheduled_date' => $this->scheduledDate,
            'requestor_notes' => $this->requestorNotes,
            'attachments' => $paths ?: null,
            'status' => ServiceRequestStatus::Submitted->value,
        ]);

        $this->reset(['scheduledDate', 'requestorNotes', 'attachments']);

        Notification::make()
            ->title('Permintaan teknisi berhasil dikirim')
            ->success()
            ->send();

        $this->redirect(TechnicianRequestHistoryPage::getUrl());
    }
}
