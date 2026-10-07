{{--
    PDF preview used by Project and Product pages. Expects the host page's $pdfPreview (title,
    preview_url, download_url) and a closePdfPreview() action. The PDF is
    rendered by the browser's own viewer; "Buka di Tab Baru" covers browsers that cannot show a PDF
    inside a frame (common on phones).
--}}
@if($pdfPreview)
    <div class="fixed inset-0 z-[140] flex items-center justify-center p-3 sm:p-6" x-data="{ loaded: false }" x-trap.inert.noscroll="true" x-on:keydown.escape.window="$wire.closePdfPreview()">
        <button type="button" wire:click="closePdfPreview" class="absolute inset-0 bg-gray-950/60" aria-label="Tutup preview PDF"></button>
        <div class="relative flex h-[calc(100dvh-1.5rem)] w-full max-w-5xl flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900 sm:h-[calc(100dvh-3rem)]" role="dialog" aria-modal="true" aria-labelledby="pdf-preview-heading">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                <div class="flex items-center gap-3">
                    <x-heroicon-o-document-text class="h-5 w-5 shrink-0 text-blue-600 dark:text-blue-400" aria-hidden="true" />
                    <h3 id="pdf-preview-heading" class="text-lg font-bold text-gray-900 dark:text-white">{{ $pdfPreview['title'] }}</h3>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ $pdfPreview['preview_url'] }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-2 text-sm font-bold text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
                        <x-heroicon-o-arrow-top-right-on-square class="h-4 w-4" aria-hidden="true" /> Buka di Tab Baru
                    </a>
                    <a href="{{ $pdfPreview['download_url'] }}" class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3 py-2 text-sm font-bold text-white transition hover:bg-blue-700">
                        <x-heroicon-o-arrow-down-tray class="h-4 w-4" aria-hidden="true" /> Download PDF
                    </a>
                    @if($canReleaseSop ?? false)
                        <button type="button" wire:click="openReleaseSopModal" class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-2 text-sm font-bold text-white transition hover:bg-emerald-700">
                            <x-heroicon-o-paper-airplane class="h-4 w-4" aria-hidden="true" /> Rilis SOP
                        </button>
                    @endif
                    @if($releasedSopUrl ?? null)
                        <a href="{{ $releasedSopUrl }}" class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-bold text-emerald-700 transition hover:bg-emerald-100 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300">
                            <x-heroicon-o-arrow-top-right-on-square class="h-4 w-4" aria-hidden="true" /> Lihat SOP
                        </a>
                    @endif
                    <button type="button" wire:click="closePdfPreview" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-200" aria-label="Tutup">
                        <x-heroicon-o-x-mark class="h-5 w-5" />
                    </button>
                </div>
            </div>
            <div class="relative min-h-0 flex-1 bg-gray-100 dark:bg-gray-950">
                <div x-show="! loaded" class="absolute inset-0 flex flex-col items-center justify-center gap-3 text-sm text-gray-500 dark:text-gray-400" role="status">
                    <span class="h-8 w-8 animate-spin rounded-full border-4 border-blue-200 border-t-blue-600" aria-hidden="true"></span>
                    Menyiapkan preview PDF...
                </div>
                <iframe src="{{ $pdfPreview['preview_url'] }}" title="{{ $pdfPreview['title'] }}" x-on:load="loaded = true" class="h-full w-full border-0"></iframe>
            </div>
        </div>
    </div>
@endif
