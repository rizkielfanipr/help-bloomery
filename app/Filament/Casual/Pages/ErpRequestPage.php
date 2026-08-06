<?php

namespace App\Filament\Casual\Pages;

use App\Enums\ItRequestStatus;
use App\Models\ErpModule;
use App\Models\ErpRepairRequest;
use App\Models\ItRequestType;
use App\Services\WhatsappCtaBuilder;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Livewire\Attributes\Validate;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class ErpRequestPage extends Page
{
    use WithFileUploads;

    protected static bool $shouldRegisterNavigation = false;

    protected static string $layout = 'filament.casual.layouts.bare';

    protected string $view = 'filament.casual.pages.erp-request-page';

    public string $erpModuleId = '';

    public string $requestTypeId = '';

    public string $keterangan = '';

    /** @var array<int, TemporaryUploadedFile> */
    #[Validate(['attachments.*' => 'file|max:10240'])]
    public array $attachments = [];

    public bool $submitted = false;

    public ?string $whatsappUrl = null;

    public ?string $requestCode = null;

    public function getTitle(): string|Htmlable
    {
        return 'Request ERP';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    /** @return Collection<int, ErpModule> */
    public function getModules(): Collection
    {
        return ErpModule::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
    }

    /** @return Collection<int, ItRequestType> */
    public function getRequestTypes(): Collection
    {
        return ItRequestType::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
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
            'erpModuleId' => ['required', 'exists:erp_modules,id'],
            'requestTypeId' => ['required', 'exists:it_request_types,id'],
            'keterangan' => ['required', 'string'],
            'attachments.*' => ['file', 'max:10240'],
        ]);

        $paths = [];
        foreach ($this->attachments as $file) {
            $paths[] = $file->store('erp-requests', 'b2');
        }

        $requestType = ItRequestType::findOrFail($this->requestTypeId);
        $module = ErpModule::find($this->erpModuleId);

        $request = ErpRepairRequest::create([
            'requester_id' => $user->id,
            'branch_id' => $user->branch_id,
            'erp_module_id' => $this->erpModuleId,
            'request_type_id' => $this->requestTypeId,
            'keterangan' => $this->keterangan,
            'attachments' => $paths ?: null,
            'status' => ItRequestStatus::Submitted,
            'priority' => $requestType->priority,
        ]);

        $request->activities()->create([
            'actor_id' => $user->id,
            'action' => 'submitted',
            'to_status' => ItRequestStatus::Submitted->value,
            'notes' => 'Request submitted by user.',
        ]);

        $this->whatsappUrl = $whatsappCtaBuilder->build('erp_request', [
            'cabang' => $user->branch?->name ?? 'Tanpa Cabang',
            'requester' => $user->name,
            'kode' => $request->ticket_number,
            'modul' => $module?->name ?? '-',
            'request_type' => $requestType->name,
            'keterangan' => $this->keterangan,
            'link' => route('filament.helpdesk.resources.erp-repair-requests.view', $request),
        ]);
        $this->requestCode = $request->ticket_number;
        $this->submitted = true;

        $this->reset(['erpModuleId', 'requestTypeId', 'keterangan', 'attachments']);

        Notification::make()
            ->title('Permintaan ERP berhasil dikirim!')
            ->body("Ticket {$request->ticket_number} berhasil dibuat. Tim IT akan segera meninjau.")
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
