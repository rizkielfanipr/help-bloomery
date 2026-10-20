<?php

namespace App\Filament\Casual\Pages;

use App\Actions\ClaimErpRequestWarrantyAction;
use App\Models\ErpRepairRequest;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;

class ErpRequestHistoryPage extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static string $layout = 'filament.casual.layouts.bare';

    protected string $view = 'filament.casual.pages.erp-request-history-page';

    public ?int $expandedId = null;

    /** @var array<int, string> */
    public array $claimNotes = [];

    public function getTitle(): string|Htmlable
    {
        return 'Riwayat Request ERP';
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

    public function claimWarranty(int $id): void
    {
        $request = ErpRepairRequest::query()->where('requester_id', auth()->id())->findOrFail($id);
        $this->validate(["claimNotes.{$id}" => ['required', 'string', 'max:5000']], ["claimNotes.{$id}.required" => 'Alasan klaim Warranty wajib diisi.']);

        app(ClaimErpRequestWarrantyAction::class)->execute($request, auth()->user(), $this->claimNotes[$id]);
        unset($this->claimNotes[$id]);
        Notification::make()->title('Klaim Warranty dikirim ulang')->success()->send();
    }

    public function requests(): Collection
    {
        return ErpRepairRequest::with(['module', 'requestType'])
            ->where('requester_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
