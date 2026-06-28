<?php

namespace App\Filament\Helpdesk\Concerns;

use Illuminate\Database\Eloquent\Model;

trait HasPermissions
{
    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view '.static::$permissionPrefix) ?? false;
    }

    public static function canView(Model $record): bool
    {
        return auth()->user()?->can('view '.static::$permissionPrefix) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create '.static::$permissionPrefix) ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->can('edit '.static::$permissionPrefix) ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->can('delete '.static::$permissionPrefix) ?? false;
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->can('delete '.static::$permissionPrefix) ?? false;
    }

    public static function canForceDelete(Model $record): bool
    {
        return auth()->user()?->can('delete '.static::$permissionPrefix) ?? false;
    }

    public static function canForceDeleteAny(): bool
    {
        return auth()->user()?->can('delete '.static::$permissionPrefix) ?? false;
    }

    public static function canRestore(Model $record): bool
    {
        return auth()->user()?->can('edit '.static::$permissionPrefix) ?? false;
    }

    public static function canRestoreAny(): bool
    {
        return auth()->user()?->can('edit '.static::$permissionPrefix) ?? false;
    }
}
