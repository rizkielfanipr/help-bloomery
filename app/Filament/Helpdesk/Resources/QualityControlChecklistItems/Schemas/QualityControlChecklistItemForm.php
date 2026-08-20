<?php

namespace App\Filament\Helpdesk\Resources\QualityControlChecklistItems\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class QualityControlChecklistItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Poin Pemeriksaan')->schema([
                    Grid::make(2)->schema([
                        TextInput::make('section_code')
                            ->label('Kode Section')
                            ->placeholder('A')
                            ->required()
                            ->maxLength(10),
                        TextInput::make('section_name')
                            ->label('Nama Section')
                            ->placeholder('Hygiene & Food Safety')
                            ->required()
                            ->maxLength(255),
                    ]),
                    Textarea::make('question')
                        ->label('Pertanyaan/Poin Audit')
                        ->required()
                        ->rows(2)
                        ->columnSpanFull(),
                    Textarea::make('check_procedure')
                        ->label('Prosedur Pengecekan')
                        ->rows(3)
                        ->columnSpanFull(),
                    Grid::make(2)->schema([
                        TextInput::make('points')
                            ->label('Poin Maksimal')
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                        TextInput::make('sort_order')
                            ->label('Urutan')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->required(),
                    ]),
                    Grid::make(3)->schema([
                        Toggle::make('is_critical')->label('Poin Critical'),
                        Toggle::make('requires_photo')->label('Wajib Foto'),
                        Toggle::make('is_active')->label('Aktif')->default(true),
                    ]),
                ]),
            ]);
    }
}
