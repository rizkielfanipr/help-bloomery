<?php

namespace App\Filament\Helpdesk\Resources\PurchaseRequests\Schemas;

use App\Enums\PurchaseRequestStatus;
use App\Enums\PurchaseType;
use Carbon\Carbon;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PurchaseRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Informasi Pengajuan')
                ->columns(2)
                ->schema([
                    TextInput::make('user.name')
                        ->label('Pemohon')
                        ->disabled(),

                    TextInput::make('branch.name')
                        ->label('Cabang')
                        ->disabled(),

                    TextInput::make('division')
                        ->label('Divisi')
                        ->disabled(),

                    TextInput::make('item_name')
                        ->label('Nama Barang')
                        ->disabled(),

                    TextInput::make('quantity')
                        ->label('Jumlah')
                        ->disabled(),

                    TextInput::make('purchase_type')
                        ->label('Jenis Pembelian')
                        ->disabled()
                        ->formatStateUsing(fn ($state) => $state instanceof PurchaseType ? $state->getLabel() : PurchaseType::tryFrom((string) $state)?->getLabel()),

                    TextInput::make('journal_item_number')
                        ->label('No. Item Journal')
                        ->disabled()
                        ->placeholder('-')
                        ->columnSpan(1),

                    TextInput::make('purchase_request_number')
                        ->label('No. Purchase Request')
                        ->disabled()
                        ->placeholder('-')
                        ->columnSpan(1),

                    TextInput::make('ecommerce_link')
                        ->label('Link e-Commerce')
                        ->disabled()
                        ->placeholder('-')
                        ->columnSpanFull(),

                    Textarea::make('purchase_reason')
                        ->label('Alasan Pembelian')
                        ->disabled()
                        ->rows(3)
                        ->columnSpanFull(),
                ]),

            Section::make('Proses Pengajuan')
                ->columns(1)
                ->schema([
                    Select::make('status')
                        ->label('Status')
                        ->options(collect(PurchaseRequestStatus::cases())->mapWithKeys(
                            fn (PurchaseRequestStatus $s) => [$s->value => $s->getLabel()]
                        ))
                        ->required(),

                    Textarea::make('admin_notes')
                        ->label('Catatan Admin')
                        ->rows(3)
                        ->placeholder('Tambahkan catatan jika diperlukan...'),

                    Grid::make(2)
                        ->schema([
                            TextInput::make('processedBy.name')
                                ->label('Diproses oleh')
                                ->disabled(),

                            TextInput::make('updated_at')
                                ->label('Terakhir diperbarui')
                                ->disabled()
                                ->formatStateUsing(fn ($state) => $state ? Carbon::parse($state)->locale('id')->isoFormat('D MMM Y, HH:mm') : null),
                        ]),
                ]),
        ]);
    }
}
