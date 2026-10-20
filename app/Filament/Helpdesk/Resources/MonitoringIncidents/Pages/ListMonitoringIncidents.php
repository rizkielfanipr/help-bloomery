<?php

namespace App\Filament\Helpdesk\Resources\MonitoringIncidents\Pages;

use App\Actions\Monitoring\CreateMonitoringIncidentAction;
use App\Enums\MonitoringIncidentSeverity;
use App\Filament\Helpdesk\Resources\MonitoringIncidents\MonitoringIncidentResource;
use App\Models\ErpRepairRequest;
use App\Models\MonitoringIncident;
use App\Models\MonitoringService;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Gate;

class ListMonitoringIncidents extends ListRecords
{
    protected static string $resource = MonitoringIncidentResource::class;

    protected ?string $subheading = 'Open → Acknowledged → Investigating → Monitoring → Resolved. Incident tidak mengubah status transaksi bisnis.';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createIncident')
                ->label('Buat Incident')
                ->icon('heroicon-o-plus')
                ->visible(fn (): bool => Gate::allows('create', MonitoringIncident::class))
                ->schema([
                    TextInput::make('title')->label('Judul')->required()->maxLength(255),
                    Select::make('severity')->label('Severity')->options(MonitoringIncidentSeverity::options())->required(),
                    Select::make('affected_services')->label('Layanan Terdampak')->multiple()
                        ->options(fn (): array => MonitoringService::query()->forCurrentEnvironment()->orderBy('name')->pluck('name', 'key')->all()),
                    DateTimePicker::make('detected_at')->label('Terdeteksi')->default(now())->seconds(false),
                    Select::make('erp_repair_request_id')->label('Permintaan IT Terkait')->searchable()
                        ->getSearchResultsUsing(fn (string $search): array => ErpRepairRequest::query()->where('ticket_number', 'like', "%{$search}%")->limit(20)->pluck('ticket_number', 'id')->all())
                        ->getOptionLabelUsing(fn ($value): ?string => ErpRepairRequest::query()->whereKey($value)->value('ticket_number')),
                    Textarea::make('summary')->label('Ringkasan')->rows(3)->maxLength(5000),
                ])
                ->action(function (array $data): void {
                    $incident = app(CreateMonitoringIncidentAction::class)->execute($data, auth()->user());
                    $this->redirect(MonitoringIncidentResource::getUrl('view', ['record' => $incident]));
                }),
        ];
    }
}
