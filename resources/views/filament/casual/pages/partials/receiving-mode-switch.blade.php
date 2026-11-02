{{-- docs/products/inventory/non-po-receiving/prd.md §17: Dengan PO / Tanpa PO inside the Receiving tile. --}}
<nav aria-label="Mode Receiving" class="grid grid-cols-2 gap-1 rounded-2xl border border-gray-200 bg-white p-1 dark:border-gray-700 dark:bg-gray-900">
    @foreach (['po' => ['Dengan PO', \App\Filament\Casual\Pages\GoodsReceiptPage::getUrl(panel: 'casual')], 'non_po' => ['Tanpa PO', \App\Filament\Casual\Pages\NonPoReceiptPage::getUrl(panel: 'casual')]] as $mode => [$text, $url])
        <a href="{{ $url }}" @if ($mode === $activeMode) aria-current="page" @endif class="rounded-xl py-2.5 text-center text-sm font-semibold transition {{ $mode === $activeMode ? 'bg-blue-600 text-white' : 'text-slate-500 hover:bg-gray-50 dark:text-slate-300 dark:hover:bg-gray-800' }}">{{ $text }}</a>
    @endforeach
</nav>
