<?php

namespace App\Filament\Helpdesk\Resources\SalesReports;

use App\Enums\SalesReportStatus;
use App\Filament\Exports\SalesReportExporter;
use App\Filament\Helpdesk\Concerns\HasPermissions;
use App\Filament\Helpdesk\Resources\SalesReports\Pages\ListSalesReports;
use App\Filament\Helpdesk\Resources\SalesReports\Pages\ViewSalesReport;
use App\Models\Branch;
use App\Models\SalesReport;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
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

                TextColumn::make('shift_submissions')
                    ->label('Shift Terkirim')
                    ->state(fn (SalesReport $record): string => $record->shiftSubmissions
                        ->sortBy('shift_number')
                        ->map(fn ($submission) => 'Shift '.$submission->shift_number)
                        ->join(', ') ?: '-'),

                TextColumn::make('submitted_by')
                    ->label('Disubmit oleh')
                    ->state(fn (SalesReport $record): string => $record->shiftSubmissions
                        ->pluck('submittedBy.name')
                        ->filter()
                        ->unique()
                        ->join(', ') ?: '-'),

                TextColumn::make('employees.employee_name')
                    ->label('Staff In Charge')
                    ->listWithLineBreaks()
                    ->limitList(3)
                    ->expandableLimitedList()
                    ->placeholder('-'),

                TextColumn::make('submitted_at')
                    ->label('Disubmit')
                    ->dateTime('d M Y HH:mm')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (SalesReportStatus $state): string => $state->getLabel())
                    ->color(fn (SalesReportStatus $state): string => $state->getColor()),

                TextColumn::make('total_system')
                    ->label('Sales System')
                    ->money('IDR')
                    ->state(fn (SalesReport $record): float => $record->total_system),

                TextColumn::make('total_store')
                    ->label('Sales Store')
                    ->money('IDR')
                    ->state(fn (SalesReport $record): float => $record->total_store),

                TextColumn::make('total_settlement')
                    ->label('Settlement')
                    ->money('IDR')
                    ->placeholder('-')
                    ->state(fn (SalesReport $record): ?float => $record->status === SalesReportStatus::Completed ? $record->total_settlement : null),

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

                SelectFilter::make('status')
                    ->label('Status')
                    ->options(SalesReportStatus::class),

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
                    ->visible(fn (SalesReport $record): bool => static::canDelete($record))
                    ->requiresConfirmation()
                    ->modalHeading('Hapus Sales Report')
                    ->modalDescription('Sales report beserta detail terkait akan dihapus secara permanen.'),
            ])
            ->toolbarActions([
                ExportAction::make()->icon('heroicon-o-arrow-down-tray')->color('success')->iconButton()->tooltip('Export Excel')->exporter(SalesReportExporter::class),
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn (): bool => static::canDeleteAny())
                        ->requiresConfirmation()
                        ->modalHeading('Hapus Sales Report Terpilih')
                        ->modalDescription('Semua sales report terpilih beserta detail terkait akan dihapus secara permanen.'),
                ]),
            ])
            ->defaultSort('report_date', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['entries', 'branch', 'employees', 'reconciliations', 'shiftSubmissions.submittedBy', 'compliments.submittedBy']);
        $user = auth()->user();

        if ($user && ! $user->canAccessAllBranches()) {
            $query->whereIn('branch_id', $user->accessibleBranchIds());
        }

        return $query;
    }

    public static function canView($record): bool
    {
        $user = auth()->user();
        if (! $user?->can('view sales reports')) {
            return false;
        }

        return $user->canAccessAllBranches()
            || $user->canAccessBranch($record->branch_id);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSalesReports::route('/'),
            'view' => ViewSalesReport::route('/{record}'),
        ];
    }
}
