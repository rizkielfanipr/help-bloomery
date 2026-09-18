<?php

namespace App\Filament\Employee\Modules\Design\Pages;

use App\Enums\DesignRequestStatus;
use App\Models\DesignCategory;
use App\Models\DesignRequest;
use App\Services\MasterData\WhatsappCtaBuilder;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Livewire\Attributes\Validate;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class DesignRequestPage extends Page
{
    use WithFileUploads;

    protected static bool $shouldRegisterNavigation = false;

    protected static string $layout = 'filament.employee.layouts.bare';

    protected string $view = 'filament.employee.design.pages.design-request-page';

    public string $judulPermintaan = '';

    public string $designCategoryId = '';

    public string $ringkasanBrief = '';

    /** @var array<int, TemporaryUploadedFile> */
    #[Validate(['attachments.*' => 'file|max:10240'])]
    public array $attachments = [];

    public bool $submitted = false;

    public ?string $whatsappUrl = null;

    public ?string $requestCode = null;

    public function getTitle(): string|Htmlable
    {
        return 'Request Design';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    /** @return DesignCategory[] */
    public function getCategories(): Collection
    {
        return DesignCategory::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
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
            'judulPermintaan' => ['required', 'string', 'max:255'],
            'designCategoryId' => ['required', 'exists:design_categories,id'],
            'ringkasanBrief' => ['required', 'string'],
            'attachments.*' => ['file', 'max:10240'],
        ]);

        $paths = [];
        foreach ($this->attachments as $file) {
            $paths[] = $file->store('design-requests', 'b2');
        }

        $category = DesignCategory::find($this->designCategoryId);

        $request = DesignRequest::create([
            'requester_id' => $user->id,
            'branch_id' => $user->branch_id,
            'design_category_id' => $this->designCategoryId,
            'judul_permintaan' => $this->judulPermintaan,
            'ringkasan_brief' => $this->ringkasanBrief,
            'attachments' => $paths ?: null,
            'status' => DesignRequestStatus::DesignRequest,
        ]);

        $this->whatsappUrl = $whatsappCtaBuilder->build('design_request', [
            'cabang' => $user->branch?->name ?? 'Tanpa Cabang',
            'requester' => $user->name,
            'kode' => $request->code,
            'judul' => $this->judulPermintaan,
            'kategori' => $category?->name ?? '-',
            'brief' => $this->ringkasanBrief,
            'link' => route('filament.helpdesk.resources.design-requests.view', $request),
        ]);
        $this->requestCode = $request->code;
        $this->submitted = true;

        $this->reset(['judulPermintaan', 'designCategoryId', 'ringkasanBrief', 'attachments']);

        Notification::make()
            ->title('Permintaan design berhasil dikirim!')
            ->body("Kode permintaan {$request->code} berhasil dibuat. Tim design akan segera meninjau.")
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
