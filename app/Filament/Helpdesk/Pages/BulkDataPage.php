<?php

namespace App\Filament\Helpdesk\Pages;

use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

class BulkDataPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cloud-arrow-up';

    protected static string|UnitEnum|null $navigationGroup = 'Information Technology';

    protected static ?string $navigationLabel = 'Bulk Data';

    protected static ?string $title = 'Bulk Data';

    protected static ?string $slug = 'bulk-data';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.helpdesk.pages.bulk-data-page';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view bulk product submissions') ?? false;
    }
}
