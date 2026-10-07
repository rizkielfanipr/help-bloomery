<?php

namespace App\Filament\Helpdesk\Resources\RndProjectTaskTemplates;

use App\Filament\Helpdesk\Resources\RndProjectTaskTemplates\Pages\CreateRndProjectTaskTemplate;
use App\Filament\Helpdesk\Resources\RndProjectTaskTemplates\Pages\EditRndProjectTaskTemplate;
use App\Filament\Helpdesk\Resources\RndProjectTaskTemplates\Pages\ListRndProjectTaskTemplates;
use App\Filament\Helpdesk\Resources\RndProjectTaskTemplates\Schemas\RndProjectTaskTemplateForm;
use App\Filament\Helpdesk\Resources\RndProjectTaskTemplates\Tables\RndProjectTaskTemplatesTable;
use App\Models\RndProjectTaskTemplate;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Template Checkpoint management (docs/rnd-project-checkpoint-calendar-prd.md §21.6). Every
 * check is delegated to `RndProjectTaskTemplatePolicy`: `view rnd project task templates` lists
 * templates, `manage rnd project task templates` creates/edits/deactivates them, and a template
 * that was ever applied can only be deactivated, never deleted.
 */
class RndProjectTaskTemplateResource extends Resource
{
    protected static ?string $model = RndProjectTaskTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static string|UnitEnum|null $navigationGroup = 'Research & Development';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Template Checkpoint';

    protected static ?string $modelLabel = 'Template Checkpoint';

    protected static ?string $pluralModelLabel = 'Template Checkpoint';

    protected static ?string $slug = 'rnd-project-task-templates';

    public static function form(Schema $schema): Schema
    {
        return RndProjectTaskTemplateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RndProjectTaskTemplatesTable::configure($table);
    }

    public static function canViewAny(): bool
    {
        return static::currentUser()?->can('viewAny', RndProjectTaskTemplate::class) ?? false;
    }

    public static function canCreate(): bool
    {
        return static::currentUser()?->can('create', RndProjectTaskTemplate::class) ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return static::currentUser()?->can('update', $record) ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return static::currentUser()?->can('delete', $record) ?? false;
    }

    private static function currentUser(): ?User
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $user;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRndProjectTaskTemplates::route('/'),
            'create' => CreateRndProjectTaskTemplate::route('/create'),
            'edit' => EditRndProjectTaskTemplate::route('/{record}/edit'),
        ];
    }
}
