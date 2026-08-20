<?php

namespace App\Filament\Helpdesk\Resources\QualityControlAudits\RelationManagers;

use App\Models\User;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Checklist Audit';

    protected static ?string $modelLabel = 'Poin Audit';

    protected static ?string $pluralModelLabel = 'Poin Audit';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Poin Pemeriksaan')->schema([
                    Placeholder::make('question')->label('Pertanyaan'),
                    Placeholder::make('check_procedure')->label('Prosedur Pengecekan')->placeholder('-'),
                    Grid::make(3)->schema([
                        Placeholder::make('maximum_points')->label('Poin Maksimal'),
                        Placeholder::make('is_critical')
                            ->label('Critical')
                            ->content(fn (bool $state): string => $state ? 'Ya' : 'Tidak'),
                        Placeholder::make('requires_photo')
                            ->label('Wajib Foto')
                            ->content(fn (bool $state): string => $state ? 'Ya' : 'Tidak'),
                    ]),
                ]),
                Section::make('Hasil Audit')->schema([
                    Select::make('result')
                        ->label('Hasil')
                        ->options([
                            'pass' => 'Sesuai',
                            'fail' => 'Tidak Sesuai',
                            'not_applicable' => 'Tidak Berlaku',
                        ])
                        ->required()
                        ->live(),
                    Textarea::make('notes')->label('Temuan/Catatan Auditor')->rows(3),
                    FileUpload::make('evidence_photos')
                        ->label('Foto Bukti')
                        ->image()
                        ->multiple()
                        ->maxFiles(5)
                        ->disk('public')
                        ->directory('quality-control/audit-evidence')
                        ->visibility('public')
                        ->required(fn (Get $get): bool => $get('result') === 'fail' && (bool) $get('requires_photo')),
                ]),
                Section::make('Corrective Action')
                    ->visible(fn (Get $get): bool => $get('result') === 'fail')
                    ->schema([
                        Textarea::make('corrective_action')->label('Rencana Perbaikan')->rows(3),
                        Grid::make(2)->schema([
                            Select::make('action_pic_id')
                                ->label('PIC')
                                ->options(fn () => User::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                                ->searchable(),
                            DatePicker::make('action_due_date')->label('Deadline'),
                        ]),
                        Select::make('action_status')
                            ->label('Status Tindak Lanjut')
                            ->options([
                                'open' => 'Open',
                                'in_progress' => 'In Progress',
                                'waiting_verification' => 'Menunggu Verifikasi',
                                'closed' => 'Closed',
                            ])
                            ->default('open'),
                        FileUpload::make('action_evidence_photos')
                            ->label('Bukti Perbaikan')
                            ->image()
                            ->multiple()
                            ->maxFiles(5)
                            ->disk('public')
                            ->directory('quality-control/action-evidence')
                            ->visibility('public'),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('question')
            ->columns([
                TextColumn::make('section_code')->label('Section')->badge()->sortable(),
                TextColumn::make('question')->label('Poin Pemeriksaan')->searchable()->wrap(),
                TextColumn::make('maximum_points')->label('Poin')->numeric(),
                TextColumn::make('result')
                    ->label('Hasil')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'pass' => 'Sesuai', 'fail' => 'Tidak Sesuai',
                        'not_applicable' => 'Tidak Berlaku', default => 'Belum Diisi',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'pass' => 'success', 'fail' => 'danger',
                        'not_applicable' => 'gray', default => 'warning',
                    }),
                TextColumn::make('earned_points')->label('Skor')->formatStateUsing(fn ($state, $record): string => $state.'/'.$record->maximum_points),
                IconColumn::make('is_critical')->label('Critical')->boolean(),
                TextColumn::make('action_status')
                    ->label('Tindak Lanjut')
                    ->badge()
                    ->formatStateUsing(fn (?string $state, $record): string => $record->result === 'fail' ? match ($state) {
                        'in_progress' => 'In Progress',
                        'waiting_verification' => 'Menunggu Verifikasi',
                        'closed' => 'Closed',
                        default => 'Open',
                    } : '-')
                    ->color(fn (?string $state, $record): string => $record->result === 'fail' ? match ($state) {
                        'closed' => 'success',
                        'waiting_verification' => 'info',
                        'in_progress' => 'warning',
                        default => 'danger',
                    } : 'gray'),
            ])
            ->filters([
                SelectFilter::make('section_code')
                    ->label('Section')
                    ->options(fn (): array => $this->getOwnerRecord()->items()
                        ->reorder('section_code')->distinct()->pluck('section_name', 'section_code')->all()),
                SelectFilter::make('result')->label('Hasil')->options([
                    'pass' => 'Sesuai', 'fail' => 'Tidak Sesuai',
                    'not_applicable' => 'Tidak Berlaku',
                ]),
            ])
            ->recordActions([
                ViewAction::make()
                    ->modalWidth(Width::FourExtraLarge),
            ])
            ->defaultSort('sort_order')
            ->paginated([10, 25, 50, 'all'])
            ->defaultPaginationPageOption(25);
    }
}
