<?php

namespace App\Filament\Casual\Pages;

use App\Models\QualityControlAudit;
use App\Models\QualityControlAuditItem;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;

class QualityControlAuditDetail extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    public static function getRoutePath(Panel $panel): string
    {
        return '/'.static::getSlug($panel).'/{record}';
    }

    protected static string $layout = 'filament.casual.layouts.bare';

    protected string $view = 'filament.casual.pages.quality-control-audit-detail';

    public int $record;

    /** Item fill form */
    public ?array $itemData = [];

    public ?int $editingItemId = null;

    /** @var string[] */
    public array $photoPaths = [];

    /** Ringkasan audit form */
    public ?array $summaryData = [];

    public function getTitle(): string|Htmlable
    {
        return 'Detail Audit';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function mount(int $record): void
    {
        abort_unless(auth()->user()?->can('view quality control audits'), 403);

        QualityControlAudit::where('auditor_id', auth()->id())->findOrFail($record);

        $this->record = $record;
    }

    #[Computed]
    public function auditModel(): QualityControlAudit
    {
        return QualityControlAudit::with(['branch', 'items'])
            ->where('auditor_id', auth()->id())
            ->findOrFail($this->record);
    }

    public function itemForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('item_id'),
                Select::make('result')
                    ->label('Hasil')
                    ->options([
                        'pass' => 'Sesuai',
                        'fail' => 'Tidak Sesuai',
                        'not_applicable' => 'Tidak Berlaku',
                    ])
                    ->required(),
                Textarea::make('notes')->label('Catatan')->rows(3),
            ])
            ->statePath('itemData');
    }

    public function summaryForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('top_findings')->label('Top 3 Findings')->rows(3),
                Textarea::make('corrective_action_required')->label('Corrective Action Required')->rows(3),
                Textarea::make('overall_notes')->label('Overall Notes')->rows(3),
            ])
            ->statePath('summaryData');
    }

    public function openItemModal(int $itemId): void
    {
        $item = QualityControlAuditItem::where('quality_control_audit_id', $this->record)->findOrFail($itemId);

        $this->itemData = [
            'item_id' => $item->id,
            'result' => $item->result,
            'notes' => $item->notes,
        ];

        $this->photoPaths = $item->evidence_photos ?? [];

        $this->editingItemId = $item->id;
        $this->dispatch('open-item-modal');
    }

    public function cancelItemModal(): void
    {
        $this->itemData = [];
        $this->editingItemId = null;
        $this->photoPaths = [];
        $this->dispatch('close-item-modal');
    }

    public function storeCameraPhoto(string $base64Data): void
    {
        if (count($this->photoPaths) >= 5) {
            return;
        }

        if (! preg_match('/^data:image\/(jpeg|jpg|png|webp);base64,/', $base64Data)) {
            return;
        }

        $imageData = preg_replace('/^data:image\/\w+;base64,/', '', $base64Data);
        $decoded = base64_decode($imageData, strict: true);

        if (! $decoded) {
            return;
        }

        $path = 'quality-control/audit-evidence/'.now()->format('YmdHis').'_'.auth()->id().'_'.uniqid().'.jpg';
        Storage::disk('b2')->put($path, $decoded);

        $this->photoPaths[] = $path;
    }

    public function removeCameraPhoto(int $index): void
    {
        if (! isset($this->photoPaths[$index])) {
            return;
        }

        Storage::disk('b2')->delete($this->photoPaths[$index]);
        array_splice($this->photoPaths, $index, 1);
    }

    public function saveItem(): void
    {
        abort_unless(auth()->user()?->can('edit quality control audits'), 403);

        $data = $this->itemForm->getState();
        $itemId = $data['item_id'] ?? $this->editingItemId;

        $item = QualityControlAuditItem::where('quality_control_audit_id', $this->record)->findOrFail($itemId);

        $item->update([
            'result' => $data['result'],
            'notes' => $data['notes'] ?? null,
            'evidence_photos' => $this->photoPaths,
        ]);

        $this->dispatch('close-item-modal');
        $this->itemData = [];
        $this->editingItemId = null;
        $this->photoPaths = [];

        unset($this->auditModel);

        Notification::make()->title('Poin tersimpan!')->success()->send();
    }

    public function openSummaryModal(): void
    {
        $audit = $this->auditModel;

        $this->summaryData = [
            'top_findings' => $audit->top_findings,
            'corrective_action_required' => $audit->corrective_action_required,
            'overall_notes' => $audit->overall_notes,
        ];

        $this->dispatch('open-summary-modal');
    }

    public function saveSummary(): void
    {
        abort_unless(auth()->user()?->can('edit quality control audits'), 403);

        $data = $this->summaryForm->getState();

        $this->auditModel->update($data);

        $this->dispatch('close-summary-modal');

        unset($this->auditModel);

        Notification::make()->title('Ringkasan tersimpan!')->success()->send();
    }

    public function submitAudit(): void
    {
        abort_unless(auth()->user()?->can('edit quality control audits'), 403);

        $audit = $this->auditModel;

        $unansweredCount = $audit->items()->whereNull('result')->count();

        if ($unansweredCount > 0) {
            Notification::make()
                ->title('Audit belum dapat disubmit')
                ->body("Masih ada {$unansweredCount} poin belum diisi.")
                ->danger()
                ->send();

            return;
        }

        $audit->update([
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        $audit->recalculateScore();

        unset($this->auditModel);

        Notification::make()->title('Audit berhasil disubmit!')->success()->send();
    }
}
