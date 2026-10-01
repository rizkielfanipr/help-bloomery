<?php

namespace App\Filament\Helpdesk\Resources\CustomerComplaints;

use App\Enums\CustomerComplaintCategory;
use App\Enums\CustomerComplaintSource;
use App\Enums\CustomerComplaintStatus;
use App\Filament\Helpdesk\Resources\CustomerComplaints\Pages\ListCustomerComplaints;
use App\Filament\Helpdesk\Resources\CustomerComplaints\Pages\ViewCustomerComplaint;
use App\Models\Branch;
use App\Models\CustomerComplaint;
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
 * docs/customer-complaints-prd.md §10, §12. Every action is gated by CustomerComplaintPolicy
 * (auto-discovered for the CustomerComplaint model), never by role name. Complaints are only ever
 * created from the Casual app's Form Komplain (CustomerComplaintPage), so this Resource has no
 * create/edit route — Follow-up (status/PIC/notes/resolution) is a header action on the View page.
 */
class CustomerComplaintResource extends Resource
{
    protected static ?string $model = CustomerComplaint::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|UnitEnum|null $navigationGroup = 'Operational';

    protected static ?string $navigationLabel = 'Customer Complaints';

    protected static ?string $modelLabel = 'Customer Complaint';

    protected static ?string $pluralModelLabel = 'Customer Complaints';

    protected static ?string $slug = 'customer-complaints';

    public static function canViewAny(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $user?->can('viewAny', CustomerComplaint::class) ?? false;
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
                TextColumn::make('complaint_number')
                    ->label('COMPLAINT NUMBER')
                    ->searchable()
                    ->copyable()
                    ->weight('semibold'),

                TextColumn::make('occurred_at')
                    ->label('COMPLAINT DATE')
                    ->dateTime('d M Y H:i')
                    ->sortable(),

                TextColumn::make('branch.name')
                    ->label('BRANCH')
                    ->sortable(),

                TextColumn::make('category')
                    ->label('CATEGORY')
                    ->badge()
                    ->formatStateUsing(fn (CustomerComplaintCategory $state) => $state->getLabel()),

                TextColumn::make('source')
                    ->label('SOURCE')
                    ->badge()
                    ->formatStateUsing(fn (CustomerComplaintSource $state) => $state->getLabel()),

                TextColumn::make('customer_name')
                    ->label('CUSTOMER')
                    ->placeholder('-')
                    ->searchable(),

                TextColumn::make('status')
                    ->label('STATUS')
                    ->badge()
                    ->sortable()
                    ->formatStateUsing(fn (CustomerComplaintStatus $state) => $state->getLabel())
                    ->color(fn (CustomerComplaintStatus $state) => $state->getColor()),

                TextColumn::make('submitter.name')
                    ->label('SUBMITTED BY')
                    ->placeholder('-'),
            ])
            ->searchPlaceholder('Cari nomor komplain, order reference, nama customer, atau detail...')
            ->filters([
                Filter::make('search')
                    ->form([TextInput::make('value')->label('Pencarian')])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(filled($data['value'] ?? null), function (Builder $query) use ($data): Builder {
                            $term = '%'.trim((string) $data['value']).'%';

                            return $query->where(function (Builder $query) use ($term): Builder {
                                return $query->where('complaint_number', 'like', $term)
                                    ->orWhere('order_reference', 'like', $term)
                                    ->orWhere('customer_name', 'like', $term)
                                    ->orWhere('description', 'like', $term);
                            });
                        })),

                SelectFilter::make('branch_id')
                    ->label('BRANCH')
                    ->options(fn (): array => Branch::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable(),

                SelectFilter::make('category')
                    ->label('CATEGORY')
                    ->options(CustomerComplaintCategory::class),

                SelectFilter::make('source')
                    ->label('SOURCE')
                    ->options(CustomerComplaintSource::class),

                SelectFilter::make('status')
                    ->label('STATUS')
                    ->options(CustomerComplaintStatus::class),

                Filter::make('occurred_at')
                    ->form([
                        DatePicker::make('from')->label('Dari Tanggal'),
                        DatePicker::make('until')->label('Sampai Tanggal'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('occurred_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('occurred_at', '<=', $date))),
            ], layout: FiltersLayout::AboveContent)
            ->deferFilters(false)
            ->recordActions([
                ViewAction::make()->iconButton()->tooltip('Lihat Detail'),
                DeleteAction::make()->iconButton()->tooltip('Hapus'),
            ])
            ->defaultSort('occurred_at', 'desc')
            ->defaultPaginationPageOption(10)
            ->paginationPageOptions([10, 20]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomerComplaints::route('/'),
            'view' => ViewCustomerComplaint::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['branch', 'submitter', 'assignee']);

        $user = auth()->user();

        // Branch scope is the primary query-level guard (§12 "query scope dan Policy menjadi
        // pengaman utama, bukan visibility UI"); the submitter-always-visible exception mirrors
        // CustomerComplaintPolicy::view() so a reviewer's own submitted complaints never
        // disappear from the index just because they fall outside their branch scope.
        if ($user && ! $user->canAccessAllBranches()) {
            $query->where(function (Builder $query) use ($user): Builder {
                return $query->whereIn('branch_id', $user->accessibleBranchIds())
                    ->orWhere('submitted_by', $user->id);
            });
        }

        return $query;
    }
}
