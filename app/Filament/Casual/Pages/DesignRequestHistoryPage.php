<?php

namespace App\Filament\Casual\Pages;

use App\Models\DesignRequest;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;

class DesignRequestHistoryPage extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static string $layout = 'filament.casual.layouts.bare';

    protected string $view = 'filament.casual.pages.design-request-history-page';

    public ?int $expandedId = null;

    public function getTitle(): string|Htmlable
    {
        return 'Riwayat Request Design';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function toggleItem(int $id): void
    {
        $this->expandedId = $this->expandedId === $id ? null : $id;
    }

    public function requests(): Collection
    {
        return DesignRequest::with('category')
            ->where('requester_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
