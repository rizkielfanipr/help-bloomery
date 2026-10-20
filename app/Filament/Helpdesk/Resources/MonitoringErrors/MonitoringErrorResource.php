<?php

namespace App\Filament\Helpdesk\Resources\MonitoringErrors;

use App\Enums\MonitoringErrorStatus;
use App\Filament\Helpdesk\Resources\MonitoringErrors\Pages\ListMonitoringErrors;
use App\Filament\Helpdesk\Resources\MonitoringErrors\Pages\ViewMonitoringError;
use App\Models\MonitoringError;
use App\Services\Monitoring\MonitoringContext;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Error Monitor (docs/system-monitoring-prd.md §13). Stack frames are only shown on the detail
 * page to users with technical access.
 */
class MonitoringErrorResource extends Resource
{
    protected static ?string $model = MonitoringError::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBugAnt;

    protected static string|UnitEnum|null $navigationGroup = 'System Monitoring';

    protected static ?string $navigationLabel = 'Errors';

    protected static ?string $modelLabel = 'Error';

    protected static ?string $pluralModelLabel = 'Errors';

    protected static ?string $slug = 'system-monitoring/errors';

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('viewAny', MonitoringError::class) ?? false;
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

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->select(MonitoringError::INDEX_COLUMNS))
            ->columns([
                TextColumn::make('last_seen_at')->label('Terakhir')->dateTime('d M Y H:i', 'Asia/Jakarta')->sortable()
                    ->description(fn (MonitoringError $record): string => $record->last_seen_at->diffForHumans()),
                TextColumn::make('exception_class')->label('Error')->formatStateUsing(fn (string $state): string => class_basename($state))
                    ->description(fn (MonitoringError $record): string => str((string) $record->safe_message)->limit(90)->toString())->wrap()->searchable(['exception_class', 'safe_message']),
                TextColumn::make('route_name')->label('Route / Modul')->placeholder('-')->description(fn (MonitoringError $record): ?string => $record->module)->wrap(),
                TextColumn::make('occurrence_count')->label('Kejadian')->numeric()->sortable()->alignEnd(),
                TextColumn::make('status')->label('Status')->badge(),
                IconColumn::make('is_regression')->label('Regresi')->boolean()->trueIcon('heroicon-o-arrow-uturn-up')->falseIcon('heroicon-o-minus'),
                TextColumn::make('last_deploy_commit')->label('Commit')->formatStateUsing(fn (?string $state): string => $state ? substr($state, 0, 7) : '-')->fontFamily('mono'),
            ])
            ->filters([
                SelectFilter::make('environment')->label('Environment')
                    ->options(fn (): array => MonitoringError::query()->distinct()->pluck('environment', 'environment')->put(MonitoringContext::environment(), MonitoringContext::environment())->all())
                    ->default(MonitoringContext::environment()),
                SelectFilter::make('status')->label('Status')->options(MonitoringErrorStatus::options())->multiple(),
                SelectFilter::make('source')->label('Sumber')->options(['backend' => 'Backend', 'browser' => 'Browser']),
                Filter::make('last_24h')->label('24 jam terakhir')->query(fn (Builder $query): Builder => $query->where('last_seen_at', '>=', now()->subDay())),
            ])
            ->recordActions([ViewAction::make()->label('Detail')])
            ->defaultSort('last_seen_at', 'desc')
            ->paginated([25, 50, 100])
            ->emptyStateHeading('Belum ada error tercatat');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMonitoringErrors::route('/'),
            'view' => ViewMonitoringError::route('/{record}'),
        ];
    }
}
