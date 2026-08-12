<?php

namespace App\Filament\Helpdesk\Resources\StockCards;

use App\Enums\StockCardStatus;
use App\Filament\Helpdesk\Concerns\HasPermissions;
use App\Filament\Helpdesk\Resources\StockCards\Pages\ListStockCards;
use App\Filament\Helpdesk\Resources\StockCards\Pages\ViewStockCard;
use App\Models\Branch;
use App\Models\StockCard;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class StockCardResource extends Resource
{
    use HasPermissions;

    protected static string $permissionPrefix = 'stock cards';

    protected static ?string $model = StockCard::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Stock Card';

    protected static ?string $pluralModelLabel = 'Stock Card';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('report_date')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('branch.name')
                    ->label('Cabang')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('entries_count')
                    ->label('Item')
                    ->counts('entries')
                    ->sortable(),

                TextColumn::make('total_variance_items')
                    ->label('Selisih')
                    ->state(fn (StockCard $record): int => $record->total_variance_items)
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'warning' : 'gray'),

                TextColumn::make('submittedBy.name')
                    ->label('Disubmit Oleh')
                    ->sortable()
                    ->searchable()
                    ->placeholder('-'),

                TextColumn::make('employees.employee_name')
                    ->label('Staff In Charge')
                    ->listWithLineBreaks()
                    ->limitList(3)
                    ->expandableLimitedList()
                    ->placeholder('-'),

                TextColumn::make('submitted_at')
                    ->label('Disubmit')
                    ->dateTime('d M Y HH:mm')
                    ->sortable()
                    ->placeholder('Belum disubmit'),

                TextColumn::make('revision_number')
                    ->label('Revisi')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (int $state): ?string => $state > 0 ? "Rev. {$state}" : null)
                    ->placeholder('-')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('branch_id')
                    ->label('Cabang')
                    ->options(Branch::orderBy('name')->pluck('name', 'id'))
                    ->searchable(),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options(StockCardStatus::class),

                Filter::make('report_date')
                    ->form([
                        DatePicker::make('from')->label('Dari'),
                        DatePicker::make('until')->label('Sampai'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'], fn (Builder $q) => $q->whereDate('report_date', '>=', $data['from']))
                        ->when($data['until'], fn (Builder $q) => $q->whereDate('report_date', '<=', $data['until']))
                    )
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from'] ?? null) {
                            $indicators[] = 'Dari: '.Carbon::parse($data['from'])->isoFormat('D MMM Y');
                        }
                        if ($data['until'] ?? null) {
                            $indicators[] = 'Sampai: '.Carbon::parse($data['until'])->isoFormat('D MMM Y');
                        }

                        return $indicators;
                    }),
            ])
            ->recordActions([
                ViewAction::make()
                    ->iconButton()
                    ->tooltip('Lihat'),
                DeleteAction::make()
                    ->iconButton()
                    ->tooltip('Hapus')
                    ->visible(fn (StockCard $record): bool => static::canDelete($record))
                    ->requiresConfirmation()
                    ->modalHeading('Hapus Stock Card')
                    ->modalDescription('Stock card beserta detail terkait akan dihapus secara permanen.'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn (): bool => static::canDeleteAny())
                        ->requiresConfirmation()
                        ->modalHeading('Hapus Stock Card Terpilih')
                        ->modalDescription('Semua stock card terpilih beserta detail terkait akan dihapus secara permanen.'),
                ]),
            ])
            ->defaultSort('report_date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStockCards::route('/'),
            'view' => ViewStockCard::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['branch', 'submittedBy', 'supervisorReviewer', 'financeReviewer', 'entries', 'employees']);
        $user = auth()->user();

        if ($user && ! $user->canAccessAllBranches()) {
            $query->whereIn('branch_id', $user->accessibleBranchIds());
        }

        return $query;
    }

    public static function canView($record): bool
    {
        $user = auth()->user();

        return parent::canView($record)
            && $user?->canAccessBranch($record->branch_id);
    }
}
