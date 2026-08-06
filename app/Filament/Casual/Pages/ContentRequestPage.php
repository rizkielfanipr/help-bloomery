<?php

namespace App\Filament\Casual\Pages;

use App\Enums\ContentRequestStatus;
use App\Models\ContentRequest;
use App\Services\WhatsappCtaBuilder;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Validate;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class ContentRequestPage extends Page
{
    use WithFileUploads;

    protected static bool $shouldRegisterNavigation = false;

    protected static string $layout = 'filament.casual.layouts.bare';

    protected string $view = 'filament.casual.pages.content-request-page';

    public string $judulKonten = '';

    public string $jenisKonten = '';

    public string $platformTujuan = '';

    public string $tujuanKonten = '';

    public string $linkContohKonten = '';

    /** @var array<int, TemporaryUploadedFile> */
    #[Validate(['attachments.*' => 'file|max:10240'])]
    public array $attachments = [];

    public bool $submitted = false;

    public ?string $whatsappUrl = null;

    public ?string $requestCode = null;

    public function getTitle(): string|Htmlable
    {
        return 'Request Konten';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    /** @return array<string, string> */
    public function getPlatforms(): array
    {
        return [
            'Instagram' => 'Instagram',
            'TikTok' => 'TikTok',
            'YouTube' => 'YouTube',
            'Facebook' => 'Facebook',
            'Website' => 'Website',
            'Lainnya' => 'Lainnya',
        ];
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
            'judulKonten' => ['required', 'string', 'max:255'],
            'jenisKonten' => ['required', 'in:photo,video'],
            'platformTujuan' => ['required', 'string', 'max:255'],
            'tujuanKonten' => ['required', 'string'],
            'linkContohKonten' => ['nullable', 'url', 'max:500'],
            'attachments.*' => ['file', 'max:10240'],
        ]);

        $paths = [];
        foreach ($this->attachments as $file) {
            $paths[] = $file->store('content-requests', 'b2');
        }

        $request = ContentRequest::create([
            'requester_id' => $user->id,
            'branch_id' => $user->branch_id,
            'judul_konten' => $this->judulKonten,
            'jenis_konten' => $this->jenisKonten,
            'platform_tujuan' => $this->platformTujuan,
            'tujuan_konten' => $this->tujuanKonten,
            'link_contoh_konten' => $this->linkContohKonten ?: null,
            'attachments' => $paths ?: null,
            'status' => ContentRequestStatus::Submitted,
        ]);

        $this->whatsappUrl = $whatsappCtaBuilder->build('content_request', [
            'cabang' => $user->branch?->name ?? 'Tanpa Cabang',
            'requester' => $user->name,
            'kode' => $request->code,
            'judul' => $this->judulKonten,
            'jenis' => $this->jenisKonten === 'video' ? 'Video' : 'Foto',
            'platform' => $this->platformTujuan,
            'tujuan' => $this->tujuanKonten,
            'link' => route('filament.helpdesk.resources.content-requests.view', $request),
        ]);
        $this->requestCode = $request->code;
        $this->submitted = true;

        $this->reset(['judulKonten', 'jenisKonten', 'platformTujuan', 'tujuanKonten', 'linkContohKonten', 'attachments']);

        Notification::make()
            ->title('Permintaan konten berhasil dikirim!')
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
