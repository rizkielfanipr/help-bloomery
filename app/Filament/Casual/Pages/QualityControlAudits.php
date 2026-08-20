<?php

namespace App\Filament\Casual\Pages;

use App\Models\Branch;
use App\Models\QualityControlAudit;
use App\Models\QualityControlChecklistItem;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;

class QualityControlAudits extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static string $layout = 'filament.casual.layouts.bare';

    protected string $view = 'filament.casual.pages.quality-control-audits';

    /** Start audit form */
    public ?array $startAuditData = [];

    public function getTitle(): string|Htmlable
    {
        return 'Quality Control';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('view quality control audits'), 403);
    }

    #[Computed]
    public function draftAudits(): Collection
    {
        return QualityControlAudit::where('auditor_id', auth()->id())
            ->where('status', 'draft')
            ->latest('audit_date')
            ->latest('id')
            ->get();
    }

    public function startAuditForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('branch_id')
                    ->label('Store/Branch')
                    ->options(function (): array {
                        $query = Branch::query()->where('is_active', true)->orderBy('name');
                        $user = auth()->user();

                        if ($user && ! $user->canAccessAllBranches()) {
                            $query->whereIn('id', $user->accessibleBranchIds());
                        }

                        return $query->pluck('name', 'id')->all();
                    })
                    ->searchable()
                    ->required(),
                DatePicker::make('audit_date')
                    ->label('Tanggal Audit')
                    ->default(today())
                    ->required(),
                Select::make('audit_type')
                    ->label('Jenis Audit')
                    ->options([
                        'routine' => 'Rutin',
                        'follow_up' => 'Follow Up',
                        'surprise' => 'Surprise Audit',
                    ])
                    ->default('routine')
                    ->required(),
                TextInput::make('store_leader_name')
                    ->label('Store Leader')
                    ->maxLength(255),
                Toggle::make('store_leader_present')
                    ->label('Store Leader Hadir'),
            ])
            ->statePath('startAuditData');
    }

    public function openStartAuditModal(): void
    {
        abort_unless(auth()->user()?->can('create quality control audits'), 403);

        $this->startAuditData = [
            'audit_date' => today()->toDateString(),
            'audit_type' => 'routine',
            'store_leader_present' => false,
        ];
        $this->dispatch('open-start-audit-modal');
    }

    public function cancelStartAuditModal(): void
    {
        $this->startAuditData = [];
        $this->dispatch('close-start-audit-modal');
    }

    public function submitStartAudit(): void
    {
        abort_unless(auth()->user()?->can('create quality control audits'), 403);

        $data = $this->startAuditForm->getState();

        $audit = QualityControlAudit::create([
            ...$data,
            'auditor_id' => auth()->id(),
            'status' => 'draft',
        ]);

        $items = QualityControlChecklistItem::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        foreach ($items as $item) {
            $audit->items()->create([
                'quality_control_checklist_item_id' => $item->id,
                'section_code' => $item->section_code,
                'section_name' => $item->section_name,
                'question' => $item->question,
                'check_procedure' => $item->check_procedure,
                'maximum_points' => $item->points,
                'is_critical' => $item->is_critical,
                'requires_photo' => $item->requires_photo,
                'sort_order' => $item->sort_order,
            ]);
        }

        $this->redirect(QualityControlAuditDetail::getUrl(['record' => $audit->id], panel: 'casual'));
    }
}
