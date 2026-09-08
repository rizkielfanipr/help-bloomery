<?php

namespace App\Filament\Helpdesk\Pages;

use App\Models\Branch;
use App\Models\RndProductSalesProjection;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use UnitEnum;

class SalesProjectionPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static string|UnitEnum|null $navigationGroup = 'Sales & Growth';

    protected static ?string $navigationLabel = 'Sales Projection';

    protected static ?string $title = 'Sales Projection';

    protected static ?string $slug = 'sales-projection';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.helpdesk.pages.sales-projection-page';

    public string $search = '';

    public string $month = '';

    public string $channel = '';

    public string $branchId = '';

    public int $page = 1;

    public int $perPage = 20;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user?->hasRole('SUPERADMIN') || $user?->can('view sales projections') || false;
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'month', 'channel', 'branchId', 'perPage'], true)) {
            $this->page = 1;
        }
    }

    public function goToPage(int $page): void
    {
        $this->page = max(1, $page);
    }

    public function rows(): LengthAwarePaginator
    {
        return $this->filteredQuery()
            ->orderByDesc('projection_month')
            ->orderByDesc('id')
            ->paginate($this->perPage, ['*'], 'page', $this->page);
    }

    /** @return array{quantity: float, revenue: float, products: int, branches: int} */
    public function summary(): array
    {
        $query = $this->filteredQuery();

        return [
            'quantity' => (float) (clone $query)->sum('target_quantity'),
            'revenue' => (float) (clone $query)->sum('target_revenue'),
            'products' => (clone $query)->distinct()->count('rnd_project_product_id'),
            'branches' => Branch::query()
                ->whereHas('salesProjectionTargets', fn (Builder $branchQuery) => $branchQuery->whereIn(
                    'rnd_product_sales_projections.id',
                    (clone $query)->select('rnd_product_sales_projections.id'),
                ))
                ->count(),
        ];
    }

    /** @return Collection<int, Branch> */
    public function branches(): Collection
    {
        return Branch::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }

    private function filteredQuery(): Builder
    {
        return RndProductSalesProjection::query()
            ->with([
                'product:id,rnd_project_id,name,product_code,status',
                'product.project:id,name',
                'region:id,name,code',
                'targetBranches:id,name',
                'creator:id,name',
            ])
            ->when(trim($this->search) !== '', function (Builder $query): void {
                $search = '%'.trim($this->search).'%';
                $query->whereHas('product', fn (Builder $productQuery) => $productQuery
                    ->where('name', 'like', $search)
                    ->orWhere('product_code', 'like', $search)
                    ->orWhereHas('project', fn (Builder $projectQuery) => $projectQuery->where('name', 'like', $search)));
            })
            ->when($this->month !== '', fn (Builder $query) => $query->whereDate('projection_month', $this->month.'-01'))
            ->when($this->channel !== '', fn (Builder $query) => $query->where('channel', $this->channel))
            ->when($this->branchId !== '', fn (Builder $query) => $query->whereHas(
                'targetBranches',
                fn (Builder $branchQuery) => $branchQuery->where('branches.id', (int) $this->branchId),
            ));
    }
}
