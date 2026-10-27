<?php

namespace App\Filament\Helpdesk\Resources\SalesReportVoids;

use App\Actions\SalesReport\ExportSalesReportVoidsXlsxAction;
use App\Filament\Helpdesk\Resources\SalesReports\Concerns\SalesReportChildTable;
use App\Filament\Helpdesk\Resources\SalesReportVoids\Pages\ListSalesReportVoids;
use App\Models\SalesReportVoid;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Resource;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Sales Report → Void (docs/products/finance/sales-report-compliment-void-remediation/prd.md §12): every Void in
 * the user's branch scope with server-side filters, pagination, detail, and export.
 */
class SalesReportVoidResource extends Resource
{
    use SalesReportChildTable;

    protected static ?string $model = SalesReportVoid::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-receipt-refund';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    protected static ?string $slug = 'sales-reports-voids';

    protected static ?string $modelLabel = 'Void';

    protected static ?string $pluralModelLabel = 'Void';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('salesReport.report_date')->label('Tanggal')->date('d M Y'),
                TextColumn::make('salesReport.branch.name')->label('Branch')->wrap(),
                TextColumn::make('shift_number')->label('Shift')->formatStateUsing(fn (int $state): string => 'Shift '.$state),
                TextColumn::make('sales_number')->label('Sales Number')->fontFamily('mono')->weight('bold')->wrap(),
                TextColumn::make('payment_method_name')->label('Payment Method')->wrap()
                    ->description(fn (SalesReportVoid $record): ?string => $record->payment_method_source?->value === 'manual' ? 'Manual' : null),
                TextColumn::make('reason')->label('Alasan Void')->limit(60)->wrap()
                    ->tooltip(fn (SalesReportVoid $record): string => $record->reason),
                TextColumn::make('original_evidence')->label('Nota Awal')->badge()
                    ->state(fn (SalesReportVoid $record): string => filled($record->original_receipt_path) ? 'Ada' : 'Tidak ada')
                    ->color(fn (SalesReportVoid $record): string => filled($record->original_receipt_path) ? 'success' : 'danger'),
                TextColumn::make('voided_evidence')->label('Nota Setelah Void')->badge()
                    ->state(fn (SalesReportVoid $record): string => filled($record->voided_receipt_path) ? 'Ada' : 'Tidak ada')
                    ->color(fn (SalesReportVoid $record): string => filled($record->voided_receipt_path) ? 'success' : 'danger'),
                TextColumn::make('submittedBy.name')->label('Disubmit Oleh')->placeholder('-'),
                TextColumn::make('sales_number_source')->label('Verifikasi ESB')->badge(),
                TextColumn::make('created_at')->label('Waktu Submit')->dateTime('d M Y H:i', config('app.timezone'))->sortable(),
            ])
            ->filters([
                ...self::sharedFilters(),
                Filter::make('payment_method')
                    ->label('Payment Method')
                    ->schema([TextInput::make('value')->label('Payment Method')->maxLength(100)])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        filled($data['value'] ?? null),
                        fn (Builder $query): Builder => $query->where('payment_method_name', 'like', '%'.addcslashes(trim((string) $data['value']), '%_\\').'%'),
                    ))
                    ->indicateUsing(fn (array $data): ?string => filled($data['value'] ?? null) ? 'Payment Method: '.$data['value'] : null),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(['default' => 1, 'sm' => 2, 'lg' => 4])
            ->deferFilters(false)
            ->recordActions([
                Action::make('detail')
                    ->label('Detail')
                    ->icon('heroicon-o-eye')
                    ->iconButton()
                    ->tooltip('Detail')
                    ->modalHeading(fn (SalesReportVoid $record): string => 'Void — '.$record->sales_number)
                    ->modalWidth(Width::TwoExtraLarge)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalContent(fn (SalesReportVoid $record): View => view('filament.helpdesk.sales-report-children.void-detail', ['void' => $record])),
            ])
            ->toolbarActions([
                Action::make('export')
                    ->label('Export Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->tooltip('Export Excel sesuai filter aktif')
                    ->action(fn (ListSalesReportVoids $livewire): BinaryFileResponse => app(ExportSalesReportVoidsXlsxAction::class)->execute(
                        $livewire->getFilteredTableQuery(),
                        $livewire->tableFilters['report_date']['from'] ?? null,
                        $livewire->tableFilters['report_date']['until'] ?? null,
                    )),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('Tidak ada Void')
            ->emptyStateDescription('Tidak ada Void yang cocok dengan filter pada branch yang dapat Anda akses.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSalesReportVoids::route('/'),
        ];
    }
}
