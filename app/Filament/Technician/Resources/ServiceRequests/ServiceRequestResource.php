<?php

namespace App\Filament\Technician\Resources\ServiceRequests;

use App\Enums\ServiceRequestStatus;
use App\Filament\Technician\Resources\ServiceRequests\Pages\ListServiceRequests;
use App\Filament\Technician\Resources\ServiceRequests\Pages\ViewServiceRequest;
use App\Models\ServiceRequest;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
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
                TextColumn::make('status')->label('Status')->badge()->sortable(),

                TextColumn::make('scheduled_date')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('technician.name')
                    ->label('Teknisi')
                    ->placeholder('Belum ditugaskan'),

                TextColumn::make('warranty_expires_at')
                    ->label('Garansi Hingga')
                    ->dateTime('d M Y')
                    ->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(ServiceRequestStatus::class),
            ])
            ->defaultSort('scheduled_date', 'asc')
            ->recordActions([
                ViewAction::make()->label('Tindak Lanjut'),
            ]);
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
