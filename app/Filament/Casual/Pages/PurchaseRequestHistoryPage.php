<?php

namespace App\Filament\Casual\Pages;

use App\Enums\PurchaseRequestStatus;
use App\Models\PurchaseRequest;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

class PurchaseRequestHistoryPage extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static string $layout = 'filament.casual.layouts.bare';

    protected string $view = 'filament.casual.pages.purchase-request-history-page';

    public ?int $expandedId = null;

    public function getTitle(): string|Htmlable
    {
        return new HtmlString('');
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

    public function confirmReceived(int $id): void
    {
        $request = PurchaseRequest::where('id', $id)
            ->where('user_id', auth()->id())
            ->where('status', PurchaseRequestStatus::Delivered->value)
            ->firstOrFail();

        $request->update(['status' => PurchaseRequestStatus::Completed->value]);

        Notification::make()
            ->title('Barang dikonfirmasi diterima')
            ->success()
            ->send();
    }

    public function requests(): Collection
    {
        return PurchaseRequest::where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
