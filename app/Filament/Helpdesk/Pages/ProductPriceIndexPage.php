<?php

namespace App\Filament\Helpdesk\Pages;

use App\Models\EsbPurchaseOrder;
use App\Models\EsbPurchaseOrderItem;
use App\Services\PurchaseOrderPriceSyncService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use UnitEnum;

class ProductPriceIndexPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static string|UnitEnum|null $navigationGroup = 'Research & Development';

    protected static ?string $navigationLabel = 'Product Price Index';

    protected static ?string $title = 'Product Price Index';

    protected static ?string $slug = 'product-price-index';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.helpdesk.pages.product-price-index';

    public string $search = '';

    public string $supplier = '';

    public string $branch = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public string $syncFrom = '';

    public string $syncTo = '';

    public int $page = 1;

    public int $perPage = 20;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user?->hasRole('SUPERADMIN')
            || ($user?->can('view product price index') ?? false);
    }

    public function mount(): void
    {
        $this->dateFrom = now()->subYear()->toDateString();
        $this->dateTo = now()->toDateString();
        $this->syncFrom = now()->subDays(30)->toDateString();
        $this->syncTo = now()->toDateString();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'supplier', 'branch', 'dateFrom', 'dateTo', 'perPage'], true)) {
            $this->page = 1;
        }
    }

    public function goToPage(int $page): void
    {
        $this->page = max(1, $page);
    }

    public function sync(): void
    {
        $user = auth()->user();
        abort_unless($user?->hasRole('SUPERADMIN') || $user?->can('sync product price index'), 403);

        $this->validate([
            'syncFrom' => ['required', 'date'],
            'syncTo' => ['required', 'date', 'after_or_equal:syncFrom'],
        ]);

        try {
            $result = app(PurchaseOrderPriceSyncService::class)
                ->sync($this->syncFrom, $this->syncTo, 10, 20);
            $body = "{$result['orders']} PO dan {$result['items']} item disinkronkan.";
            if ($result['failed'] > 0) {
                $body .= " {$result['failed']} PO gagal dan dapat dicoba kembali.";
            }
            Notification::make()->title('Sinkronisasi Product Price selesai')->body($body)->success()->send();
        } catch (\Throwable $exception) {
            Notification::make()->title('Sinkronisasi Product Price gagal')->body($exception->getMessage())->danger()->send();
        }
    }

    public function rows(): LengthAwarePaginator
    {
        $baseQuantity = 'CASE WHEN esb_purchase_order_items.stock_qty > 0 THEN esb_purchase_order_items.stock_qty ELSE esb_purchase_order_items.qty * (CASE WHEN esb_purchase_order_items.conversion_qty > 1 THEN esb_purchase_order_items.conversion_qty ELSE 1 END) END';
        $netAmount = '((esb_purchase_order_items.total - esb_purchase_order_items.vat) * esb_purchase_orders.rate)';
        $normalized = "($netAmount / NULLIF($baseQuantity, 0))";

        $query = EsbPurchaseOrderItem::query()
            ->join('esb_purchase_orders', 'esb_purchase_orders.id', '=', 'esb_purchase_order_items.esb_purchase_order_id')
            ->selectRaw("
                esb_purchase_order_items.product_detail_id,
                MAX(esb_purchase_order_items.product_id) as product_id,
                MAX(esb_purchase_order_items.product_code) as product_code,
                MAX(esb_purchase_order_items.product_name) as product_name,
                MAX(esb_purchase_order_items.uom_name) as uom_name,
                SUM($netAmount) / NULLIF(SUM($baseQuantity), 0) as average_price,
                MIN($normalized) as minimum_price,
                MAX($normalized) as maximum_price,
                COUNT(DISTINCT esb_purchase_orders.id) as po_count,
                MAX(esb_purchase_orders.purchase_date) as latest_purchase_date
            ")
            ->when($this->dateFrom, fn (Builder $query) => $query->whereDate('esb_purchase_orders.purchase_date', '>=', $this->dateFrom))
            ->when($this->dateTo, fn (Builder $query) => $query->whereDate('esb_purchase_orders.purchase_date', '<=', $this->dateTo))
            ->when(trim($this->search) !== '', function (Builder $query): void {
                $search = '%'.trim($this->search).'%';
                $query->where(fn (Builder $query) => $query
                    ->where('esb_purchase_order_items.product_code', 'like', $search)
                    ->orWhere('esb_purchase_order_items.product_name', 'like', $search));
            })
            ->when(trim($this->supplier) !== '', fn (Builder $query) => $query->where('esb_purchase_orders.supplier_name', 'like', '%'.trim($this->supplier).'%'))
            ->when(trim($this->branch) !== '', fn (Builder $query) => $query->where('esb_purchase_orders.branch_name', 'like', '%'.trim($this->branch).'%'))
            ->groupBy('esb_purchase_order_items.product_detail_id')
            ->orderBy('product_name');

        $paginator = $query->paginate($this->perPage, ['*'], 'page', $this->page);
        $paginator->setCollection($paginator->getCollection()->map(function ($row) {
            $latest = EsbPurchaseOrderItem::query()
                ->with('purchaseOrder')
                ->where('product_detail_id', $row->product_detail_id)
                ->whereHas('purchaseOrder', function (Builder $query): void {
                    $query->when($this->dateFrom, fn (Builder $query) => $query->whereDate('purchase_date', '>=', $this->dateFrom))
                        ->when($this->dateTo, fn (Builder $query) => $query->whereDate('purchase_date', '<=', $this->dateTo))
                        ->when(trim($this->supplier) !== '', fn (Builder $query) => $query->where('supplier_name', 'like', '%'.trim($this->supplier).'%'))
                        ->when(trim($this->branch) !== '', fn (Builder $query) => $query->where('branch_name', 'like', '%'.trim($this->branch).'%'));
                })
                ->join('esb_purchase_orders', 'esb_purchase_orders.id', '=', 'esb_purchase_order_items.esb_purchase_order_id')
                ->orderByDesc('esb_purchase_orders.purchase_date')
                ->select('esb_purchase_order_items.*')
                ->first();

            $row->latest_price = $latest?->normalizedNetPrice() ?? 0;
            $row->latest_supplier = $latest?->purchaseOrder?->supplier_name;
            $row->change_percent = (float) $row->average_price > 0
                ? (($row->latest_price - (float) $row->average_price) / (float) $row->average_price) * 100
                : 0;

            return $row;
        }));

        return $paginator;
    }

    public function lastSyncedAt(): ?Carbon
    {
        return EsbPurchaseOrder::query()->max('last_synced_at')
            ? Carbon::parse(EsbPurchaseOrder::query()->max('last_synced_at'))
            : null;
    }
}
