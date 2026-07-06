<?php

namespace App\Filament\Helpdesk\Resources\FuelTypes\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class FuelTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama Jenis BBM')
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('price_per_liter')
                    ->label('Harga per Liter (Rp)')
                    ->numeric()
                    ->required()
                    ->prefix('Rp')
                    ->default(0)
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->label('Aktif'),
                TextInput::make('sort_order')
                    ->label('Urutan')
                    ->numeric()
                    ->default(0),
            ]);
    }
}
