<?php

namespace App\Filament\Helpdesk\Resources\BriefingTasks;

use App\Enums\BriefingPeriod;
use App\Enums\BriefingSubmissionType;
use App\Filament\Exports\BriefingTaskExporter;
use App\Filament\Helpdesk\Concerns\HasPermissions;
use App\Filament\Helpdesk\Resources\BriefingTasks\Pages\CreateBriefingTask;
use App\Filament\Helpdesk\Resources\BriefingTasks\Pages\EditBriefingTask;
use App\Filament\Helpdesk\Resources\BriefingTasks\Pages\ListBriefingTasks;
use App\Models\Branch;
use App\Models\BriefingTask;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class BriefingTaskResource extends Resource
{
    use HasPermissions;

    protected static string $permissionPrefix = 'briefing records';

    protected static ?string $model = BriefingTask::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-list-bullet';

    protected static string|UnitEnum|null $navigationGroup = 'Human Resources';

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'Poin Briefing';

    protected static ?string $pluralModelLabel = 'Kelola Poin Briefing';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Informasi Poin')->schema([
                Select::make('branch_id')
                    ->label('Branch')
                    ->placeholder('Semua Branch (Global)')
                    ->options(fn () => Branch::where('is_active', true)->pluck('name', 'id'))
                    ->default(fn () => request()->integer('branch_id') ?: null)
                    ->nullable()
                    ->searchable()
                    ->helperText('Kosongkan agar poin ini berlaku untuk semua branch.'),

                Grid::make(2)->schema([
                    TextInput::make('label')
                        ->label('Nama Poin')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('key')
                        ->label('Key (unik)')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->alphaDash()
                        ->maxLength(100)
                        ->helperText('Hanya huruf kecil, angka, dan underscore. Tidak bisa diubah setelah data tersimpan.'),
                ]),

                Grid::make(2)->schema([
                    Select::make('period')
                        ->label('Periode')
                        ->options(BriefingPeriod::class)
                        ->required()
                        ->live(),

                    Select::make('submission_type')
                        ->label('Jenis Input')
                        ->options(BriefingSubmissionType::class)
                        ->required()
                        ->helperText('Menentukan apa yang user harus isi saat submit poin ini.'),
                ]),

                Grid::make(2)->schema([
                    TextInput::make('note_type')
                        ->label('Keterangan Singkat')
                        ->maxLength(100)
                        ->helperText('Teks kecil di bawah nama poin, misal: "Foto Selfie Briefing".'),

                    TextInput::make('sort_order')
                        ->label('Urutan')
                        ->numeric()
                        ->default(0),
                ]),

                Grid::make(2)->schema([
                    TextInput::make('group')
                        ->label('Grup (key)')
                        ->maxLength(100)
                        ->nullable()
                        ->default(fn () => request()->get('group'))
                        ->helperText('Hanya huruf kecil, angka, underscore. Misal: general_cleaning.'),

                    TextInput::make('group_label')
                        ->label('Nama Grup')
                        ->maxLength(100)
                        ->nullable()
                        ->default(fn () => request()->get('group_label'))
                        ->helperText('Label yang tampil di header grup, misal: General Cleaning.'),
                ]),

                Grid::make(2)->schema([
                    Toggle::make('is_active')
                        ->label('Aktif')
                        ->default(true),

                    TextInput::make('weight')
                        ->label('Bobot Penilaian (%)')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100)
                        ->step(0.01)
                        ->nullable()
                        ->placeholder('Kosongkan jika tidak dinilai')
                        ->helperText('Persentase kontribusi poin ini terhadap total nilai (0–100).'),
                ]),
            ]),

            Section::make('Batas Waktu (Deadline)')->schema([
                Toggle::make('deadline_enabled')
                    ->label('Aktifkan Deadline')
                    ->live()
                    ->default(false),

                Grid::make(2)->schema([
                    TimePicker::make('deadline_time')
                        ->label('Jam Batas')
                        ->seconds(false)
                        ->nullable()
                        ->visible(fn ($get) => $get('deadline_enabled')),

                    Select::make('deadline_day')
                        ->label('Hari Batas')
                        ->options([
                            1 => 'Senin',
                            2 => 'Selasa',
                            3 => 'Rabu',
                            4 => 'Kamis',
                            5 => 'Jumat',
                            6 => 'Sabtu',
                            7 => 'Minggu',
                        ])
                        ->nullable()
                        ->dehydrated(function ($get) {
                            $period = $get('period');
                            $value = $period instanceof BriefingPeriod ? $period->value : $period;

                            return $value === 'weekly';
                        })
                        ->visible(function ($get) {
                            if (! $get('deadline_enabled')) {
                                return false;
                            }
                            $period = $get('period');
                            $value = $period instanceof BriefingPeriod ? $period->value : $period;

                            return $value === 'weekly';
                        }),

                    TextInput::make('deadline_day')
                        ->label('Tanggal Batas')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(31)
                        ->nullable()
                        ->helperText('Isi 1–31, atau 0 untuk hari terakhir bulan.')
                        ->extraAttributes([
                            'x-on:keydown' => "
                                if (/^[0-9]$/.test(\$event.key)) {
                                    const digits = \$el.value.replace(/[^0-9]/g, '');
                                    if (digits.length >= 2) {
                                        \$event.preventDefault();
                                        alert('Tanggal hanya boleh 2 digit (0–31).');
                                    }
                                }
                            ",
                            'x-on:input' => '
                                let v = parseInt($el.value);
                                if (!isNaN(v) && v > 31) $el.value = 31;
                                if (!isNaN(v) && v < 0) $el.value = 0;
                            ',
                        ])
                        ->dehydrated(function ($get) {
                            $period = $get('period');
                            $value = $period instanceof BriefingPeriod ? $period->value : $period;

                            return $value === 'monthly';
                        })
                        ->visible(function ($get) {
                            if (! $get('deadline_enabled')) {
                                return false;
                            }
                            $period = $get('period');
                            $value = $period instanceof BriefingPeriod ? $period->value : $period;

                            return $value === 'monthly';
                        }),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable()
                    ->width(50),

                TextColumn::make('label')
                    ->label('Nama Poin')
                    ->searchable()
                    ->wrap(),

                TextColumn::make('branch.name')
                    ->label('Branch')
                    ->placeholder('Semua Branch')
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                TextColumn::make('period')
                    ->label('Periode')
                    ->badge()
                    ->sortable(),

                TextColumn::make('submission_type')
                    ->label('Jenis Input')
                    ->badge()
                    ->sortable(),

                TextColumn::make('group_label')
                    ->label('Grup')
                    ->placeholder('-')
                    ->sortable(),

                IconColumn::make('deadline_enabled')
                    ->label('Deadline')
                    ->boolean()
                    ->trueIcon('heroicon-o-clock')
                    ->falseIcon('heroicon-o-minus'),

                TextColumn::make('deadline_time')
                    ->label('Jam Batas')
                    ->placeholder('-')
                    ->formatStateUsing(fn (?string $state): string => $state ? substr($state, 0, 5) : '-'),

                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('branch_id')
                    ->label('Branch')
                    ->options(fn () => Branch::where('is_active', true)->pluck('name', 'id'))
                    ->placeholder('Semua Branch'),

                SelectFilter::make('period')
                    ->label('Periode')
                    ->options(BriefingPeriod::class),

                SelectFilter::make('submission_type')
                    ->label('Jenis Input')
                    ->options(BriefingSubmissionType::class),
            ])
            ->defaultSort('period')
            ->reorderable('sort_order')
            ->recordActions([
                EditAction::make()->iconButton()->tooltip('Edit'),
            ])
            ->toolbarActions([
                ExportAction::make()->icon('heroicon-o-arrow-down-tray')->color('success')->iconButton()->tooltip('Export Excel')->exporter(BriefingTaskExporter::class),

                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBriefingTasks::route('/'),
            'create' => CreateBriefingTask::route('/create'),
            'edit' => EditBriefingTask::route('/{record}/edit'),
        ];
    }
}
