<?php

namespace App\Filament\Helpdesk\Pages;

use App\Models\EsbPurchaseOrderItem;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ProductPriceIndexDetailPage extends Page
{
    protected static ?string $slug = 'product-price-index/{productDetail}';

    protected static ?string $title = 'Detail Product Price';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.helpdesk.pages.product-price-index-detail';

    protected Width|string|null $maxContentWidth = Width::Full;

    public int $productDetailId;

    public string $dateFrom = '';

    public string $dateTo = '';

    public int $page = 1;

    public static function canAccess(): bool
    {
        return ProductPriceIndexPage::canAccess();
    }

    public function mount(int $productDetail): void
    {
        abort_unless(EsbPurchaseOrderItem::query()->where('product_detail_id', $productDetail)->exists(), 404);
        $this->productDetailId = $productDetail;
        $this->dateFrom = now()->subYear()->toDateString();
        $this->dateTo = now()->toDateString();
    }

    public function product(): EsbPurchaseOrderItem
    {
        return EsbPurchaseOrderItem::query()
            ->where('product_detail_id', $this->productDetailId)
            ->latest('id')
            ->firstOrFail();
    }

    public function history(): LengthAwarePaginator
    {
        return EsbPurchaseOrderItem::query()
            ->with('purchaseOrder')
            ->where('product_detail_id', $this->productDetailId)
            ->whereHas('purchaseOrder', fn (Builder $query) => $query
                ->when($this->dateFrom, fn (Builder $query) => $query->whereDate('purchase_date', '>=', $this->dateFrom))
                ->when($this->dateTo, fn (Builder $query) => $query->whereDate('purchase_date', '<=', $this->dateTo)))
            ->join('esb_purchase_orders', 'esb_purchase_orders.id', '=', 'esb_purchase_order_items.esb_purchase_order_id')
            ->orderByDesc('esb_purchase_orders.purchase_date')
            ->select('esb_purchase_order_items.*')
            ->paginate(20, ['*'], 'page', $this->page);
    }

    public function stats(): array
    {
        $rows = EsbPurchaseOrderItem::query()
            ->with('purchaseOrder')
            ->where('product_detail_id', $this->productDetailId)
            ->whereHas('purchaseOrder', fn (Builder $query) => $query
                ->when($this->dateFrom, fn (Builder $query) => $query->whereDate('purchase_date', '>=', $this->dateFrom))
                ->when($this->dateTo, fn (Builder $query) => $query->whereDate('purchase_date', '<=', $this->dateTo)))
            ->get()
            ->sortByDesc(fn ($row) => $row->purchaseOrder?->purchase_date?->timestamp ?? 0)
            ->values();
        $prices = $rows->map->normalizedNetPrice()->filter(fn (float $price) => $price > 0);
        $net = $rows->sum(fn ($row): float => ((float) $row->total - (float) $row->vat) * (float) ($row->purchaseOrder?->rate ?: 1));
        $qty = $rows->sum(fn ($row): float => (float) $row->stock_qty > 0
            ? (float) $row->stock_qty
            : (float) $row->qty * max(1, (float) $row->conversion_qty));

        return [
            'average' => $qty > 0 ? $net / $qty : 0,
            'minimum' => $prices->min() ?? 0,
            'maximum' => $prices->max() ?? 0,
            'latest' => $prices->first() ?? 0,
            'po_count' => $rows->pluck('esb_purchase_order_id')->unique()->count(),
        ];
    }
}
