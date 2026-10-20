<?php

namespace App\Filament\Helpdesk\Resources\MonitoringDeployments;

use App\Enums\MonitoringDeploymentStatus;
use App\Filament\Helpdesk\Resources\MonitoringDeployments\Pages\ListMonitoringDeployments;
use App\Filament\Helpdesk\Resources\MonitoringDeployments\Pages\ViewMonitoringDeployment;
use App\Models\MonitoringDeployment;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Deployment Monitor (docs/system-monitoring-prd.md §14): read-only; there is intentionally no
 * deploy, redeploy, or rollback action.
 */
class MonitoringDeploymentResource extends Resource
{
    protected static ?string $model = MonitoringDeployment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRocketLaunch;

    protected static string|UnitEnum|null $navigationGroup = 'System Monitoring';

    protected static ?string $navigationLabel = 'Deployments';

    protected static ?string $modelLabel = 'Deployment';

    protected static ?string $pluralModelLabel = 'Deployments';

    protected static ?string $slug = 'system-monitoring/deployments';

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('viewAny', MonitoringDeployment::class) ?? false;
    }

    public static function canView(Model $record): bool
    {
        return auth()->user()?->can('view', $record) ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Deployment')->columns(['default' => 1, 'md' => 3])->schema([
                TextEntry::make('environment')->label('Environment'),
                TextEntry::make('status')->label('Status')->badge(),
                TextEntry::make('stage')->label('Stage')->placeholder('-'),
                TextEntry::make('branch')->label('Branch')->placeholder('-'),
                TextEntry::make('commit_sha')->label('Commit')->fontFamily('mono')->copyable()->placeholder('Tidak diketahui'),
                TextEntry::make('actor')->label('Actor')->placeholder('-'),
                TextEntry::make('commit_message')->label('Pesan Commit')->placeholder('-')->columnSpanFull(),
                TextEntry::make('provider')->label('Provider'),
                TextEntry::make('run_id')->label('Run ID')->fontFamily('mono'),
                TextEntry::make('duration_seconds')->label('Durasi')->formatStateUsing(fn (?int $state): string => $state === null ? '-' : gmdate('i:s', $state).' menit')->placeholder('Berjalan'),
                TextEntry::make('started_at')->label('Mulai')->dateTime('d M Y H:i:s', 'Asia/Jakarta')->placeholder('-'),
                TextEntry::make('finished_at')->label('Selesai')->dateTime('d M Y H:i:s', 'Asia/Jakarta')->placeholder('-'),
                TextEntry::make('migration_status')->label('Migration')->placeholder('Tidak dilaporkan'),
                TextEntry::make('health_status')->label('Post-deploy Health')->placeholder('Tidak dilaporkan'),
                TextEntry::make('metadata.working_tree_dirty')->label('Working Tree Server')->formatStateUsing(fn ($state): string => $state ? 'Ada perubahan lokal' : 'Bersih')->placeholder('Tidak dilaporkan'),
                TextEntry::make('failure_summary')->label('Ringkasan Kegagalan')->placeholder('-')->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('started_at')->label('Mulai')->dateTime('d M Y H:i', 'Asia/Jakarta')->sortable(),
                TextColumn::make('environment')->label('Environment')->badge()->color('gray'),
                TextColumn::make('commit_sha')->label('Commit')->formatStateUsing(fn (?string $state): string => $state ? substr($state, 0, 7) : '-')->fontFamily('mono')
                    ->description(fn (MonitoringDeployment $record): ?string => str((string) $record->commit_message)->limit(60)->toString() ?: null)->searchable(),
                TextColumn::make('branch')->label('Branch')->placeholder('-'),
                TextColumn::make('status')->label('Status')->badge()->description(fn (MonitoringDeployment $record): ?string => $record->isLongRunning() ? 'Melebihi batas durasi' : $record->stage),
                TextColumn::make('migration_status')->label('Migration')->placeholder('-'),
                TextColumn::make('health_status')->label('Health')->placeholder('-'),
                TextColumn::make('duration_seconds')->label('Durasi')->formatStateUsing(fn (?int $state): string => $state === null ? '-' : gmdate('i:s', $state))->alignEnd(),
            ])
            ->filters([
                SelectFilter::make('environment')->label('Environment')->options(fn (): array => MonitoringDeployment::query()->distinct()->pluck('environment', 'environment')->all()),
                SelectFilter::make('status')->label('Status')->options(MonitoringDeploymentStatus::options()),
            ])
            ->recordActions([ViewAction::make()->label('Detail')])
            ->defaultSort('started_at', 'desc')
            ->paginated([25, 50])
            ->emptyStateHeading('Not Configured')
            ->emptyStateDescription('Belum ada metadata deployment. Deploy script dapat menjalankan `php artisan monitoring:record-deployment` atau mengirim callback bertanda tangan bila MONITORING_DEPLOYMENT_SECRET dikonfigurasi.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMonitoringDeployments::route('/'),
            'view' => ViewMonitoringDeployment::route('/{record}'),
        ];
    }
}
