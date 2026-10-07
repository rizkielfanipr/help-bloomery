@props([
    'preview' => null,
    'previewKey' => 'pdf-preview',
])

<section class="flex min-h-[28rem] min-w-0 flex-1 flex-col overflow-hidden bg-gray-100 dark:bg-gray-950 lg:min-h-0" aria-label="Preview PDF">
    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 bg-white px-4 py-3 dark:border-gray-700 dark:bg-gray-900">
        <div class="flex min-w-0 items-center gap-2">
            <x-heroicon-o-document-text class="h-5 w-5 shrink-0 text-blue-600 dark:text-blue-400" aria-hidden="true" />
            <p class="truncate text-sm font-bold text-gray-900 dark:text-white">{{ $preview['title'] ?? 'Preview PDF' }}</p>
        </div>

        @if($preview)
            <a href="{{ $preview['preview_url'] }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-2 text-xs font-bold text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
                <x-heroicon-o-arrow-top-right-on-square class="h-4 w-4" aria-hidden="true" />
                Buka di Tab Baru
            </a>
        @endif
    </div>

    @if($preview)
        <div wire:key="{{ $previewKey }}" class="relative min-h-0 flex-1" x-data="{ loaded: false }">
            <div x-show="! loaded" class="absolute inset-0 z-10 flex flex-col items-center justify-center gap-3 bg-gray-100 text-sm text-gray-500 dark:bg-gray-950 dark:text-gray-400" role="status">
                <span class="h-8 w-8 animate-spin rounded-full border-4 border-blue-200 border-t-blue-600" aria-hidden="true"></span>
                Memperbarui preview PDF...
            </div>
            <iframe src="{{ $preview['preview_url'] }}" title="{{ $preview['title'] }}" x-on:load="loaded = true" class="h-full min-h-[28rem] w-full border-0 lg:min-h-0"></iframe>
        </div>
    @else
        <div class="flex min-h-[28rem] flex-1 flex-col items-center justify-center gap-3 px-6 text-center text-gray-500 dark:text-gray-400">
            <x-heroicon-o-document-magnifying-glass class="h-10 w-10 text-gray-300 dark:text-gray-600" aria-hidden="true" />
            <div>
                <p class="text-sm font-bold text-gray-700 dark:text-gray-200">Belum ada BOM yang dipilih</p>
                <p class="mt-1 text-xs">Centang minimal satu resep untuk menampilkan preview.</p>
            </div>
        </div>
    @endif
</section>
