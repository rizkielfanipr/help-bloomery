<?php

namespace App\Filament\Helpdesk\Resources\BriefingItems;

use App\Enums\BriefingPeriod;
use App\Enums\BriefingReviewStatus;
use App\Enums\BriefingTaskKey;
use App\Filament\Helpdesk\Concerns\HasPermissions;
use App\Filament\Helpdesk\Resources\BriefingItems\Pages\ListBriefingItems;
use App\Models\Branch;
use App\Models\BriefingItem;
use App\Models\User;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

class BriefingItemResource extends Resource
{
    use HasPermissions;

    protected static string $permissionPrefix = 'briefing items';

    protected static ?string $model = BriefingItem::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static string|UnitEnum|null $navigationGroup = 'Human Resources';

    protected static ?int $navigationSort = 6;

    protected static ?string $modelLabel = 'Poin Briefing';

    protected static ?string $pluralModelLabel = 'Monitoring Poin Briefing';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('staff_name')
                    ->label('Staff')
                    ->searchable(['users.name'])
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('users.name', $direction)
                    ),

                TextColumn::make('branch_name')
                    ->label('Cabang')
                    ->placeholder('-')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('branches.name', $direction)
                    ),

                TextColumn::make('task_key')
                    ->label('Tugas')
                    ->formatStateUsing(fn (BriefingTaskKey $state): string => $state->getLabel())
                    ->wrap(),

                TextColumn::make('period')
                    ->label('Periode')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => BriefingPeriod::from($state)->getLabel())
                    ->color(fn (string $state): string => BriefingPeriod::from($state)->getColor()),

                TextColumn::make('record_date')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('briefing_records.record_date', $direction)
                    ),

                TextColumn::make('completed_at')
                    ->label('Waktu Selesai')
                    ->dateTime('d M Y H:i')
                    ->placeholder('-')
                    ->sortable(),

                TextColumn::make('review_status')
                    ->label('Status Review')
                    ->badge()
                    ->placeholder('Belum Disubmit')
                    ->formatStateUsing(fn (?BriefingReviewStatus $state): string => $state?->getLabel() ?? '')
                    ->color(fn (?BriefingReviewStatus $state): string => $state?->getColor() ?? 'gray'),

            ])
            ->recordActions([
                Action::make('view_photos')
                    ->label('Lihat Foto')
                    ->icon('heroicon-o-photo')
                    ->iconButton()
                    ->tooltip('Lihat Foto & Catatan')
                    ->color('info')
                    ->modalHeading('Foto & Catatan')
                    ->registerModalActions([
                        Action::make('approve')
                            ->label('Setujui')
                            ->color('success')
                            ->icon('heroicon-o-check-circle')
                            ->requiresConfirmation()
                            ->modalHeading('Setujui Item Ini?')
                            ->modalDescription('Setelah disetujui, staff tidak dapat mengubah item ini.')
                            ->action(function (BriefingItem $record): void {
                                $record->update([
                                    'review_status' => BriefingReviewStatus::Approved->value,
                                    'reviewed_by' => auth()->id(),
                                    'reviewed_at' => now(),
                                    'rejection_reason' => null,
                                ]);
                            })
                            ->hidden(fn (BriefingItem $record): bool => $record->review_status === BriefingReviewStatus::Approved),

                        Action::make('reject')
                            ->label('Tolak')
                            ->color('danger')
                            ->icon('heroicon-o-x-circle')
                            ->form([
                                Textarea::make('rejection_reason')
                                    ->label('Alasan Penolakan')
                                    ->required()
                                    ->rows(3),
                            ])
                            ->action(function (array $data, BriefingItem $record): void {
                                $record->update([
                                    'review_status' => BriefingReviewStatus::Rejected->value,
                                    'rejection_reason' => $data['rejection_reason'],
                                    'reviewed_by' => auth()->id(),
                                    'reviewed_at' => now(),
                                ]);
                            })
                            ->hidden(fn (BriefingItem $record): bool => $record->review_status === BriefingReviewStatus::Approved),
                    ])
                    ->modalContent(fn (BriefingItem $record, Action $action): View => view(
                        'filament.helpdesk.briefing-items.photo-modal',
                        ['record' => $record, 'action' => $action],
                    ))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->visible(fn (BriefingItem $record): bool => ! empty($record->photo_paths)),
            ])
            ->filters([
                Filter::make('user_id')
                    ->label('Staff')
                    ->form([
                        Select::make('user_id')
                            ->label('Staff')
                            ->options(User::role(['casual_staff', 'hr_staff'])->pluck('name', 'id'))
                            ->searchable()
                            ->nullable(),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query->when($data['user_id'], fn (Builder $q) => $q->where('briefing_records.user_id', $data['user_id']))
                    )
                    ->indicateUsing(fn (array $data): ?string => $data['user_id'] ? 'Staff: '.User::find($data['user_id'])?->name : null
                    ),

                Filter::make('record_date')
                    ->label('Tanggal')
                    ->form([
                        DatePicker::make('from')->label('Dari'),
                        DatePicker::make('until')->label('Sampai'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'], fn (Builder $q) => $q->whereDate('briefing_records.record_date', '>=', $data['from']))
                        ->when($data['until'], fn (Builder $q) => $q->whereDate('briefing_records.record_date', '<=', $data['until']))
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

                TernaryFilter::make('is_completed')
                    ->label('Status')
                    ->trueLabel('Sudah Selesai')
                    ->falseLabel('Belum Selesai'),
            ])
            ->defaultSort('briefing_records.record_date', 'desc')
            ->toolbarActions([
                Action::make('export_briefing')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->iconButton()
                    ->tooltip('Export Excel')
                    ->form([
                        Select::make('branch_id')
                            ->label('Cabang')
                            ->options(Branch::orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->required(),

                        Select::make('period')
                            ->label('Periode')
                            ->options([
                                'daily' => 'Harian',
                                'weekly' => 'Mingguan',
                                'monthly' => 'Bulanan',
                            ])
                            ->required()
                            ->default('daily')
                            ->live(),

                        Select::make('month')
                            ->label('Bulan')
                            ->options([
                                1 => 'Januari',  2 => 'Februari', 3 => 'Maret',
                                4 => 'April',    5 => 'Mei',      6 => 'Juni',
                                7 => 'Juli',     8 => 'Agustus',  9 => 'September',
                                10 => 'Oktober', 11 => 'November', 12 => 'Desember',
                            ])
                            ->required()
                            ->default((int) now()->format('n'))
                            ->hidden(fn (Get $get): bool => $get('period') === 'monthly'),

                        TextInput::make('year')
                            ->label('Tahun')
                            ->numeric()
                            ->required()
                            ->default(now()->year)
                            ->minValue(2020)
                            ->maxValue(2099),
                    ])
                    ->modalSubmitActionLabel('Export')
                    ->action(function (array $data): void {
                        $params = array_filter([
                            'branch_id' => $data['branch_id'],
                            'period' => $data['period'],
                            'year' => $data['year'],
                            'month' => $data['period'] !== 'monthly' ? ($data['month'] ?? null) : null,
                        ], fn ($v) => $v !== null);

                        redirect()->to(route('helpdesk.exports.briefing', $params));
                    }),

                BulkActionGroup::make([
                    BulkAction::make('bulk_approve')
                        ->label('Setujui')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Setujui Item yang Dipilih?')
                        ->modalDescription('Item yang sudah disetujui sebelumnya akan dilewati.')
                        ->action(function (Collection $records): void {
                            $records
                                ->reject(fn (BriefingItem $item) => $item->review_status === BriefingReviewStatus::Approved)
                                ->each(fn (BriefingItem $item) => $item->update([
                                    'review_status' => BriefingReviewStatus::Approved->value,
                                    'reviewed_by' => auth()->id(),
                                    'reviewed_at' => now(),
                                    'rejection_reason' => null,
                                ]));
                        }),

                    BulkAction::make('bulk_reject')
                        ->label('Tolak')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->form([
                            Textarea::make('rejection_reason')
                                ->label('Alasan Penolakan')
                                ->required()
                                ->rows(3),
                        ])
                        ->action(function (array $data, Collection $records): void {
                            $records
                                ->reject(fn (BriefingItem $item) => $item->review_status === BriefingReviewStatus::Approved)
                                ->each(fn (BriefingItem $item) => $item->update([
                                    'review_status' => BriefingReviewStatus::Rejected->value,
                                    'rejection_reason' => $data['rejection_reason'],
                                    'reviewed_by' => auth()->id(),
                                    'reviewed_at' => now(),
                                ]));
                        }),

                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBriefingItems::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->join('briefing_records', 'briefing_items.briefing_record_id', '=', 'briefing_records.id')
            ->join('users', 'briefing_records.user_id', '=', 'users.id')
            ->leftJoin('branches', 'users.branch_id', '=', 'branches.id')
            ->select(
                'briefing_items.*',
                'briefing_records.period',
                'briefing_records.record_date',
                'users.name as staff_name',
                'branches.name as branch_name',
            )
            ->selectRaw('COALESCE(JSON_LENGTH(briefing_items.photo_paths), 0) as photo_count');
    }
}
