<?php

namespace App\Filament\Helpdesk\Resources\StoreSalesOrders;

use App\Enums\StoreSalesOrderEventType;
use App\Enums\StoreSalesOrderStatus;
use App\Filament\Helpdesk\Resources\StoreSalesOrders\Pages\ListStoreSalesOrders;
use App\Filament\Helpdesk\Resources\StoreSalesOrders\Pages\ViewStoreSalesOrder;
use App\Models\Branch;
use App\Models\StoreSalesOrder;
use App\Models\User;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * docs/store-sales-order-prd.md §13, §16. Every action is gated by StoreSalesOrderPolicy
 * (auto-discovered for the StoreSalesOrder model), never by role name. Orders are only ever
 * created from the Casual app's Store Sales Order tile (StoreSalesOrderPage), so this Resource has
 * no create route — edit, status change, and Refresh from ESB are header actions on the View page.
 */
class StoreSalesOrderResource extends Resource
{
    protected static ?string $model = StoreSalesOrder::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Operational';

    protected static ?string $navigationLabel = 'Store Sales Orders';

    protected static ?string $modelLabel = 'Store Sales Order';

    protected static ?string $pluralModelLabel = 'Store Sales Orders';

    protected static ?string $slug = 'store-sales-orders';

    public static function canViewAny(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $user?->can('viewAny', StoreSalesOrder::class) ?? false;
    }

    public static function canView(Model $record): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $user?->can('view', $record) ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $user?->can('update', $record) ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $user?->can('delete', $record) ?? false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product_sales_number')
                    ->label('SALES ORDER NUMBER')
                    ->searchable()
                    ->copyable()
                    ->weight('semibold'),

                TextColumn::make('required_date')
                    ->label('REQUIRED DATE')
                    ->date('d M Y')
                    ->placeholder('-')
                    ->sortable(),

                TextColumn::make('customer_name_snapshot')
                    ->label('CUSTOMER')
                    ->placeholder('-')
                    ->searchable(),

                TextColumn::make('branch.name')
                    ->label('BRANCH')
                    ->sortable(),

                TextColumn::make('event_type')
                    ->label('EVENT TYPE')
                    ->badge()
                    ->placeholder('-')
                    ->formatStateUsing(fn (?StoreSalesOrderEventType $state) => $state?->getLabel()),

                TextColumn::make('items_summary')
                    ->label('PRODUCT SUMMARY')
                    ->state(fn (StoreSalesOrder $record): string => $record->items
                        ->map(fn ($item) => $item->product_type->getLabel().' x'.rtrim(rtrim((string) $item->quantity, '0'), '.'))
                        ->implode(', ') ?: '-')
                    ->wrap(),

                TextColumn::make('esb_status_name')
                    ->label('ESB STATUS')
                    ->placeholder('-'),

                TextColumn::make('operational_status')
                    ->label('OPERATIONAL STATUS')
                    ->badge()
                    ->sortable()
                    ->formatStateUsing(fn (StoreSalesOrderStatus $state) => $state->getLabel())
                    ->color(fn (StoreSalesOrderStatus $state) => $state->getColor()),

                TextColumn::make('submitter.name')
                    ->label('SUBMITTED BY')
                    ->placeholder('-'),
            ])
            ->searchPlaceholder('Cari nomor SO, nama customer, Order By, nomor HP, atau catatan...')
            ->filters([
                Filter::make('search')
                    ->form([TextInput::make('value')->label('Pencarian')])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(filled($data['value'] ?? null), function (Builder $query) use ($data): Builder {
                            $term = '%'.trim((string) $data['value']).'%';

                            return $query->where(function (Builder $query) use ($term): Builder {
                                return $query->where('product_sales_number', 'like', $term)
                                    ->orWhere('customer_name_snapshot', 'like', $term)
                                    ->orWhere('ordered_by', 'like', $term)
                                    ->orWhere('phone_number', 'like', $term)
                                    ->orWhere('preparation_notes', 'like', $term);
                            });
                        })),

                SelectFilter::make('branch_id')
                    ->label('BRANCH')
                    ->options(fn (): array => Branch::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable(),

                SelectFilter::make('operational_status')
                    ->label('OPERATIONAL STATUS')
                    ->options(StoreSalesOrderStatus::class),

                SelectFilter::make('esb_status_name')
                    ->label('ESB STATUS')
                    ->options(fn (): array => StoreSalesOrder::query()
                        ->whereNotNull('esb_status_name')
                        ->distinct()
                        ->orderBy('esb_status_name')
                        ->pluck('esb_status_name', 'esb_status_name')
                        ->all()),

                SelectFilter::make('event_type')
                    ->label('EVENT TYPE')
                    ->options(StoreSalesOrderEventType::class),

                Filter::make('required_date')
                    ->form([
                        DatePicker::make('from')->label('Dari Tanggal'),
                        DatePicker::make('until')->label('Sampai Tanggal'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('required_date', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('required_date', '<=', $date))),
            ], layout: FiltersLayout::AboveContent)
            ->deferFilters(false)
            ->recordActions([
                ViewAction::make()->iconButton()->tooltip('Lihat Detail'),
                DeleteAction::make()->iconButton()->tooltip('Hapus'),
            ])
            // Simplified from the PRD's "required date terdekat yang belum terminal, lalu data
            // terbaru" compound rule: a plain ascending required_date keeps column-click sorting
            // correct (CanSortRecords applies the clicked column's ->orderBy() *before* a
            // defaultSort fallback only when they differ — a compound default baked into
            // getEloquentQuery() via orderByRaw() would instead run first in the generated SQL and
            // silently override every explicit column sort, including the user clicking this very
            // column).
            ->defaultSort('required_date', 'asc')
            ->defaultPaginationPageOption(10)
            ->paginationPageOptions([10, 20]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStoreSalesOrders::route('/'),
            'view' => ViewStoreSalesOrder::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['branch', 'submitter', 'items']);

        $user = auth()->user();

        // Branch scope is the primary query-level guard (§16 "query scope dan Policy menjadi
        // pengaman utama"); the submitter-always-visible exception mirrors
        // StoreSalesOrderPolicy::view() so a reviewer's own submitted orders never disappear from
        // the index just because they fall outside their branch scope.
        if ($user && ! $user->canAccessAllBranches()) {
            $query->where(function (Builder $query) use ($user): Builder {
                return $query->whereIn('branch_id', $user->accessibleBranchIds())
                    ->orWhere('submitted_by', $user->id);
            });
        }

        return $query;
    }
}
