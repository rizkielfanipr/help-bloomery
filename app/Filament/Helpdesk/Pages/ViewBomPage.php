<?php

namespace App\Filament\Helpdesk\Pages;

use App\Services\EsbCoreService;
use Filament\Pages\Page;

class ViewBomPage extends Page
{
    protected static ?string $slug = 'bill-of-material/{bom}/view';

    protected static ?string $title = 'Detail Bill of Material';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.helpdesk.pages.view-bom';

    public int $bomId;

    public array $detail = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user?->hasRole('SUPERADMIN')
            || ($user?->can('view bill of materials') ?? false);
    }

    public function mount(int $bom): void
    {
        $this->bomId = $bom;
        $this->detail = app(EsbCoreService::class)->getBillOfMaterial($bom);
    }
}
