<?php

namespace App\Filament\Employee\Modules\Technician\Pages;

use App\Actions\Technician\CreateServiceRequestAction;
use App\Services\MasterData\WhatsappCtaBuilder;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Validate;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class TechnicianRequestPage extends Page
{
    use WithFileUploads;

    protected static bool $shouldRegisterNavigation = false;

    protected static string $layout = 'filament.employee.layouts.bare';

    protected string $view = 'filament.employee.technician.pages.technician-request-page';

    public string $requestorNotes = '';

    /** @var array<int, TemporaryUploadedFile> */
    #[Validate(['attachments.*' => 'file|image|max:5120'])]
    public array $attachments = [];

    public bool $submitted = false;

    public ?string $whatsappUrl = null;

    public ?string $requestCode = null;

    public function getTitle(): string|Htmlable
    {
        return 'Request Teknisi';
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

    public function submit(WhatsappCtaBuilder $whatsappCtaBuilder): void
    {
        $user = auth()->user();

        if (! $user->branch_id) {
            Notification::make()->title('Cabang belum diatur')->body('Hubungi admin untuk mengatur cabang Anda.')->warning()->send();

            return;
        }

        $this->validate([
            'requestorNotes' => ['required', 'string', 'max:2000'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'image', 'max:5120'],
        ]);

        $paths = [];
        foreach ($this->attachments as $file) {
            $paths[] = $file->store('service-request-attachments', 'b2');
        }

        $request = app(CreateServiceRequestAction::class)->execute($user, $this->requestorNotes, $paths);

        $this->whatsappUrl = $whatsappCtaBuilder->build('service_request', [
            'cabang' => $user->branch?->name ?? 'Tanpa Cabang',
            'requester' => $user->name,
            'kode' => $request->code,
            'tanggal' => 'Menunggu penjadwalan teknisi',
            'deskripsi' => $this->requestorNotes,
            'link' => route('filament.helpdesk.resources.service-requests.view', $request),
        ]);
        $this->requestCode = $request->code;
        $this->submitted = true;

        $this->reset(['requestorNotes', 'attachments']);

        Notification::make()
            ->title('Permintaan teknisi berhasil dikirim')
            ->body("Kode permintaan {$request->code} berhasil dibuat.")
            ->success()
            ->send();
    }

    public function startNewRequest(): void
    {
        $this->submitted = false;
        $this->whatsappUrl = null;
        $this->requestCode = null;
    }
}
