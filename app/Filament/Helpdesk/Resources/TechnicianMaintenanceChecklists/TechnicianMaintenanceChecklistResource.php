<?php

namespace App\Filament\Helpdesk\Resources\TechnicianMaintenanceChecklists;

use App\Filament\Helpdesk\Resources\TechnicianMaintenanceChecklists\Pages\CreateTechnicianMaintenanceChecklist;
use App\Filament\Helpdesk\Resources\TechnicianMaintenanceChecklists\Pages\EditTechnicianMaintenanceChecklist;
use App\Filament\Helpdesk\Resources\TechnicianMaintenanceChecklists\Pages\ListTechnicianMaintenanceChecklists;
use App\Models\TechnicianMaintenanceChecklist;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TechnicianMaintenanceChecklistResource extends Resource
{
    protected static ?string $model = TechnicianMaintenanceChecklist::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static string|\UnitEnum|null $navigationGroup = 'Teknisi';

    protected static ?string $navigationParentItem = 'Pemeliharaan';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Checklist Poin';

    protected static ?string $modelLabel = 'Checklist Poin';

    protected static ?string $pluralModelLabel = 'Checklist Poin';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Informasi Checklist')->schema([
                Textarea::make('question')->label('Pertanyaan checklist')->required()->columnSpanFull(),
                Textarea::make('check_procedure')->label('Prosedur pengecekan')->columnSpanFull(),
                TextInput::make('sort_order')->label('Urutan')->numeric()->required()->minValue(0)->default(0),
                Checkbox::make('requires_photo')->label('Wajib foto'),
                Checkbox::make('is_active')->label('Aktif')->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('sort_order')->columns([
            TextColumn::make('sort_order')->label('#')->sortable(),
            TextColumn::make('question')->label('Pertanyaan')->searchable()->wrap(),
            IconColumn::make('requires_photo')->label('Foto')->boolean(),
            IconColumn::make('is_active')->label('Aktif')->boolean(),
        ])->actions([
            EditAction::make(),
        ])->bulkActions([
            BulkActionGroup::make([
                DeleteBulkAction::make(),
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTechnicianMaintenanceChecklists::route('/'),
            'create' => CreateTechnicianMaintenanceChecklist::route('/create'),
            'edit' => EditTechnicianMaintenanceChecklist::route('/{record}/edit'),
        ];
    }
}
