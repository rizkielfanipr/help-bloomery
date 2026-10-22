<?php

namespace App\Filament\Helpdesk\Resources\SalesReports\Concerns;

use App\Enums\SalesReportDataSource;
use App\Enums\SalesReportStatus;
use App\Models\Branch;
use App\Services\SalesReport\SalesReportAccess;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Shared scope and filters of the Compliment and Void reports. Every query starts from the
 * branch scope of SalesReportAccess; filters only narrow it.
 */
trait SalesReportChildTable
{
    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view sales reports') ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return app(SalesReportAccess::class)->scopeChildren(
            parent::getEloquentQuery()->with(['salesReport:id,branch_id,report_date,status', 'salesReport.branch:id,name', 'submittedBy:id,name']),
            auth()->user(),
        );
    }

    /** @return list<Filter|SelectFilter> */
    protected static function sharedFilters(): array
    {
        return [
            Filter::make('report_date')
                ->label('Tanggal Laporan')
                ->schema([
                    DatePicker::make('from')->label('Dari Tanggal'),
                    DatePicker::make('until')->label('Sampai Tanggal')->afterOrEqual('from'),
                ])
                ->columns(2)
                ->query(fn (Builder $query, array $data): Builder => $query
                    ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereHas('salesReport', fn (Builder $reports): Builder => $reports->whereDate('report_date', '>=', $date)))
                    ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereHas('salesReport', fn (Builder $reports): Builder => $reports->whereDate('report_date', '<=', $date))))
                ->indicateUsing(fn (array $data): array => array_values(array_filter([
                    ($data['from'] ?? null) ? 'Dari: '.Carbon::parse($data['from'])->isoFormat('D MMM Y') : null,
                    ($data['until'] ?? null) ? 'Sampai: '.Carbon::parse($data['until'])->isoFormat('D MMM Y') : null,
                ]))),
            SelectFilter::make('branch')
                ->label('Branch')
                ->searchable()
                ->options(fn (): array => self::branchOptions())
                ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                    ? $query->whereHas('salesReport', fn (Builder $reports): Builder => $reports->where('branch_id', (int) $data['value']))
                    : $query),
            SelectFilter::make('shift_number')
                ->label('Shift')
                ->options(fn (): array => collect(range(1, max(2, (int) Branch::query()->max('sales_shift_count'))))
                    ->mapWithKeys(fn (int $shift): array => [$shift => 'Shift '.$shift])->all()),
            Filter::make('sales_number')
                ->label('Sales Number')
                ->schema([TextInput::make('value')->label('Sales Number')->maxLength(50)])
                ->query(fn (Builder $query, array $data): Builder => $query->when(
                    filled($data['value'] ?? null),
                    fn (Builder $query): Builder => $query->where('sales_number', 'like', '%'.addcslashes(trim((string) $data['value']), '%_\\').'%'),
                ))
                ->indicateUsing(fn (array $data): ?string => filled($data['value'] ?? null) ? 'Sales Number: '.$data['value'] : null),
            SelectFilter::make('source')
                ->label('Sumber Sales Number')
                ->options(SalesReportDataSource::options())
                ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                    null, '' => $query,
                    SalesReportDataSource::Legacy->value => $query->where(fn (Builder $query): Builder => $query->whereNull('sales_number_source')->orWhere('sales_number_source', SalesReportDataSource::Legacy->value)),
                    default => $query->where('sales_number_source', $data['value']),
                }),
            SelectFilter::make('report_status')
                ->label('Status Sales Report')
                ->options(SalesReportStatus::class)
                ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                    ? $query->whereHas('salesReport', fn (Builder $reports): Builder => $reports->where('status', $data['value']))
                    : $query),
        ];
    }

    /** @return array<int, string> */
    private static function branchOptions(): array
    {
        $user = auth()->user();

        return Branch::query()
            ->when($user && ! $user->canAccessAllBranches(), fn (Builder $query): Builder => $query->whereIn('id', $user->accessibleBranchIds()))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
