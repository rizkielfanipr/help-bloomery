<?php

namespace App\Filament\Casual\Pages;

use App\Models\TechnicianMaintenance;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class TechnicianMaintenanceDetailPage extends Page
{
    use WithFileUploads;

    protected static bool $shouldRegisterNavigation = false;

    protected static string $layout = 'filament.casual.layouts.bare';

    protected string $view = 'filament.casual.pages.technician-maintenance-detail-page';

    public int $record;

    public array $results = [];

    public string $overallNotes = '';

    public string $checkedAt = '';

    public static function getRoutePath(Panel $panel): string
    {
        return '/'.static::getSlug($panel).'/{record}';
    }

    public function mount(int $record): void
    {
        abort_unless(auth()->user()?->can('view technician monthly maintenance'), 403);
        $maintenance = $this->maintenance();
        $this->results = $maintenance->items->mapWithKeys(fn ($item): array => [$item->id => ['result' => $item->result, 'notes' => $item->notes]])->all();
        $this->overallNotes = (string) $maintenance->overall_notes;
        $this->checkedAt = $maintenance->checked_at?->format('Y-m-d') ?? now()->format('Y-m-d');
    }

    public function maintenance(): TechnicianMaintenance
    {
        return TechnicianMaintenance::with('items')->where('technician_id', auth()->id())->findOrFail($this->record);
    }

    public function saveDraft(): void
    {
        abort_unless(auth()->user()?->can('edit technician monthly maintenance'), 403);
        $maintenance = $this->maintenance();
        abort_if($maintenance->status !== 'draft', 422, 'Maintenance sudah dikirim.');
        DB::transaction(function () use ($maintenance): void {
            foreach ($this->results as $id => $result) {
                $item = $maintenance->items()->findOrFail($id);
                $photoPaths = $item->photo_paths ?? [];
                if (($result['photo'] ?? null) instanceof TemporaryUploadedFile) {
                    $photoPaths[] = $result['photo']->store('technician-maintenance/evidence', 'b2');
                }
                $item->update(['result' => $result['result'] ?? null, 'earned_points' => ($result['result'] ?? null) === 'pass' ? DB::raw('maximum_points') : 0, 'notes' => $result['notes'] ?? null, 'photo_paths' => $photoPaths]);
            }
            $maintenance->update(['checked_at' => $this->checkedAt ?: now()->toDateString(), 'overall_notes' => $this->overallNotes]);
            $maintenance->recalculateScore();
        });
        Notification::make()->title('Draft tersimpan')->success()->send();
    }

    public function submit(): void
    {
        $this->validate([
            'checkedAt' => ['required', 'date'],
            'results.*.result' => ['required', 'in:pass,fail,na'],
            'results.*.notes' => ['required', 'string', 'min:3'],
            'results.*.photo' => ['required', 'image', 'max:5120'],
        ], [
            'results.*.result.required' => 'Status wajib dipilih.',
            'results.*.notes.required' => 'Catatan wajib diisi.',
            'results.*.photo.required' => 'Foto wajib diunggah.',
        ]);
        $this->saveDraft();
        $maintenance = $this->maintenance();
        abort_if($maintenance->items()->whereNull('result')->exists(), 422, 'Semua checklist harus diisi.');
        $maintenance->update(['status' => 'submitted', 'submitted_at' => now()]);
        Notification::make()->title('Maintenance berhasil dikirim')->success()->send();
    }

    public function getTitle(): string
    {
        return 'Detail Maintenance';
    }
}
