<?php

namespace App\Filament\Helpdesk\Resources\MonitoringIncidents\Pages;

use App\Actions\Monitoring\AddMonitoringIncidentNoteAction;
use App\Actions\Monitoring\AssignMonitoringIncidentOwnerAction;
use App\Actions\Monitoring\LinkMonitoringIncidentAction;
use App\Actions\Monitoring\TransitionMonitoringIncidentAction;
use App\Actions\Monitoring\UpdateMonitoringIncidentAnalysisAction;
use App\Enums\MonitoringIncidentSeverity;
use App\Enums\MonitoringIncidentStatus;
use App\Filament\Helpdesk\Resources\MonitoringDeployments\MonitoringDeploymentResource;
use App\Filament\Helpdesk\Resources\MonitoringErrors\MonitoringErrorResource;
use App\Filament\Helpdesk\Resources\MonitoringIncidents\MonitoringIncidentResource;
use App\Filament\Helpdesk\Resources\MonitoringTransactions\MonitoringTransactionResource;
use App\Models\ErpRepairRequest;
use App\Models\MonitoringIncident;
use App\Models\MonitoringIncidentLink;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Gate;

class ViewMonitoringIncident extends ViewRecord
{
    protected static string $resource = MonitoringIncidentResource::class;

    protected string $view = 'filament.helpdesk.monitoring.incident-view';

    protected Width|string|null $maxContentWidth = Width::Full;

    public function mount(int|string $record): void
    {
        parent::mount($record);
        $this->loadIncident();
    }

    public function getTitle(): string
    {
        return 'Detail Incident';
    }

    public function incident(): MonitoringIncident
    {
        /** @var MonitoringIncident $record */
        $record = $this->getRecord();

        return $record;
    }

    public function canManage(): bool
    {
        return Gate::allows('manage', $this->incident());
    }

    /** @return list<array{id: int, type: string, label: string, url: ?string, missing: bool}> */
    public function linkedRecords(): array
    {
        return $this->incident()->links->map(function (MonitoringIncidentLink $link): array {
            $record = $link->linkedRecord();
            $url = match (true) {
                $record === null => null,
                $link->linkable_type === 'transaction' && Gate::allows('view', $record) => MonitoringTransactionResource::getUrl('view', ['record' => $record]),
                $link->linkable_type === 'error' && Gate::allows('view', $record) => MonitoringErrorResource::getUrl('view', ['record' => $record]),
                $link->linkable_type === 'deployment' && Gate::allows('view', $record) => MonitoringDeploymentResource::getUrl('view', ['record' => $record]),
                default => null,
            };

            return ['id' => $link->id, 'type' => $link->linkable_type, 'label' => (string) ($link->label ?: '#'.$link->linkable_id), 'url' => $url, 'missing' => $record === null];
        })->all();
    }

    public function unlink(int $linkId): void
    {
        $incident = $this->incident();
        $link = $incident->links()->findOrFail($linkId);
        app(LinkMonitoringIncidentAction::class)->unlink($incident, $link, auth()->user());
        $this->loadIncident();
        Notification::make()->title('Tautan dihapus')->success()->send();
    }

