<?php

namespace App\Filament\Helpdesk\Pages;

use App\Filament\Helpdesk\Resources\Projects\ProjectResource;
use App\Services\EsbCoreService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use UnitEnum;

class BillOfMaterialPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-beaker';

    protected static string|UnitEnum|null $navigationGroup = 'Research & Development';

    protected static ?string $navigationLabel = 'Bill of Material';

    protected static ?string $title = 'Bill of Material';

    protected static ?string $slug = 'bill-of-material';

    protected static ?int $navigationSort = 1;

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.helpdesk.pages.bill-of-material';

    public string $productName = '';

    public string $uomName = '';

    public string $status = '1';

    public int $page = 1;

    public int $limit = 20;

    public int $total = 0;

    public array $rows = [];

    public bool $loaded = false;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('SUPERADMIN') ?? false;
    }

    public function mount(): void
    {
        $this->redirect(ProjectResource::getUrl());
    }

    public function search(): void
    {
        $this->page = 1;
        $this->fetch();
    }

    public function resetFilters(): void
    {
        $this->productName = '';
        $this->uomName = '';
        $this->status = '1';
        $this->page = 1;
        $this->fetch();
    }

    public function previousPage(): void
    {
        if ($this->page > 1) {
            $this->page--;
            $this->fetch();
        }
    }

    public function nextPage(): void
    {
        if ($this->page * $this->limit < $this->total) {
            $this->page++;
            $this->fetch();
        }
    }

    public function fetch(): void
    {
        try {
            $result = app(EsbCoreService::class)->getBillOfMaterials([
                'page' => $this->page,
                'limit' => $this->limit,
                'productName' => trim($this->productName),
                'uomName' => trim($this->uomName),
                'flagActive' => (int) $this->status,
            ]);

            $this->rows = $result['data'];
            $this->total = $result['count'];
            $this->page = $result['page'];
            $this->loaded = true;
        } catch (\RuntimeException $exception) {
            $this->rows = [];
            $this->loaded = true;
            Notification::make()->title('Data BOM belum dapat dimuat')->body($exception->getMessage())->danger()->send();
        }
    }
}
