@php
    $viewUrl = \App\Filament\Helpdesk\Resources\MarketingDesignBundles\MarketingDesignBundleResource::getUrl('view', ['record' => $bundle]);
@endphp
<li wire:key="design-bundle-row-{{ $bundle->id }}" class="grid gap-3 p-4 transition hover:bg-gray-50 dark:hover:bg-gray-800/40 xl:items-center xl:px-5 xl:py-3 {{ $bundleRowGrid }}">
    <div class="min-w-0">
        <a href="{{ $viewUrl }}" class="text-sm font-bold text-gray-900 [overflow-wrap:anywhere] hover:text-blue-700 dark:text-white dark:hover:text-blue-300">{{ $bundle->name }}</a>
        <p class="mt-0.5 text-xs font-semibold text-gray-500 [overflow-wrap:anywhere] dark:text-gray-400">{{ $bundle->code }}</p>
        @if(filled($bundle->description))
            <p class="mt-0.5 line-clamp-2 text-xs leading-5 text-gray-500 [overflow-wrap:anywhere] dark:text-gray-400">{{ $bundle->description }}</p>
        @endif
    </div>
    <div class="grid grid-cols-2 gap-3 xl:contents">
        <div class="min-w-0">
            <p class="text-xs text-gray-400 xl:hidden">Brand</p>
            <p class="text-sm font-semibold text-gray-700 [overflow-wrap:anywhere] dark:text-gray-200">{{ $bundle->brand->name }}</p>
        </div>
        <div class="min-w-0">
            <p class="mb-1 text-xs text-gray-400 xl:hidden">Kategori</p>
            <span class="inline-flex w-fit rounded-full bg-blue-50 px-2.5 py-1 text-xs font-bold text-blue-700 dark:bg-blue-950/40 dark:text-blue-300">{{ $bundle->category->getLabel() }}</span>
        </div>
        <div class="min-w-0">
            <p class="text-xs text-gray-400 xl:hidden">Item</p>
            <p class="text-sm font-bold text-gray-900 dark:text-white">{{ $bundle->items_count }}</p>
        </div>
        <div class="min-w-0">
            <p class="text-xs text-gray-400 xl:hidden">Diperbarui</p>
            <p class="text-xs font-medium text-gray-700 dark:text-gray-200"><span class="whitespace-nowrap">{{ $bundle->updated_at->timezone('Asia/Jakarta')->format('d M Y, H:i') }}</span></p>
        </div>
    </div>
    <div class="flex items-center gap-2 xl:justify-end">
        <a href="{{ $viewUrl }}" aria-label="Buka Bundle {{ $bundle->name }}" title="Buka Bundle" class="inline-flex flex-1 items-center justify-center gap-2 rounded-lg bg-blue-600 px-3 py-2 text-sm font-bold text-white hover:bg-blue-700 xl:flex-none xl:p-2">
            <span class="xl:hidden">Buka Bundle</span>
            <x-heroicon-o-arrow-right class="h-4 w-4" />
        </a>
    </div>
</li>
