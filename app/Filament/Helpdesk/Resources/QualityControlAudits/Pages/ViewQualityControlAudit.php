<?php

namespace App\Filament\Helpdesk\Resources\QualityControlAudits\Pages;

use App\Filament\Helpdesk\Resources\QualityControlAudits\QualityControlAuditResource;
use App\Models\QualityControlAuditItem;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;

class ViewQualityControlAudit extends ViewRecord
{
    protected static string $resource = QualityControlAuditResource::class;

    protected string $view = 'filament.helpdesk.quality-control-audits.view';

    protected Width|string|null $maxContentWidth = Width::Full;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->record->load(['branch', 'auditor', 'items.actionPic']);
    }

    public function editItemAction(): Action
    {
        return Action::make('editItem')
            ->label('Edit Poin')
            ->icon('heroicon-o-pencil-square')
            ->modalHeading(fn (QualityControlAuditItem $record): string => 'Edit Poin '.$record->section_code)
            ->modalDescription(fn (QualityControlAuditItem $record): string => $record->question)
            ->modalWidth(Width::Large)
            ->record(function (array $arguments): QualityControlAuditItem {
                abort_unless(QualityControlAuditResource::canEdit($this->getRecord()), 403);

                return $this->getRecord()->items()->findOrFail($arguments['item']);
            })
            ->schema([
                TextInput::make('earned_points')
                    ->label('Poin')
                    ->numeric()
                    ->integer()
                    ->minValue(0)
                    ->maxValue(fn (QualityControlAuditItem $record): int => $record->maximum_points)
                    ->helperText(fn (QualityControlAuditItem $record): string => "Skor maksimal: {$record->maximum_points} poin")
                    ->required(),
                Textarea::make('notes')
                    ->label('Catatan')
                    ->rows(3)
                    ->maxLength(5000),
                FileUpload::make('evidence_photos')
                    ->label('Foto Bukti')
                    ->image()
                    ->multiple()
                    ->maxFiles(5)
                    ->maxSize(10240)
                    ->disk('b2')
                    ->directory('quality-control/audit-evidence'),
            ])
            ->action(function (array $data, QualityControlAuditItem $record): void {
                abort_unless(QualityControlAuditResource::canEdit($this->getRecord()), 403);

                $record->update([
                    ...$data,
                    'result' => 'scored',
                ]);

                $this->record->refresh()->load(['branch', 'auditor', 'items.actionPic']);

                Notification::make()
                    ->title('Poin audit berhasil diperbarui')
                    ->success()
                    ->send();
            })
            ->visible(fn (): bool => QualityControlAuditResource::canEdit($this->getRecord()));
    }
}
