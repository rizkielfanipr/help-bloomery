<?php

namespace App\Filament\Helpdesk\Resources\ServiceTemplates;

use App\Filament\Helpdesk\Resources\ServiceTemplates\Pages\CreateServiceTemplate;
use App\Filament\Helpdesk\Resources\ServiceTemplates\Pages\EditServiceTemplate;
use App\Filament\Helpdesk\Resources\ServiceTemplates\Pages\ListServiceTemplates;
use App\Models\ServiceTemplate;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ServiceTemplateResource extends Resource
{
    protected static ?string $model = ServiceTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-duplicate';

    protected static string|\UnitEnum|null $navigationGroup = 'Teknisi';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Template Pekerjaan';

    protected static ?string $pluralModelLabel = 'Template Pekerjaan';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                TextInput::make('name')
                    ->label('Nama Pekerjaan')
                    ->required()
                    ->maxLength(255),

                Textarea::make('description')
                    ->label('Deskripsi Default')
                    ->rows(3)
                    ->nullable()
                    ->helperText('Digunakan sebagai isian awal saat membuat permintaan baru'),

                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('description')
                    ->label('Deskripsi')
                    ->limit(60)
                    ->placeholder('-'),

                TextColumn::make('service_requests_count')
                    ->label('Dipakai')
                    ->counts('serviceRequests')
                    ->suffix(' pekerjaan')
                    ->badge()
                    ->color('gray'),

                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListServiceTemplates::route('/'),
            'create' => CreateServiceTemplate::route('/create'),
            'edit' => EditServiceTemplate::route('/{record}/edit'),
        ];
    }
}
