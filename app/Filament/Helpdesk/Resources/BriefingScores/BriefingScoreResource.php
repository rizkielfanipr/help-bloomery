<?php

namespace App\Filament\Helpdesk\Resources\BriefingScores;

use App\Filament\Helpdesk\Concerns\HasPermissions;
use App\Filament\Helpdesk\Resources\BriefingScores\Pages\ListBriefingScores;
use App\Models\Branch;
use App\Models\BriefingScore;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class BriefingScoreResource extends Resource
{
    use HasPermissions;

    protected static string $permissionPrefix = 'briefing scores';

    protected static ?string $model = BriefingScore::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-star';

    protected static string|UnitEnum|null $navigationGroup = 'Human Resources';

    protected static ?int $navigationSort = 7;

    protected static ?string $modelLabel = 'Nilai Briefing';

    protected static ?string $pluralModelLabel = 'Nilai Briefing';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('branch.name')
                    ->label('Branch')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('month_label')
                    ->label('Periode')
                    ->getStateUsing(fn (BriefingScore $record): string => $record->monthLabel())
                    ->sortable(query: fn (Builder $query, string $direction) => $query
                        ->orderBy('year', $direction)
                        ->orderBy('month', $direction)),

                TextColumn::make('score')
                    ->label('Nilai')
                    ->formatStateUsing(fn (float $state): string => number_format($state, 2).'%')
                    ->badge()
                    ->color(fn (BriefingScore $record): string => $record->isPassing() ? 'success' : 'danger')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->getStateUsing(fn (BriefingScore $record): string => $record->isPassing() ? 'Achieve' : 'Tidak Achieve')
                    ->badge()
                    ->color(fn (BriefingScore $record): string => $record->isPassing() ? 'success' : 'danger'),

                TextColumn::make('computed_at')
                    ->label('Dihitung')
                    ->dateTime('d M Y H:i')
                    ->placeholder('-')
                    ->sortable(),
            ])
            ->defaultSort('year', 'desc')
            ->filters([
                Filter::make('branch_id')
                    ->label('Branch')
                    ->form([
                        Select::make('branch_id')
                            ->label('Branch')
                            ->placeholder('Semua Branch')
                            ->options(Branch::orderBy('name')->pluck('name', 'id')),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['branch_id'], fn ($q) => $q->where('branch_id', $data['branch_id']))
                    ),

                Filter::make('period')
                    ->label('Periode')
                    ->form([
                        Select::make('year')
                            ->label('Tahun')
                            ->options(fn () => collect(range(now()->year, 2024))->mapWithKeys(fn ($y) => [$y => $y]))
                            ->default(now()->year),
                        Select::make('month')
                            ->label('Bulan')
                            ->placeholder('Semua Bulan')
                            ->options([
                                1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                                5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                                9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
                            ]),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['year'], fn ($q) => $q->where('year', $data['year']))
                        ->when($data['month'], fn ($q) => $q->where('month', $data['month']))
                    ),
            ])
            ->recordActions([
                Action::make('breakdown')
                    ->label('Detail')
                    ->icon('heroicon-o-magnifying-glass')
                    ->iconButton()
                    ->tooltip('Lihat Detail Penilaian')
                    ->modalWidth(Width::FiveExtraLarge)
                    ->stickyModalHeader()
                    ->stickyModalFooter()
                    ->modalHeading(fn (BriefingScore $record): string => 'Detail Nilai — '.$record->branch->name.' ('.$record->monthLabel().')')
                    ->modalContent(fn (BriefingScore $record) => view('filament.helpdesk.briefing-scores.breakdown-modal', ['record' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup'),
            ])
            ->toolbarActions([
                Action::make('compute')
                    ->label('Hitung Ulang')
                    ->icon('heroicon-o-calculator')
                    ->color('warning')
                    ->form([
                        Select::make('year')
                            ->label('Tahun')
                            ->options(fn () => collect(range(now()->year, 2024))->mapWithKeys(fn ($y) => [$y => $y]))
                            ->default(now()->year)
                            ->required(),
                        Select::make('month')
                            ->label('Bulan')
                            ->options([
                                1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                                5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                                9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
                            ])
                            ->default(now()->subMonth()->month)
                            ->required(),
                        Select::make('branch_id')
                            ->label('Branch (opsional)')
                            ->placeholder('Semua Branch')
                            ->options(Branch::orderBy('name')->pluck('name', 'id'))
                            ->nullable(),
                    ])
                    ->modalSubmitActionLabel('Hitung')
                    ->action(function (array $data): void {
                        $cmd = 'briefing:compute-scores --year='.$data['year'].' --month='.$data['month'];
                        if ($data['branch_id'] ?? null) {
                            $cmd .= ' --branch='.$data['branch_id'];
                        }
                        \Artisan::call($cmd);

                        Notification::make()
                            ->title('Nilai berhasil dihitung.')
                            ->success()
                            ->send();
                    }),

                Action::make('export_excel')
                    ->label('Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->form([
                        Select::make('year')
                            ->label('Tahun')
                            ->options(fn () => collect(range(now()->year, 2024))->mapWithKeys(fn ($y) => [$y => $y]))
                            ->default(now()->year)
                            ->required(),
                        Select::make('month')
                            ->label('Bulan')
                            ->placeholder('Semua Bulan')
                            ->options([
                                1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                                5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                                9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
                            ])
                            ->nullable(),
                        Select::make('branch_id')
                            ->label('Branch')
                            ->placeholder('Semua Branch')
                            ->options(Branch::orderBy('name')->pluck('name', 'id'))
                            ->nullable(),
                    ])
                    ->modalSubmitActionLabel('Export Excel')
                    ->action(function (array $data): void {
                        redirect()->to(route('helpdesk.exports.briefing-scores', array_filter([
                            'format' => 'excel',
                            'year' => $data['year'],
                            'month' => $data['month'] ?? null,
                            'branch_id' => $data['branch_id'] ?? null,
                        ])));
                    }),

                Action::make('export_pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document-text')
                    ->color('danger')
                    ->form([
                        Select::make('year')
                            ->label('Tahun')
                            ->options(fn () => collect(range(now()->year, 2024))->mapWithKeys(fn ($y) => [$y => $y]))
                            ->default(now()->year)
                            ->required(),
                        Select::make('month')
                            ->label('Bulan')
                            ->placeholder('Semua Bulan')
                            ->options([
                                1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                                5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                                9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
                            ])
                            ->nullable(),
                        Select::make('branch_id')
                            ->label('Branch')
                            ->placeholder('Semua Branch')
                            ->options(Branch::orderBy('name')->pluck('name', 'id'))
                            ->nullable(),
                    ])
                    ->modalSubmitActionLabel('Export PDF')
                    ->action(function (array $data): void {
                        redirect()->to(route('helpdesk.exports.briefing-scores', array_filter([
                            'format' => 'pdf',
                            'year' => $data['year'],
                            'month' => $data['month'] ?? null,
                            'branch_id' => $data['branch_id'] ?? null,
                        ])));
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBriefingScores::route('/'),
        ];
    }
}
