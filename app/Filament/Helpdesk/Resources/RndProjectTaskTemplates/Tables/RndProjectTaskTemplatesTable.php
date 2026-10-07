<?php

namespace App\Filament\Helpdesk\Resources\RndProjectTaskTemplates\Tables;

use App\Models\RndProjectTaskTemplate;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RndProjectTaskTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount(['checkpoints', 'applications']))
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Template')
                    ->searchable()
                    ->sortable()
                    ->description(fn (RndProjectTaskTemplate $record): ?string => str($record->description)->limit(70)->toString() ?: null),
                TextColumn::make('checkpoints_count')->label('Checkpoint')->alignCenter()->sortable(),
                TextColumn::make('applications_count')->label('Diterapkan')->suffix('x')->alignCenter()->sortable(),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
                TextColumn::make('updated_at')->label('Terakhir Diubah')->since()->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Status Aktif'),
            ])
            ->defaultSort('name')
            ->emptyStateHeading('Belum ada Template Checkpoint')
            ->emptyStateDescription('Buat template untuk mempercepat pembuatan rangkaian Task Project.')
            ->recordActions([
                EditAction::make()->iconButton()->tooltip('Edit'),
                static::toggleActiveAction(),
                DeleteAction::make()->iconButton()->tooltip('Hapus')->requiresConfirmation(),
            ]);
    }

    /**
     * Applied templates are deactivated instead of deleted (§12.9); Task snapshots stay intact.
     */
    private static function toggleActiveAction(): Action
    {
        return Action::make('toggleActive')
            ->iconButton()
            ->icon(fn (RndProjectTaskTemplate $record): string => $record->is_active ? 'heroicon-o-pause-circle' : 'heroicon-o-play-circle')
            ->color(fn (RndProjectTaskTemplate $record): string => $record->is_active ? 'warning' : 'success')
            ->tooltip(fn (RndProjectTaskTemplate $record): string => $record->is_active ? 'Nonaktifkan' : 'Aktifkan')
            ->authorize(fn (RndProjectTaskTemplate $record): bool => auth()->user()?->can('update', $record) ?? false)
            ->requiresConfirmation(fn (RndProjectTaskTemplate $record): bool => $record->is_active)
            ->modalHeading('Nonaktifkan template?')
            ->modalDescription('Template tidak dapat diterapkan lagi. Task yang sudah dibuat dari template ini tidak berubah.')
            ->action(function (RndProjectTaskTemplate $record): void {
                $record->update(['is_active' => ! $record->is_active]);

                Notification::make()
                    ->title($record->is_active ? 'Template diaktifkan' : 'Template dinonaktifkan')
                    ->success()
                    ->send();
            });
    }
}
