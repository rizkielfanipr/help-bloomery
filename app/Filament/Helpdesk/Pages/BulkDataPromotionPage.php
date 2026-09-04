<?php

namespace App\Filament\Helpdesk\Pages;

use BackedEnum;
use Filament\Pages\Page;

class BulkDataPromotionPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static ?string $title = 'Bulk Data Promotion';

    protected static ?string $slug = 'bulk-data/promotion';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.helpdesk.pages.bulk-data-promotion-page';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view bulk product submissions') ?? false;
    }
}
