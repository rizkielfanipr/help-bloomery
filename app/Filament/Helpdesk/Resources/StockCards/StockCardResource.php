<?php

namespace App\Filament\Helpdesk\Resources\StockCards;

use App\Filament\Helpdesk\Concerns\HasPermissions;
use App\Filament\Helpdesk\Resources\StockCards\Pages\ListStockCards;
use App\Filament\Helpdesk\Resources\StockCards\Pages\ViewStockCard;
use App\Models\Branch;
use App\Models\StockCard;
use BackedEnum;
use Carbon\Carbon;
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

                TextColumn::make('flag_unit')
                    ->label('Unit')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('entries_count')
                    ->label('Item')
                    ->counts('entries')
                    ->sortable(),

                TextColumn::make('submitted_at')
                    ->label('Disubmit')
                    ->dateTime('d M Y HH:mm')
                    ->sortable()
                    ->placeholder('Belum disubmit'),

                TextColumn::make('submittedBy.name')
                    ->label('Oleh')
                    ->sortable()
                    ->searchable()
                    ->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('branch_id')
                    ->label('Cabang')
                    ->options(Branch::orderBy('name')->pluck('name', 'id'))
                    ->searchable(),

                Filter::make('submitted')
                    ->label('Sudah Disubmit')
                    ->query(fn (Builder $q) => $q->whereNotNull('submitted_at')),

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
                ViewAction::make(),
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
        $query = parent::getEloquentQuery()->with(['branch', 'submittedBy']);
        $user = auth()->user();

        if ($user && ! $user->access_all_branches && ! $user->hasRole('SUPERADMIN')) {
            $query->whereIn('branch_id', $user->accessibleBranchIds());
        }

        return $query;
    }

    public static function canView($record): bool
    {
        $user = auth()->user();

        return parent::canView($record)
            && ($user?->hasRole('SUPERADMIN') || $user?->canAccessBranch($record->branch_id));
    }
}
