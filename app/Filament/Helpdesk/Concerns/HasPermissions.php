<?php

namespace App\Filament\Helpdesk\Concerns;

use Illuminate\Database\Eloquent\Model;

trait HasPermissions
{
    private static function hasPermission(string $ability): bool
    {
        $user = auth()->user();

        return $user !== null
            && ($user->hasRole('SUPERADMIN') || $user->can($ability));
    }

    public static function canViewAny(): bool
    {
        return static::hasPermission('view '.static::$permissionPrefix);
    }

    public static function canView(Model $record): bool
    {
        return static::hasPermission('view '.static::$permissionPrefix);
    }

    public static function canCreate(): bool
    {
        return static::hasPermission('create '.static::$permissionPrefix);
    }

    public static function canEdit(Model $record): bool
    {
        return static::hasPermission('edit '.static::$permissionPrefix);
    }

    public static function canDelete(Model $record): bool
    {
        return static::hasPermission('delete '.static::$permissionPrefix);
    }

    public static function canDeleteAny(): bool
    {
        return static::hasPermission('delete '.static::$permissionPrefix);
    }

    public static function canForceDelete(Model $record): bool
    {
        return static::hasPermission('delete '.static::$permissionPrefix);
    }

    public static function canForceDeleteAny(): bool
    {
        return static::hasPermission('delete '.static::$permissionPrefix);
    }

    public static function canRestore(Model $record): bool
    {
        return static::hasPermission('edit '.static::$permissionPrefix);
    }

    public static function canRestoreAny(): bool
    {
        return static::hasPermission('edit '.static::$permissionPrefix);
    }
}
