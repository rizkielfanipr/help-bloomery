<?php

namespace App\Filament\Helpdesk\Pages;

use Filament\Pages\Page;

class PermissionsPage extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-key';

    protected static ?string $title = 'Daftar Permission';

    protected string $view = 'filament.helpdesk.pages.permissions-page';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view roles') ?? false;
    }

    public function getPermissions(): array
    {
        return config('permissions', []);
    }
}
