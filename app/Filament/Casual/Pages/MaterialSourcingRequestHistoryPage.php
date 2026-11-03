<?php

namespace App\Filament\Casual\Pages;

use App\Actions\Purchasing\MaterialSourcingRequest\CancelMaterialSourcingRequestAction;
use App\Models\MaterialSourcingRequest;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\WithPagination;

/**
 * The requester's own Request Sourcing history (PRD §17.4). It never loads supplier, price, or
 * sourcing review data: a converted request only shows its sourcing reference.
 */
class MaterialSourcingRequestHistoryPage extends Page
{
    use WithPagination;

    protected static bool $shouldRegisterNavigation = false;

    protected static string $layout = 'filament.casual.layouts.bare';

    protected string $view = 'filament.casual.pages.material-sourcing-request-history-page';

    public ?int $expandedId = null;

    public ?int $cancellingId = null;

    public string $cancelReason = '';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('accessEmployeeApp', MaterialSourcingRequest::class) ?? false;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public function getTitle(): string|Htmlable
    {
        return 'Riwayat Request Sourcing';
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

    public function startCancel(int $id): void
    {
        $this->cancellingId = $id;
        $this->cancelReason = '';
        $this->resetValidation();
    }

    public function stopCancel(): void
    {
        $this->cancellingId = null;
        $this->cancelReason = '';
        $this->resetValidation();
    }

    public function cancelRequest(): void
    {
        $request = MaterialSourcingRequest::query()->ownedBy(auth()->user())->findOrFail((int) $this->cancellingId);

        app(CancelMaterialSourcingRequestAction::class)->execute(auth()->user(), $request, $this->cancelReason);

        $this->stopCancel();
        Notification::make()->success()->title('Request dibatalkan')->body("{$request->request_number} tidak diproses.")->send();
    }

    /** @return LengthAwarePaginator<int, MaterialSourcingRequest> */
    public function requests(): LengthAwarePaginator
    {
        return MaterialSourcingRequest::query()
            ->ownedBy(auth()->user())
            ->select([
                'id', 'request_number', 'status', 'product_code', 'product_name', 'uom_name', 'reason', 'estimated_quantity',
                'target_date', 'notes', 'attachments', 'last_decision_reason', 'last_decision_at', 'rnd_product_esb_material_id',
                'submitted_at', 'converted_at', 'created_at', 'requester_id',
            ])
            ->latest('id')
            ->paginate(10);
    }
}
