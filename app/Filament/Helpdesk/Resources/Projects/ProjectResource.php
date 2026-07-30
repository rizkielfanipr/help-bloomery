<?php

namespace App\Filament\Helpdesk\Resources\Projects;

use App\Filament\Helpdesk\Concerns\HasPermissions;
use App\Filament\Helpdesk\Resources\Projects\Pages\CreateProject;
use App\Filament\Helpdesk\Resources\Projects\Pages\EditProject;
use App\Filament\Helpdesk\Resources\Projects\Pages\ListProjects;
use App\Filament\Helpdesk\Resources\Projects\Pages\ViewProject;
use App\Models\RndProject;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class ProjectResource extends Resource
{
    use HasPermissions;

    protected static string $permissionPrefix = 'rnd projects';

    protected static ?string $model = RndProject::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-folder-open';

    protected static string|UnitEnum|null $navigationGroup = 'Research & Development';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Project';

    protected static ?string $modelLabel = 'Project';

    protected static ?string $pluralModelLabel = 'Project';

    protected static ?string $slug = 'rnd-projects';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Informasi Project')
                ->description('Informasi utama dan periode pelaksanaan project R&D.')
                ->schema([
                    TextInput::make('name')
                        ->label('Nama Project')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('Contoh: Pengembangan Menu Seasonal'),

                    Textarea::make('description')
                        ->label('Deskripsi Project')
                        ->rows(4)
                        ->columnSpanFull()
                        ->placeholder('Jelaskan tujuan, ruang lingkup, dan hasil yang diharapkan...'),

                    DatePicker::make('start_date')
                        ->label('Start Date')
                        ->required()
                        ->native(false)
                        ->displayFormat('d M Y'),

                    DatePicker::make('end_date')
                        ->label('End Date')
                        ->required()
                        ->native(false)
                        ->displayFormat('d M Y')
                        ->afterOrEqual('start_date'),
                ])
                ->columns(2),

        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Project')
                    ->searchable()
                    ->sortable()
                    ->description(fn (RndProject $record): ?string => str($record->description)->limit(70)->toString()),

                TextColumn::make('start_date')
                    ->label('Start Date')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('end_date')
                    ->label('End Date')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('creator.name')
                    ->label('Dibuat Oleh')
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('updated_at')
                    ->label('Terakhir Diubah')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->recordActions([
                ViewAction::make()->iconButton()->tooltip('Buka Project'),
                EditAction::make()->iconButton()->tooltip('Edit Project'),
                DeleteAction::make()->iconButton()->tooltip('Hapus Project'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProjects::route('/'),
            'create' => CreateProject::route('/create'),
            'view' => ViewProject::route('/{record}'),
            'edit' => EditProject::route('/{record}/edit'),
        ];
    }
}
