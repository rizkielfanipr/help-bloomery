<?php

namespace App\Filament\Helpdesk\Resources\SalesReportCompliments;

use App\Actions\SalesReport\ExportSalesReportComplimentsXlsxAction;
use App\Filament\Helpdesk\Resources\SalesReportCompliments\Pages\ListSalesReportCompliments;
use App\Filament\Helpdesk\Resources\SalesReports\Concerns\SalesReportChildTable;
use App\Models\ComplimentType;
use App\Models\SalesReportCompliment;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Resource;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Sales Report → Compliment (docs/sales-report-compliment-void-remediation-prd.md §11): every
 * Compliment in the user's branch scope, server-side filters and pagination, and an export of
 * exactly the filtered rows.
 */
class SalesReportComplimentResource extends Resource
{
    use SalesReportChildTable;

    protected static ?string $model = SalesReportCompliment::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-gift';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    protected static ?string $slug = 'sales-reports-compliments';

    protected static ?string $modelLabel = 'Compliment';

    protected static ?string $pluralModelLabel = 'Compliment';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('salesReport.report_date')->label('Tanggal')->date('d M Y'),
                TextColumn::make('salesReport.branch.name')->label('Branch')->wrap(),
                TextColumn::make('shift_number')->label('Shift')->formatStateUsing(fn (int $state): string => 'Shift '.$state),
                TextColumn::make('sales_number')->label('Sales Number')->fontFamily('mono')->weight('bold')
                    ->placeholder('Data lama')->wrap(),
                TextColumn::make('compliment_type_name')->label('Jenis')->wrap(),
                TextColumn::make('notes')->label('Keterangan Transaksi')->limit(60)->wrap()
                    ->tooltip(fn (SalesReportCompliment $record): string => $record->notes),
                TextColumn::make('submittedBy.name')->label('Disubmit Oleh')->placeholder('-'),
                TextColumn::make('evidence_count')->label('Bukti')
                    ->state(fn (SalesReportCompliment $record): string => count($record->attachment_paths ?? []).' file'),
                TextColumn::make('source')->label('Verifikasi ESB')->badge()
                    ->state(fn (SalesReportCompliment $record): string => $record->effectiveSalesNumberSource()->getLabel())
                    ->color(fn (SalesReportCompliment $record): string => $record->effectiveSalesNumberSource()->getColor()),
                TextColumn::make('created_at')->label('Waktu Submit')->dateTime('d M Y H:i', config('app.timezone'))->sortable(),
            ])
            ->filters([
                ...self::sharedFilters(),
                SelectFilter::make('compliment_type_id')
                    ->label('Jenis Compliment')
                    ->options(fn (): array => ComplimentType::query()->orderBy('sort_order')->orderBy('name')->pluck('name', 'id')->all()),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(['default' => 1, 'sm' => 2, 'lg' => 4])
            ->deferFilters(false)
            ->recordActions([
                Action::make('detail')
                    ->label('Detail')
                    ->icon('heroicon-o-eye')
                    ->iconButton()
                    ->tooltip('Detail')
                    ->modalHeading(fn (SalesReportCompliment $record): string => 'Compliment — '.($record->sales_number ?? 'Data lama'))
                    ->modalWidth(Width::TwoExtraLarge)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalContent(fn (SalesReportCompliment $record): View => view('filament.helpdesk.sales-report-children.compliment-detail', ['compliment' => $record])),
            ])
            ->toolbarActions([
                Action::make('export')
                    ->label('Export Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->tooltip('Export Excel sesuai filter aktif')
                    ->action(fn (ListSalesReportCompliments $livewire): BinaryFileResponse => app(ExportSalesReportComplimentsXlsxAction::class)->execute(
                        $livewire->getFilteredTableQuery(),
                        $livewire->tableFilters['report_date']['from'] ?? null,
                        $livewire->tableFilters['report_date']['until'] ?? null,
                    )),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('Tidak ada Compliment')
            ->emptyStateDescription('Tidak ada Compliment yang cocok dengan filter pada branch yang dapat Anda akses.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSalesReportCompliments::route('/'),
        ];
    }
}
