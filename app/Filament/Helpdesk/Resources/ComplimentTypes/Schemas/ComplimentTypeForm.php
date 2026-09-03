<?php

namespace App\Filament\Helpdesk\Resources\ComplimentTypes\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ComplimentTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Compliment Type')->schema([
                    TextInput::make('name')
                        ->label('Nama')
                        ->required()
                        ->maxLength(100)
                        ->unique(ignoreRecord: true),
                    TextInput::make('sort_order')
                        ->label('Urutan')
                        ->numeric()
                        ->minValue(0)
                        ->default(0)
                        ->required(),
                    Toggle::make('is_active')
                        ->label('Aktif')
                        ->default(true),
                ]),
            ]);
    }
}
