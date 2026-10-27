<?php

namespace App\Filament\Helpdesk\Resources\MonitoringTransactions;

use App\Enums\MonitoringTransactionStatus;
use App\Filament\Helpdesk\Resources\MonitoringTransactions\Pages\ListMonitoringTransactions;
use App\Filament\Helpdesk\Resources\MonitoringTransactions\Pages\ViewMonitoringTransaction;
use App\Models\Branch;
use App\Models\MonitoringTransaction;
use App\Models\User;
use App\Services\Monitoring\MonitoringContext;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * ESB Transaction Monitor (docs/products/system-monitoring/prd.md §11, §21.2). The index only selects
 * metadata columns; sanitized payload summaries are loaded on the detail page for users with
 * `view monitoring sanitized payloads`.
 */
class MonitoringTransactionResource extends Resource
{
    protected static ?string $model = MonitoringTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = 'System Monitoring';

    protected static ?string $navigationLabel = 'ESB Transactions';

    protected static ?string $modelLabel = 'Transaksi ESB';

    protected static ?string $pluralModelLabel = 'Transaksi ESB';

    protected static ?string $slug = 'system-monitoring/esb-transactions';

    protected static ?string $recordTitleAttribute = 'uuid';

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('viewAny', MonitoringTransaction::class) ?? false;
    }

    public static function canView(Model $record): bool
    {
        return $record instanceof MonitoringTransaction && (auth()->user()?->can('view', $record) ?? false);
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

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();
        $query = parent::getEloquentQuery()->parents();

        return $user instanceof User ? $query->visibleTo($user) : $query->whereRaw('1 = 0');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->select(MonitoringTransaction::INDEX_COLUMNS)
                ->with(['actor:id,name', 'branch:id,name']))
            ->columns([
                TextColumn::make('started_at')->label('Waktu')->dateTime('d M Y H:i:s', 'Asia/Jakarta')->sortable()
                    ->description(fn (MonitoringTransaction $record): string => $record->started_at?->diffForHumans() ?? ''),
                TextColumn::make('source_module')->label('Modul / Aksi')
                    ->formatStateUsing(fn (MonitoringTransaction $record): string => $record->moduleLabel())
                    ->description(fn (MonitoringTransaction $record): string => str($record->source_action)->headline()->toString()),
                TextColumn::make('operation_type')->label('Operasi')->formatStateUsing(fn (string $state): string => str($state)->headline()->toString()),
                TextColumn::make('company_code')->label('Company / Branch')->placeholder('-')
                    ->description(fn (MonitoringTransaction $record): ?string => $record->branch?->name),
                TextColumn::make('external_reference')->label('Referensi')->placeholder('-')->searchable()
                    ->description(fn (MonitoringTransaction $record): string => 'Corr '.substr((string) $record->correlation_id, 0, 8)),
                TextColumn::make('actor.name')->label('Actor')->placeholder('System'),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('duration_ms')->label('Durasi')->alignEnd()->sortable()
                    ->formatStateUsing(fn (?int $state): string => $state === null ? '-' : number_format($state / 1000, 2).' s'),
            ])
            ->searchable(['external_reference', 'correlation_id', 'uuid'])
            ->filters([
                Filter::make('started_at')
                    ->schema([
                        DatePicker::make('from')->label('Dari Tanggal'),
                        DatePicker::make('until')->label('Sampai Tanggal'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->where('started_at', '>=', $date.' 00:00:00'))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->where('started_at', '<=', $date.' 23:59:59'))),
                SelectFilter::make('environment')->label('Environment')
                    ->options(fn (): array => self::environmentOptions())
                    ->default(MonitoringContext::environment()),
                SelectFilter::make('source_module')->label('Modul')->options(MonitoringTransaction::moduleOptions()),
                SelectFilter::make('operation_type')->label('Operasi')->options(fn (): array => self::operationOptions()),
                SelectFilter::make('status')->label('Status')->options(MonitoringTransactionStatus::options())->multiple(),
                SelectFilter::make('company_code')->label('Company Code')->options(fn (): array => self::companyOptions()),
                SelectFilter::make('branch_id')->label('Branch')->options(fn (): array => Branch::query()->orderBy('name')->pluck('name', 'id')->all())->searchable(),
                SelectFilter::make('actor_id')->label('Actor')->relationship('actor', 'name')->searchable(),
            ])
            ->recordActions([
                ViewAction::make()->label('Detail'),
            ])
            ->defaultSort('started_at', 'desc')
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('Belum ada transaksi ESB tercatat')
            ->emptyStateDescription('Transaksi muncul setelah mutation ESB dari UI aplikasi dijalankan.');
    }

    /** @return array<string, string> */
    private static function environmentOptions(): array
    {
        return MonitoringTransaction::query()->parents()->distinct()->orderBy('environment')->pluck('environment', 'environment')
            ->put(MonitoringContext::environment(), MonitoringContext::environment())
            ->all();
    }

    /** @return array<string, string> */
    private static function operationOptions(): array
    {
        return MonitoringTransaction::query()->parents()->distinct()->orderBy('operation_type')->pluck('operation_type')
            ->mapWithKeys(fn (string $operation): array => [$operation => str($operation)->headline()->toString()])
            ->all();
    }

    /** @return array<string, string> */
    private static function companyOptions(): array
    {
        return MonitoringTransaction::query()->whereNotNull('company_code')->distinct()->orderBy('company_code')->pluck('company_code', 'company_code')->all();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMonitoringTransactions::route('/'),
            'view' => ViewMonitoringTransaction::route('/{record}'),
        ];
    }
}
