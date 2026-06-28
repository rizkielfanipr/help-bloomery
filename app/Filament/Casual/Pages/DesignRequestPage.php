<?php

namespace App\Filament\Casual\Pages;

use App\Enums\RequestStatus;
use App\Models\DesignCategory;
use App\Models\DesignRequest;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Validate;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class DesignRequestPage extends Page
{
    use WithFileUploads;

    protected static bool $shouldRegisterNavigation = false;

    protected static string $layout = 'filament.casual.layouts.bare';

    protected string $view = 'filament.casual.pages.design-request-page';

    public string $judulPermintaan = '';

    public string $designCategoryId = '';

    public string $ringkasanBrief = '';

    /** @var array<int, TemporaryUploadedFile> */
    #[Validate(['attachments.*' => 'file|max:10240'])]
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

    public function submit(): void
    {
        $user = auth()->user();

        $this->validate([
            'judulPermintaan' => ['required', 'string', 'max:255'],
            'designCategoryId' => ['required', 'exists:design_categories,id'],
            'ringkasanBrief' => ['required', 'string'],
            'attachments.*' => ['file', 'max:10240'],
        ]);

        $paths = [];
        foreach ($this->attachments as $file) {
            $paths[] = $file->store('design-requests', 'public');
        }

        DesignRequest::create([
            'requester_id' => $user->id,
            'branch_id' => $user->branch_id,
            'design_category_id' => $this->designCategoryId,
            'judul_permintaan' => $this->judulPermintaan,
            'ringkasan_brief' => $this->ringkasanBrief,
            'attachments' => $paths ?: null,
            'status' => RequestStatus::Submitted,
        ]);

        $this->reset(['judulPermintaan', 'designCategoryId', 'ringkasanBrief', 'attachments']);

        Notification::make()
            ->title('Permintaan design berhasil dikirim!')
            ->body('Tim design akan segera meninjau permintaan Anda.')
            ->success()
            ->send();
    }
}
