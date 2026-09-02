<?php

namespace App\Filament\Technician\Resources\ServiceRequests;

use App\Enums\ServiceRequestStatus;
use App\Filament\Technician\Resources\ServiceRequests\Pages\ListServiceRequests;
use App\Filament\Technician\Resources\ServiceRequests\Pages\ViewServiceRequest;
use App\Models\ServiceRequest;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ServiceRequestResource extends Resource
{
    protected static ?string $model = ServiceRequest::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationLabel = 'Pekerjaan Saya';

    protected static ?string $modelLabel = 'Pekerjaan';

    protected static ?string $pluralModelLabel = 'Pekerjaan Saya';

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view service requests') ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('KODE')
                    ->searchable()
                    ->copyable()
                    ->weight('semibold'),

                TextColumn::make('scheduled_date')
                    ->label('TANGGAL')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('technician.name')
                    ->label('TEKNISI')
                    ->placeholder('Belum ditugaskan')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->label('STATUS')
                    ->badge()
                    ->sortable()
                    ->formatStateUsing(fn (ServiceRequestStatus $state) => $state->getLabel())
                    ->color(fn (ServiceRequestStatus $state) => $state->getColor()),

                TextColumn::make('warranty_expires_at')
                    ->label('GARANSI HINGGA')
                    ->dateTime('d M Y')
                    ->placeholder('-'),
            ])
            ->filters([
                Filter::make('code')
                    ->form([TextInput::make('value')])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(filled($data['value'] ?? null), fn (Builder $query): Builder => $query
                            ->where('code', 'like', '%'.trim((string) $data['value']).'%'))),

                Filter::make('scheduled_date')
                    ->form([
                        DatePicker::make('from'),
                        DatePicker::make('until'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('scheduled_date', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('scheduled_date', '<=', $date))),

                SelectFilter::make('status')
                    ->label('STATUS')
                    ->options(ServiceRequestStatus::class)
                    ->multiple(),
            ], layout: FiltersLayout::AboveContent)
            ->deferFilters(false)
            ->defaultSort('scheduled_date', 'asc')
            ->recordActions([
                ViewAction::make()->iconButton()->tooltip('Tindak Lanjut'),
            ])
            ->defaultPaginationPageOption(10)
            ->paginationPageOptions([10, 25, 50, 100]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListServiceRequests::route('/'),
            'view' => ViewServiceRequest::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['technician', 'repairs.technician']);

        if (auth()->user()?->canAccessAllBranches()) {
            return $query;
        }

        // Show unassigned jobs + jobs assigned to this technician
        return $query->where(function (Builder $q): void {
            $q->whereNull('technician_id')
                ->orWhere('technician_id', auth()->id());
        });
    }
}
