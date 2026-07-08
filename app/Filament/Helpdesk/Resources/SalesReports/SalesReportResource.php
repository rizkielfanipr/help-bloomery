<?php

namespace App\Filament\Helpdesk\Resources\SalesReports;

use App\Filament\Exports\SalesReportExporter;
use App\Filament\Helpdesk\Concerns\HasPermissions;
use App\Filament\Helpdesk\Resources\SalesReports\Pages\ListSalesReports;
use App\Filament\Helpdesk\Resources\SalesReports\Pages\ViewSalesReport;
use App\Models\Branch;
use App\Models\SalesReport;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\ExportAction;
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

class SalesReportResource extends Resource
{
    use HasPermissions;

    protected static string $permissionPrefix = 'sales reports';

    protected static ?string $model = SalesReport::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Sales Report';

    protected static ?string $pluralModelLabel = 'Sales Report';

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

                TextColumn::make('modal_shift_1')
                    ->label('Modal Shift 1')
                    ->money('IDR')
                    ->sortable(),

                TextColumn::make('modal_shift_2')
                    ->label('Modal Shift 2')
                    ->money('IDR')
                    ->sortable(),

                TextColumn::make('submittedBy.name')
                    ->label('Disubmit oleh')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('updated_at')
                    ->label('Terakhir Diperbarui')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('branch_id')
                    ->label('Cabang')
                    ->options(Branch::orderBy('name')->pluck('name', 'id'))
                    ->searchable(),

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
            ->toolbarActions([
                ExportAction::make()->icon('heroicon-o-arrow-down-tray')->color('success')->iconButton()->tooltip('Export Excel')->exporter(SalesReportExporter::class),
            ])
            ->defaultSort('report_date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSalesReports::route('/'),
            'view' => ViewSalesReport::route('/{record}'),
        ];
    }
}
