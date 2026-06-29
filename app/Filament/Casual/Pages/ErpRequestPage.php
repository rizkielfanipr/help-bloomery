<?php

namespace App\Filament\Casual\Pages;

use App\Enums\RequestStatus;
use App\Models\ErpModule;
use App\Models\ErpRepairRequest;
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

    public string $keterangan = '';

    /** @var array<int, TemporaryUploadedFile> */
    #[Validate(['attachments.*' => 'file|max:10240'])]
    public array $attachments = [];

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

    public function removeAttachment(int $index): void
    {
        array_splice($this->attachments, $index, 1);
        $this->attachments = array_values($this->attachments);
    }

    public function submit(): void
    {
        $user = auth()->user();

        $this->validate([
            'erpModuleId' => ['required', 'exists:erp_modules,id'],
            'keterangan' => ['required', 'string'],
            'attachments.*' => ['file', 'max:10240'],
        ]);

        $paths = [];
        foreach ($this->attachments as $file) {
            $paths[] = $file->store('erp-requests', 'public');
        }

        ErpRepairRequest::create([
            'requester_id' => $user->id,
            'branch_id' => $user->branch_id,
            'erp_module_id' => $this->erpModuleId,
            'keterangan' => $this->keterangan,
            'attachments' => $paths ?: null,
            'status' => RequestStatus::Submitted,
        ]);

        $this->reset(['erpModuleId', 'keterangan', 'attachments']);

        Notification::make()
            ->title('Permintaan ERP berhasil dikirim!')
            ->body('Tim IT akan segera meninjau permintaan Anda.')
            ->success()
            ->send();
    }
}
