<?php

namespace App\Filament\Helpdesk\Pages;

use App\Models\RndProjectProduct;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
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
