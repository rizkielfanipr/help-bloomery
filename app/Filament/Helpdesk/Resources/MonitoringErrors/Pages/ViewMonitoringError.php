<?php

namespace App\Filament\Helpdesk\Resources\MonitoringErrors\Pages;

use App\Actions\Monitoring\TriageMonitoringErrorAction;
use App\Enums\MonitoringErrorStatus;
use App\Filament\Helpdesk\Resources\MonitoringErrors\MonitoringErrorResource;
use App\Models\MonitoringError;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Gate;

class ViewMonitoringError extends ViewRecord
{
    protected static string $resource = MonitoringErrorResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identitas Error')->columns(['default' => 1, 'md' => 3])->schema([
                TextEntry::make('exception_class')->label('Class')->columnSpanFull(),
                TextEntry::make('safe_message')->label('Pesan (tersanitasi)')->placeholder('-')->columnSpanFull(),
                TextEntry::make('status')->label('Status')->badge(),
                TextEntry::make('severity')->label('Severity'),
                TextEntry::make('is_regression')->label('Regresi')->formatStateUsing(fn (bool $state): string => $state ? 'Ya' : 'Tidak'),
                TextEntry::make('occurrence_count')->label('Jumlah Kejadian')->numeric(),
                TextEntry::make('first_seen_at')->label('Pertama Terlihat')->dateTime('d M Y H:i:s', 'Asia/Jakarta'),
                TextEntry::make('last_seen_at')->label('Terakhir Terlihat')->dateTime('d M Y H:i:s', 'Asia/Jakarta'),
                TextEntry::make('route_name')->label('Route')->placeholder('-'),
                TextEntry::make('action')->label('Action')->placeholder('-'),
                TextEntry::make('http_status')->label('HTTP Status')->placeholder('-'),
                TextEntry::make('last_correlation_id')->label('Correlation ID Terakhir')->copyable()->placeholder('-')->fontFamily('mono'),
                TextEntry::make('first_deploy_commit')->label('Commit Pertama')->placeholder('Tidak diketahui')->fontFamily('mono'),
                TextEntry::make('last_deploy_commit')->label('Commit Terakhir')->placeholder('Tidak diketahui')->fontFamily('mono'),
            ]),
            Section::make('Frame Aplikasi (tersanitasi)')->schema([
                TextEntry::make('metadata.frames')->label('')->listWithLineBreaks()->fontFamily('mono')->placeholder('-'),
            ])->collapsible(),
            Section::make('Triage')->columns(['default' => 1, 'md' => 3])->schema([
                TextEntry::make('triagedBy.name')->label('Oleh')->placeholder('-'),
                TextEntry::make('triaged_at')->label('Waktu')->dateTime('d M Y H:i', 'Asia/Jakarta')->placeholder('-'),
                TextEntry::make('triage_note')->label('Catatan')->placeholder('-'),
            ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('triage')
                ->label('Ubah Status Triage')
                ->icon('heroicon-o-clipboard-document-check')
                ->visible(fn (): bool => Gate::allows('triage', $this->getRecord()))
                ->fillForm(fn (): array => ['status' => $this->getRecord()->status->value])
                ->schema([
                    Select::make('status')->label('Status')->options(MonitoringErrorStatus::options())->required()->live(),
                    Textarea::make('note')->label('Catatan / Alasan')->rows(3)->maxLength(1000)
                        ->required(fn (Get $get): bool => $get('status') === MonitoringErrorStatus::Ignored->value),
                ])
                ->action(function (array $data): void {
                    /** @var MonitoringError $error */
                    $error = $this->getRecord();
                    app(TriageMonitoringErrorAction::class)->execute($error, MonitoringErrorStatus::from($data['status']), $data['note'] ?? null, auth()->user());
                    $this->refreshFormData(['status', 'triage_note', 'triaged_at']);
                    Notification::make()->title('Status triage disimpan')->success()->send();
                }),
        ];
    }
}
