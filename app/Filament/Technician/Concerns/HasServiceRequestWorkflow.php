<?php

namespace App\Filament\Technician\Concerns;

use App\Enums\ServiceRequestStatus;
use App\Models\ServiceRequest;
use App\Services\ServiceRequestWorkflow;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;

trait HasServiceRequestWorkflow
{
    /** @return array<int, Action> */
    protected function workflowActions(): array
    {
        return [
            Action::make('schedule')->label('Atur Jadwal')->icon('heroicon-o-calendar-days')
                ->visible(fn (ServiceRequest $record): bool => in_array($record->status, [ServiceRequestStatus::Submitted, ServiceRequestStatus::Scheduled, ServiceRequestStatus::ReSubmitted], true))
                ->schema([
                    DatePicker::make('scheduled_date')->label('Tanggal Pengerjaan')->required()->minDate(today()),
                    Select::make('priority')->label('Prioritas')->options(['normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'])->default('normal')->required(),
                ])->action(function (ServiceRequest $record, array $data): void {
                    app(ServiceRequestWorkflow::class)->schedule($record, auth()->user(), $data);
                    $this->record->refresh();
                }),
            Action::make('awaiting_parts')->label('Menunggu Sparepart')->icon('heroicon-o-clock')
                ->visible(fn (ServiceRequest $record): bool => $record->status === ServiceRequestStatus::InProgress)
                ->requiresConfirmation()->action(function (ServiceRequest $record): void {
                    $workflow = app(ServiceRequestWorkflow::class);
                    $workflow->authorizeTechnician($record, auth()->user());
                    $workflow->ensureStatus($record, [ServiceRequestStatus::InProgress]);
                    $record->update(['status' => ServiceRequestStatus::AwaitingParts]);
                    $this->record->refresh();
                }),
            Action::make('resume')->label('Lanjutkan Pengerjaan')->icon('heroicon-o-play')
                ->visible(fn (ServiceRequest $record): bool => $record->status === ServiceRequestStatus::AwaitingParts)
                ->action(function (ServiceRequest $record): void {
                    $workflow = app(ServiceRequestWorkflow::class);
                    $workflow->authorizeTechnician($record, auth()->user());
                    $workflow->ensureStatus($record, [ServiceRequestStatus::AwaitingParts]);
                    $record->update(['status' => ServiceRequestStatus::InProgress]);
                    $this->record->refresh();
                }),
            Action::make('outsource')->label('Perlu Outsource')->icon('heroicon-o-building-office')
                ->visible(fn (ServiceRequest $record): bool => in_array($record->status, [ServiceRequestStatus::Submitted, ServiceRequestStatus::Scheduled, ServiceRequestStatus::ReSubmitted, ServiceRequestStatus::InProgress, ServiceRequestStatus::AwaitingParts], true))
                ->schema([Textarea::make('outsource_reason')->label('Alasan dan Pekerjaan Vendor')->required()->maxLength(2000)])
                ->action(function (ServiceRequest $record, array $data): void {
                    app(ServiceRequestWorkflow::class)->outsource($record, auth()->user(), $data);
                    $this->record->refresh();
                }),
            Action::make('verify_outsource')->label('Verifikasi Report Vendor')->icon('heroicon-o-check-badge')
                ->visible(fn (ServiceRequest $record): bool => $record->status === ServiceRequestStatus::AwaitingVerification)
                ->schema([
                    Select::make('result')->label('Hasil Verifikasi')->options(['approved' => 'Disetujui', 'revision' => 'Perlu Revisi'])->required(),
                    Select::make('asset_condition')->label('Kondisi Akhir')->options(['safe' => 'Aman / Active', 'unsafe' => 'Tidak Layak / Inactive'])->required(),
                    Textarea::make('notes')->label('Catatan Verifikasi')->required()->maxLength(2000),
                ])->action(function (ServiceRequest $record, array $data): void {
                    app(ServiceRequestWorkflow::class)->verifyOutsource($record, auth()->user(), $data);
                    $this->record->refresh();
                }),
        ];
    }
}
