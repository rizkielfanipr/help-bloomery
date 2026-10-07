<?php

namespace App\Filament\Helpdesk\Pages;

use App\Actions\Rnd\ShelfLife\CreateWipShelfLifeAction;
use App\Actions\Rnd\ShelfLife\SyncWipProductCatalogAction;
use App\Actions\Rnd\ShelfLife\UpdateWipShelfLifeAction;
use App\Enums\RndWipShelfLifeSource;
use App\Enums\RndWipShelfLifeStatus;
use App\Exceptions\Rnd\WipShelfLifeAlreadyExistsException;
use App\Filament\Helpdesk\Concerns\ManagesWipShelfLifeForm;
use App\Jobs\Rnd\SyncWipProductCatalogJob;
use App\Models\RndProductEsbShelfLife;
use App\Models\RndWipProduct;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * Shelf Life menu: lists the active ESB Products in category "Barang WIP" and is the only place
 * an existing WIP Shelf Life master can be edited, deactivated, or reactivated. Masters are kept
 * locally and never sent to ESB.
 */
class WipShelfLifePage extends Page
{
    use ManagesWipShelfLifeForm;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static string|UnitEnum|null $navigationGroup = 'Research & Development';

    protected static ?string $navigationLabel = 'Shelf Life';

    protected static ?string $title = 'Shelf Life';

    protected static ?string $slug = 'shelf-life';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.helpdesk.pages.wip-shelf-life';

    public string $search = '';

    public string $unitFilter = '';

    #[Url]
    public string $shelfLifeFilter = '';

    public int $perPage = 20;

    public int $page = 1;

    /** WIP product row the modal was opened for; its Product Detail ID is re-read on save. */
    #[Locked]
    public ?int $wipProductId = null;

    public static function canAccess(): bool
    {
        return Auth::user()?->can('view wip shelf life') ?? false;
    }

    public function canEdit(): bool
    {
        return Auth::user()?->can('manage wip shelf life') ?? false;
    }

    public function updatedSearch(): void
    {
        $this->page = 1;
    }

    public function updatedUnitFilter(): void
    {
        $this->page = 1;
    }

    public function updatedShelfLifeFilter(): void
    {
        $this->page = 1;
    }

    public function updatedPerPage(): void
    {
        $this->page = 1;
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->unitFilter = '';
        $this->shelfLifeFilter = '';
        $this->page = 1;
    }

    public function previousPage(): void
    {
        if ($this->page > 1) {
            $this->page--;
        }
    }

    public function nextPage(): void
    {
        if ($this->productRows()->hasMorePages()) {
            $this->page++;
        }
    }

    public function goToPage(int $page): void
    {
        $this->page = max(1, min($page, $this->productRows()->lastPage()));
    }

    public function productRows(): LengthAwarePaginator
    {
        return RndWipProduct::query()
            ->listedProducts()
            ->with('wipShelfLife')
            ->search($this->search)
            ->withShelfLifeStatus(RndWipShelfLifeStatus::tryFrom($this->shelfLifeFilter))
            ->when($this->unitFilter !== '', fn ($query) => $query->where('uom_name', $this->unitFilter))
            ->orderBy('product_name')
            ->orderBy('product_detail_id')
            ->paginate($this->perPage, ['*'], 'page', $this->page);
    }

    /** @return list<string> */
    public function availableUnits(): array
    {
        return RndWipProduct::query()->listedProducts()->whereNotNull('uom_name')->distinct()->orderBy('uom_name')->pluck('uom_name')->all();
    }

    public function shelfLifeStatusFor(RndWipProduct $row): RndWipShelfLifeStatus
    {
        return RndWipShelfLifeStatus::for($row->product_detail_id, $row->wipShelfLife);
    }

    public function openShelfLifeModal(int $wipProductId): void
    {
        abort_unless($this->canEdit(), 403);

        $product = $this->wipProduct($wipProductId);
        $this->wipProductId = $product->id;
        $this->fillShelfLifeForm($this->shelfLifeIdentity($product), $product->wipShelfLife);
    }

    public function closeShelfLifeModal(): void
    {
        $this->resetValidation();
        $this->shelfLifeModalOpen = false;
        $this->shelfLifeTarget = null;
        $this->wipProductId = null;
    }

    public function saveShelfLife(): void
    {
        abort_unless($this->canEdit(), 403);
        abort_if($this->wipProductId === null, 404);

        $product = $this->wipProduct($this->wipProductId);
        $values = $this->validatedShelfLifeValues();

        try {
            // A modal opened as "Isi" always creates, so a master another user created meanwhile is
            // reported instead of silently overwritten.
            if ($this->shelfLifeTarget['is_existing'] && $product->wipShelfLife !== null) {
                app(UpdateWipShelfLifeAction::class)->execute($product->wipShelfLife, $values, Auth::user());
            } else {
                app(CreateWipShelfLifeAction::class)->execute(
                    RndWipShelfLifeSource::ShelfLifeMenu,
                    [
                        'company_code' => RndProductEsbShelfLife::DEFAULT_COMPANY_CODE,
                        'esb_product_detail_id' => $product->product_detail_id,
                        'product_code' => $product->product_code,
                        'product_name' => $product->product_name,
                        ...$values,
                    ],
                    Auth::user(),
                );
            }
        } catch (WipShelfLifeAlreadyExistsException $exception) {
            $this->fillShelfLifeForm($this->shelfLifeIdentity($product), $exception->existing);
            Notification::make()->title('Shelf Life sudah diisi pengguna lain')->body('Nilai terbaru ditampilkan. Periksa lalu simpan lagi bila perlu diubah.')->warning()->send();

            return;
        }

        $this->closeShelfLifeModal();
        Notification::make()->title('Shelf Life WIP tersimpan')->body('Data disimpan lokal dan tidak dikirim ke ESB.')->success()->send();
    }

    public function toggleShelfLifeActive(int $wipProductId): void
    {
        abort_unless($this->canEdit(), 403);

        $master = $this->wipProduct($wipProductId)->wipShelfLife;
        abort_if($master === null, 404);

        $master = app(UpdateWipShelfLifeAction::class)->setActive($master, ! $master->is_active, Auth::user());

        Notification::make()
            ->title($master->is_active ? 'Shelf Life WIP diaktifkan' : 'Shelf Life WIP dinonaktifkan')
            ->body('Perubahan hanya tersimpan lokal.')
            ->success()
            ->send();
    }

    public function refreshCatalog(): void
    {
        abort_unless($this->canEdit(), 403);

        SyncWipProductCatalogJob::dispatch(Auth::id());

        Notification::make()->title('Sinkronisasi produk WIP dimulai di latar belakang')->success()->send();
    }

    /** @return array<string, mixed>|null */
    public function syncProgress(): ?array
    {
        return SyncWipProductCatalogAction::progress();
    }

    /**
     * Server-side identity: only a listed (active, base unit) "Barang WIP" product can receive a master.
     */
    private function wipProduct(int $wipProductId): RndWipProduct
    {
        return RndWipProduct::query()->listedProducts()->with('wipShelfLife')->findOrFail($wipProductId);
    }

    /**
     * @return array{product_detail_id: int, product_code: ?string, product_name: string, uom_name: ?string}
     */
    private function shelfLifeIdentity(RndWipProduct $product): array
    {
        return [
            'product_detail_id' => $product->product_detail_id,
            'product_code' => $product->product_code,
            'product_name' => $product->product_name,
            'uom_name' => $product->uom_name,
        ];
    }
}
