<?php

namespace App\Filament\Helpdesk\Resources\MonitoringIncidents;

use App\Enums\MonitoringIncidentSeverity;
use App\Enums\MonitoringIncidentStatus;
use App\Filament\Helpdesk\Resources\MonitoringIncidents\Pages\ListMonitoringIncidents;
use App\Filament\Helpdesk\Resources\MonitoringIncidents\Pages\ViewMonitoringIncident;
use App\Models\MonitoringIncident;
use App\Services\Monitoring\MonitoringContext;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Incident Center (docs/operations/monitoring/prd.md §16). Management can read; changes go through
 * the incident Actions, which authorize and write the append-only timeline.
 */
class MonitoringIncidentResource extends Resource
{
    protected static ?string $model = MonitoringIncident::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFire;

    protected static string|UnitEnum|null $navigationGroup = 'System Monitoring';

    protected static ?string $navigationLabel = 'Incidents';

    protected static ?string $modelLabel = 'Incident';

    protected static ?string $pluralModelLabel = 'Incidents';

    protected static ?string $slug = 'system-monitoring/incidents';

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('viewAny', MonitoringIncident::class) ?? false;
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
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('owner:id,name'))
            ->columns([
                TextColumn::make('number')->label('Nomor')->searchable()->fontFamily('mono'),
                TextColumn::make('title')->label('Judul')->searchable()->wrap(),
                TextColumn::make('severity')->label('Severity')->badge()->sortable(),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('owner.name')->label('PIC')->placeholder('Belum ditentukan'),
                TextColumn::make('detected_at')->label('Terdeteksi')->dateTime('d M Y H:i', 'Asia/Jakarta')->sortable(),
                TextColumn::make('resolved_at')->label('Selesai')->dateTime('d M Y H:i', 'Asia/Jakarta')->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('environment')->label('Environment')
                    ->options(fn (): array => MonitoringIncident::query()->distinct()->pluck('environment', 'environment')->put(MonitoringContext::environment(), MonitoringContext::environment())->all())
                    ->default(MonitoringContext::environment()),
                SelectFilter::make('status')->label('Status')->options(MonitoringIncidentStatus::options())->multiple(),
                SelectFilter::make('severity')->label('Severity')->options(collect(MonitoringIncidentSeverity::cases())->mapWithKeys(fn ($severity): array => [$severity->value => $severity->getLabel()])->all()),
            ])
            ->recordActions([ViewAction::make()->label('Detail')])
            ->defaultSort('detected_at', 'desc')
            ->paginated([25, 50])
            ->emptyStateHeading('Belum ada incident');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMonitoringIncidents::route('/'),
            'view' => ViewMonitoringIncident::route('/{record}'),
        ];
    }
}
