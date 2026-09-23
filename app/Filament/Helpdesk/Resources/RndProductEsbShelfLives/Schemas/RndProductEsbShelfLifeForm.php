<?php

namespace App\Filament\Helpdesk\Resources\RndProductEsbShelfLives\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RndProductEsbShelfLifeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Master Shelf Life')
                    ->description('Shelf Life tidak tersedia dari ESB; nilai ini menjadi sumber lokal untuk Memo Internal (Company Code BLSS).')
                    ->schema([
                        TextInput::make('product_name')
                            ->label('Nama Produk / Menu')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('esb_menu_id')
                            ->label('Menu ID ESB')
                            ->helperText('Diisi bila master ini untuk sebuah Menu; dipakai untuk mengisi otomatis form Memo Internal.')
                            ->numeric()
                            ->nullable(),

                        TextInput::make('product_code')
                            ->label('Kode Produk')
                            ->maxLength(100)
                            ->nullable(),

                        TextInput::make('shelf_life_value')
                            ->label('Nilai Shelf Life')
                            ->numeric()
                            ->minValue(0)
                            ->required(),

                        Select::make('shelf_life_unit')
                            ->label('Satuan')
                            ->options(['jam' => 'Jam', 'hari' => 'Hari', 'minggu' => 'Minggu', 'bulan' => 'Bulan'])
                            ->required(),

                        TextInput::make('storage_condition')
                            ->label('Kondisi Penyimpanan')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('mis. Chiller 2-8°C'),

                        DatePicker::make('effective_from')
                            ->label('Berlaku Mulai')
                            ->native(false),

                        DatePicker::make('effective_until')
                            ->label('Berlaku Sampai')
                            ->native(false)
                            ->afterOrEqual('effective_from'),

                        Textarea::make('notes')
                            ->label('Catatan')
                            ->rows(2)
                            ->columnSpanFull(),

                        Toggle::make('is_active')
                            ->label('Aktif')
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }
}
