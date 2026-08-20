<?php

namespace App\Filament\Helpdesk\Resources\QualityControlAudits\RelationManagers;

use Filament\Actions\ViewAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
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
                    Placeholder::make('earned_points')
                        ->label('Skor')
                        ->content(fn ($record): string => $record ? "{$record->earned_points}/{$record->maximum_points} poin" : '—'),
                    Textarea::make('notes')->label('Temuan/Catatan Auditor')->rows(3),
                    FileUpload::make('evidence_photos')
                        ->label('Foto Bukti')
                        ->image()
                        ->multiple()
                        ->maxFiles(5)
                        ->disk('b2')
                        ->directory('quality-control/audit-evidence')
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
                TextColumn::make('result')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state !== null ? 'Sudah Dinilai' : 'Belum Dinilai')
                    ->color(fn (?string $state): string => $state !== null ? 'success' : 'gray'),
                TextColumn::make('earned_points')->label('Skor')->formatStateUsing(fn ($state, $record): string => $state.'/'.$record->maximum_points),
                IconColumn::make('is_critical')->label('Critical')->boolean(),
            ])
            ->filters([
                SelectFilter::make('section_code')
                    ->label('Section')
                    ->options(fn (): array => $this->getOwnerRecord()->items()
                        ->reorder('section_code')->distinct()->pluck('section_name', 'section_code')->all()),
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
