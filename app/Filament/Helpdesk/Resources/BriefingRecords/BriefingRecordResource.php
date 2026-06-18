<?php

namespace App\Filament\Helpdesk\Resources\BriefingRecords;

use App\Enums\BriefingPeriod;
use App\Enums\BriefingTaskKey;
use App\Filament\Helpdesk\Resources\BriefingRecords\Pages\ListBriefingRecords;
use App\Filament\Helpdesk\Resources\BriefingRecords\Pages\ViewBriefingRecord;
use App\Models\BriefingRecord;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\Component;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BriefingRecordResource extends Resource
{
    protected static ?string $model = BriefingRecord::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|\UnitEnum|null $navigationGroup = 'Human Resources';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Briefing Record';

    protected static ?string $pluralModelLabel = 'Daily Briefing';

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Informasi Staff')
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('user.name')->label('Nama'),
                        TextEntry::make('period')->label('Periode')->badge(),
                        TextEntry::make('record_date')->label('Tanggal')->date('d M Y'),
                        TextEntry::make('submitted_at')->label('Dikirim')->dateTime('d M Y H:i')->placeholder('-'),
                    ]),
                ]),

            Section::make('Tugas')
                ->schema(fn (BriefingRecord $record): array => self::buildTaskInfolist($record->period)),
        ]);
    }

    /** @return array<Component> */
    private static function buildTaskInfolist(BriefingPeriod $period): array
    {
        return array_map(function (BriefingTaskKey $task): Section {
            return Section::make($task->getLabel())
                ->compact()
                ->schema([
                    Grid::make(3)->schema([
                        IconEntry::make('items_completed_'.$task->value)
                            ->label('Selesai')
                            ->boolean()
                            ->state(fn (BriefingRecord $record): bool => $record->items
                                ->where('task_key', $task)
                                ->first()?->is_completed ?? false),

                        TextEntry::make('items_completed_at_'.$task->value)
                            ->label('Waktu')
                            ->state(fn (BriefingRecord $record): ?string => $record->items
                                ->where('task_key', $task)
                                ->first()?->completed_at?->format('d M Y H:i'))
                            ->placeholder('-'),

                        TextEntry::make('items_notes_'.$task->value)
                            ->label('Catatan')
                            ->state(fn (BriefingRecord $record): ?string => $record->items
                                ->where('task_key', $task)
                                ->first()?->notes)
                            ->placeholder('-'),
                    ]),

                    ImageEntry::make('items_photo_'.$task->value)
                        ->label('Foto Bukti')
                        ->disk('public')
                        ->size(200)
                        ->state(fn (BriefingRecord $record): ?string => $record->items
                            ->where('task_key', $task)
                            ->first()?->photo_path)
                        ->hidden(fn (BriefingRecord $record): bool => ! $record->items
                            ->where('task_key', $task)
                            ->first()?->photo_path),
                ]);
        }, BriefingTaskKey::forPeriod($period));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Staff')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('period')
                    ->label('Periode')
                    ->badge()
                    ->sortable(),

                TextColumn::make('record_date')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('items_count')
                    ->label('Selesai')
                    ->counts('items')
                    ->formatStateUsing(function (BriefingRecord $record): string {
                        $completed = $record->items->where('is_completed', true)->count();
                        $total = $record->items->count();

                        return "{$completed}/{$total}";
                    }),

                TextColumn::make('submitted_at')
                    ->label('Dikirim')
                    ->dateTime('d M Y H:i')
                    ->placeholder('-')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('period')
                    ->label('Periode')
                    ->options(BriefingPeriod::class),

                SelectFilter::make('user_id')
                    ->label('Staff')
                    ->options(User::role(['casual_staff', 'hr_staff'])->pluck('name', 'id'))
                    ->searchable(),
            ])
            ->defaultSort('record_date', 'desc')
            ->recordActions([
                ViewAction::make()->iconButton()->tooltip('Lihat')->color('info'),

                Action::make('approve_hr')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->iconButton()
                    ->tooltip('Approve tugas HR')
                    ->visible(fn (BriefingRecord $record): bool => $record->items
                        ->where('task_key', BriefingTaskKey::MonthlyMilestone)
                        ->first() !== null || $record->items
                        ->where('task_key', BriefingTaskKey::MonthlyKpi)
                        ->first() !== null)
                    ->action(function (BriefingRecord $record): void {
                        $record->load('items');
                        $updated = 0;

                        foreach ([BriefingTaskKey::MonthlyMilestone, BriefingTaskKey::MonthlyKpi] as $task) {
                            $item = $record->items->where('task_key', $task)->first();

                            if ($item && ! $item->is_completed) {
                                $item->update([
                                    'is_completed' => true,
                                    'completed_at' => now(),
                                    'reviewed_by' => auth()->id(),
                                    'reviewed_at' => now(),
                                ]);
                                $updated++;
                            }
                        }

                        Notification::make()
                            ->title($updated > 0 ? "Disetujui ({$updated} tugas)" : 'Sudah disetujui')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBriefingRecords::route('/'),
            'view' => ViewBriefingRecord::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user', 'items']);
    }
}
