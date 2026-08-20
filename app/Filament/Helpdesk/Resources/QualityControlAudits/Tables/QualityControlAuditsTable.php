<?php

namespace App\Filament\Helpdesk\Resources\QualityControlAudits\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class QualityControlAuditsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('audit_number')->label('Nomor Audit')->searchable()->copyable(),
                TextColumn::make('audit_date')->label('Tanggal')->date('d M Y')->sortable(),
                TextColumn::make('branch.name')->label('Store')->searchable()->sortable(),
                TextColumn::make('auditor.name')->label('Auditor')->searchable(),
                TextColumn::make('score')->label('Nilai')->suffix('%')->numeric(2)->sortable(),
                TextColumn::make('rating')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'green' => 'Green',
                        'yellow' => 'Yellow',
                        'red' => 'Red',
                        default => 'Belum Dinilai',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'green' => 'success',
                        'yellow' => 'warning',
                        'red' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('status')->label('Audit')->badge()->formatStateUsing(fn (string $state): string => $state === 'submitted' ? 'Submitted' : 'Draft'),
            ])
            ->filters([
                SelectFilter::make('branch_id')->label('Store')->relationship('branch', 'name')->searchable()->preload(),
                SelectFilter::make('rating')->label('Status Nilai')->options(['green' => 'Green', 'yellow' => 'Yellow', 'red' => 'Red']),
                Filter::make('audit_date')->form([
                    DatePicker::make('from')->label('Dari'),
                    DatePicker::make('until')->label('Sampai'),
                ])->query(fn (Builder $query, array $data): Builder => $query
                    ->when($data['from'], fn (Builder $query, $date): Builder => $query->whereDate('audit_date', '>=', $date))
                    ->when($data['until'], fn (Builder $query, $date): Builder => $query->whereDate('audit_date', '<=', $date))),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('audit_date', 'desc');
    }
}