    protected function getHeaderActions(): array
    {
        $transitions = collect([
            MonitoringIncidentStatus::Acknowledged->value => ['Acknowledge', 'heroicon-o-hand-raised'],
            MonitoringIncidentStatus::Investigating->value => ['Mulai Investigasi', 'heroicon-o-magnifying-glass'],
            MonitoringIncidentStatus::Monitoring->value => ['Masuk Monitoring', 'heroicon-o-eye'],
            MonitoringIncidentStatus::Resolved->value => ['Resolve', 'heroicon-o-check-circle'],
            MonitoringIncidentStatus::Open->value => ['Buka Ulang', 'heroicon-o-arrow-uturn-left'],
        ])->map(fn (array $meta, string $status): Action => $this->transitionAction(MonitoringIncidentStatus::from($status), $meta[0], $meta[1]))->values()->all();

        return [
            ...$transitions,
            ActionGroup::make([
                Action::make('assignOwner')->label('Tetapkan PIC')->icon('heroicon-o-user')
                    ->visible(fn (): bool => $this->canManage())
                    ->schema([Select::make('owner_id')->label('PIC')->required()->searchable()
                        ->options(fn (): array => User::permission('manage monitoring incidents')->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all()
                            + User::role('SUPERADMIN')->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())])
                    ->action(function (array $data): void {
                        app(AssignMonitoringIncidentOwnerAction::class)->execute($this->incident(), User::query()->findOrFail($data['owner_id']), auth()->user());
                        $this->afterChange('PIC ditetapkan');
                    }),
                Action::make('addNote')->label('Tambah Catatan Timeline')->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->visible(fn (): bool => $this->canManage())
                    ->schema([Textarea::make('note')->label('Catatan')->required()->minLength(3)->maxLength(5000)->rows(4)])
                    ->action(function (array $data): void {
                        app(AddMonitoringIncidentNoteAction::class)->execute($this->incident(), $data['note'], auth()->user());
                        $this->afterChange('Catatan ditambahkan');
                    }),
                Action::make('linkRecord')->label('Tautkan Record')->icon('heroicon-o-link')
                    ->visible(fn (): bool => $this->canManage())
                    ->schema([
                        Select::make('type')->label('Jenis')->required()->options(['transaction' => 'Transaksi ESB (UUID)', 'error' => 'Error (UUID)', 'deployment' => 'Deployment (UUID)', 'alert' => 'Alert (ID)']),
                        TextInput::make('reference')->label('UUID / ID')->required()->maxLength(64),
                    ])
                    ->action(function (array $data): void {
                        app(LinkMonitoringIncidentAction::class)->link($this->incident(), $data['type'], trim($data['reference']), auth()->user());
                        $this->afterChange('Record ditautkan');
                    }),
                Action::make('analysis')->label('Analisis & Permintaan IT')->icon('heroicon-o-document-magnifying-glass')
                    ->visible(fn (): bool => $this->canManage())
                    ->fillForm(fn (): array => $this->incident()->only(['severity', 'root_cause', 'resolution', 'preventive_action', 'erp_repair_request_id']) + ['severity' => $this->incident()->severity->value])
                    ->schema([
                        Select::make('severity')->label('Severity')->options(MonitoringIncidentSeverity::options())->required(),
                        Textarea::make('root_cause')->label('Root Cause')->rows(3)->maxLength(5000),
                        Textarea::make('resolution')->label('Resolution')->rows(3)->maxLength(5000),
                        Textarea::make('preventive_action')->label('Preventive Action')->rows(3)->maxLength(5000),
                        Select::make('erp_repair_request_id')->label('Permintaan IT Terkait')->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => ErpRepairRequest::query()->where('ticket_number', 'like', "%{$search}%")->limit(20)->pluck('ticket_number', 'id')->all())
                            ->getOptionLabelUsing(fn ($value): ?string => ErpRepairRequest::query()->whereKey($value)->value('ticket_number')),
                    ])
                    ->action(function (array $data): void {
                        app(UpdateMonitoringIncidentAnalysisAction::class)->execute($this->incident(), $data, auth()->user());
                        $this->afterChange('Analisis disimpan');
                    }),
            ])->label('Kelola')->icon('heroicon-o-ellipsis-vertical')->button(),
        ];
    }

    private function transitionAction(MonitoringIncidentStatus $target, string $label, string $icon): Action
    {
        $schema = match ($target) {
            MonitoringIncidentStatus::Resolved => [
                Textarea::make('resolution')->label('Resolution')->required()->rows(3)->maxLength(5000),
                Textarea::make('root_cause')->label('Root Cause')->rows(3)->maxLength(5000),
                Textarea::make('preventive_action')->label('Preventive Action')->rows(3)->maxLength(5000),
            ],
            MonitoringIncidentStatus::Open => [Textarea::make('note')->label('Alasan Buka Ulang')->required()->rows(3)->maxLength(2000)],
            default => [Textarea::make('note')->label('Catatan (opsional)')->rows(2)->maxLength(2000)],
        };

        return Action::make('transition_'.$target->value)
            ->label($label)
            ->icon($icon)
            ->color($target === MonitoringIncidentStatus::Resolved ? 'success' : 'gray')
            ->visible(fn (): bool => $this->canManage() && $this->incident()->status->canTransitionTo($target))
            ->fillForm(fn (): array => $target === MonitoringIncidentStatus::Resolved ? $this->incident()->only(['resolution', 'root_cause', 'preventive_action']) : [])
            ->schema($schema)
            ->action(function (array $data) use ($target): void {
                app(TransitionMonitoringIncidentAction::class)->execute($this->incident(), $target, auth()->user(), $data);
                $this->afterChange('Status incident diperbarui');
            });
    }

    private function afterChange(string $message): void
    {
        $this->record = $this->incident()->fresh();
        $this->loadIncident();
        Notification::make()->title($message)->success()->send();
    }

    private function loadIncident(): void
    {
        $this->record->load(['owner:id,name', 'creator:id,name', 'erpRepairRequest:id,ticket_number', 'events.actor:id,name', 'links', 'alerts:id,monitoring_incident_id,title,severity,status,opened_at,resolved_at']);
    }
}
