@props(['paginator', 'previousMethod', 'nextMethod', 'goToMethod', 'label' => null])

@php
    $current = $paginator->currentPage();
    $last = $paginator->lastPage();
    $delta = 1;

    $pages = [];
    for ($i = 1; $i <= $last; $i++) {
        if ($i === 1 || $i === $last || ($i >= $current - $delta && $i <= $current + $delta)) {
            $pages[] = $i;
        }
    }

    $items = [];
    $previousPageNumber = null;
    foreach ($pages as $pageNumber) {
        if ($previousPageNumber !== null && $pageNumber - $previousPageNumber > 1) {
            $items[] = '...';
        }
        $items[] = $pageNumber;
        $previousPageNumber = $pageNumber;
    }
@endphp

<div class="flex flex-wrap items-center justify-between gap-3 text-xs text-gray-500 dark:text-gray-400">
    <span>Halaman {{ $current }} / {{ $last }}@if($label) &middot; {{ number_format($paginator->total()) }} {{ $label }}@endif</span>
    <div class="flex items-center gap-1">
        <button type="button" wire:click="{{ $previousMethod }}" @disabled($paginator->onFirstPage()) class="rounded-lg border border-gray-300 p-1.5 disabled:opacity-40 dark:border-gray-600" aria-label="Halaman sebelumnya">
            <x-heroicon-o-chevron-left class="h-4 w-4" />
        </button>
        @foreach($items as $item)
            @if($item === '...')
                <span class="px-1 text-gray-400">&hellip;</span>
            @else
                <button
                    type="button"
                    wire:click="{{ $goToMethod }}({{ $item }})"
                    @if($item === $current) aria-current="page" @endif
                    @class([
                        'min-w-[1.75rem] rounded-lg border px-2 py-1.5 text-xs font-semibold transition',
                        'border-blue-600 bg-blue-600 text-white' => $item === $current,
                        'border-gray-300 text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800' => $item !== $current,
                    ])
                >{{ $item }}</button>
            @endif
        @endforeach
        <button type="button" wire:click="{{ $nextMethod }}" @disabled(! $paginator->hasMorePages()) class="rounded-lg border border-gray-300 p-1.5 disabled:opacity-40 dark:border-gray-600" aria-label="Halaman berikutnya">
            <x-heroicon-o-chevron-right class="h-4 w-4" />
        </button>
    </div>
</div>
