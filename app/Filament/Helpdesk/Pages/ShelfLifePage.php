<?php

namespace App\Filament\Helpdesk\Pages;

use App\Models\RndProjectProduct;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use UnitEnum;

class ShelfLifePage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static string|UnitEnum|null $navigationGroup = 'Research & Development';

    protected static ?string $navigationLabel = 'Shelf Life';

    protected static ?string $title = 'Shelf Life';

    protected static ?string $slug = 'shelf-life';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.helpdesk.pages.shelf-life-page';

    public string $search = '';

    public string $storageCondition = '';

    public string $status = '';

    public int $page = 1;

    public int $perPage = 20;

    public bool $shelfLifeModalOpen = false;

    public ?int $editingProductId = null;

    public string $editingProductName = '';

    public string $shelfLifeValue = '';

    public string $shelfLifeUnit = 'month';

    public string $editingStorageCondition = 'dry';

    public string $storageNotes = '';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view rnd projects') ?? false;
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'storageCondition', 'status', 'perPage'], true)) {
            $this->page = 1;
        }
    }

    public function goToPage(int $page): void
    {
        $this->page = max(1, $page);
    }

    public function editShelfLife(int $productId): void
    {
        abort_unless(auth()->user()?->can('edit rnd projects'), 403);
        $product = RndProjectProduct::query()->findOrFail($productId);

        $this->editingProductId = $product->id;
        $this->editingProductName = $product->name;
        $this->shelfLifeValue = (string) ($product->shelf_life_value ?? '');
        $this->shelfLifeUnit = $product->shelf_life_unit ?? 'month';
        $this->editingStorageCondition = $product->storage_condition ?? 'dry';
        $this->storageNotes = $product->storage_notes ?? '';
        $this->resetValidation();
        $this->shelfLifeModalOpen = true;
    }

    public function saveShelfLife(): void
    {
        abort_unless(auth()->user()?->can('edit rnd projects'), 403);
        $validated = $this->validate([
            'editingProductId' => ['required', 'integer', 'exists:rnd_project_products,id'],
            'shelfLifeValue' => ['required', 'integer', 'min:1', 'max:9999'],
            'shelfLifeUnit' => ['required', Rule::in(array_keys(RndProjectProduct::SHELF_LIFE_UNITS))],
            'editingStorageCondition' => ['required', Rule::in(array_keys(RndProjectProduct::STORAGE_CONDITIONS))],
            'storageNotes' => ['nullable', 'string', 'max:2000'],
        ]);

        RndProjectProduct::query()->findOrFail($validated['editingProductId'])->update([
            'shelf_life_value' => $validated['shelfLifeValue'],
            'shelf_life_unit' => $validated['shelfLifeUnit'],
            'storage_condition' => $validated['editingStorageCondition'],
            'storage_notes' => trim($validated['storageNotes']) ?: null,
        ]);

        $this->closeShelfLifeModal();
        Notification::make()->title('Shelf life produk berhasil diperbarui')->success()->send();
    }

    public function closeShelfLifeModal(): void
    {
        $this->shelfLifeModalOpen = false;
        $this->editingProductId = null;
        $this->editingProductName = '';
        $this->shelfLifeValue = '';
        $this->shelfLifeUnit = 'month';
        $this->editingStorageCondition = 'dry';
        $this->storageNotes = '';
        $this->resetValidation();
    }

    public function rows(): LengthAwarePaginator
    {
        return RndProjectProduct::query()
            ->with('project:id,name')
            ->when(trim($this->search) !== '', function (Builder $query): void {
                $search = '%'.trim($this->search).'%';
                $query->where(fn (Builder $query) => $query
                    ->where('name', 'like', $search)
                    ->orWhere('product_code', 'like', $search)
                    ->orWhereHas('project', fn (Builder $query) => $query->where('name', 'like', $search)));
            })
            ->when($this->storageCondition !== '', fn (Builder $query) => $query->where('storage_condition', $this->storageCondition))
            ->when($this->status !== '', fn (Builder $query) => $query->where('status', $this->status))
            ->orderBy('name')
            ->paginate($this->perPage, ['*'], 'page', $this->page);
    }

    /** @return array<string, string|int> */
    public function exportParameters(): array
    {
        return array_filter([
            'search' => trim($this->search),
            'storage_condition' => $this->storageCondition,
            'status' => $this->status,
        ], fn (string $value): bool => $value !== '');
    }
}
