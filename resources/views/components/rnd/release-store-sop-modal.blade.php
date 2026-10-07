@props([
    'categoryOptions' => [],
    'brandOptions' => [],
    'branchOptions' => [],
    'selectedBrandId' => null,
])

<div class="fixed inset-0 z-[160] flex items-center justify-center p-3 sm:p-6" x-trap.inert.noscroll="true" x-on:keydown.escape.window="$wire.closeReleaseSopModal()">
    <button type="button" wire:click="closeReleaseSopModal" class="absolute inset-0 bg-gray-950/65" aria-label="Tutup form rilis SOP"></button>

    <form wire:submit="releaseBomToStoreSop" class="relative flex max-h-[calc(100dvh-1.5rem)] w-full max-w-3xl flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-gray-700 dark:bg-gray-900" role="dialog" aria-modal="true" aria-labelledby="release-sop-heading">
        <header class="flex items-start justify-between gap-4 border-b border-gray-200 px-5 py-4 dark:border-gray-700">
            <div>
                <h3 id="release-sop-heading" class="text-lg font-bold text-gray-900 dark:text-white">Rilis ke SOP Store</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">PDF sesuai BOM yang dipilih akan disimpan dan langsung dipublikasikan ke branch tujuan.</p>
            </div>
            <button type="button" wire:click="closeReleaseSopModal" class="shrink-0 rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-200" aria-label="Tutup">
                <x-heroicon-o-x-mark class="h-5 w-5" />
            </button>
        </header>

        <div class="min-h-0 flex-1 overflow-y-auto p-5">
            @error('releaseSop')<p class="mb-4 rounded-lg bg-red-50 px-3 py-2 text-sm font-semibold text-red-700 dark:bg-red-950/40 dark:text-red-300" role="alert">{{ $message }}</p>@enderror

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="release-sop-code" class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Nomor SOP</label>
                    <input id="release-sop-code" type="text" wire:model="releaseSopCode" maxlength="50" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm dark:border-gray-600 dark:bg-gray-800" required>
                    @error('releaseSopCode')<p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="release-sop-title" class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Judul SOP</label>
                    <input id="release-sop-title" type="text" wire:model="releaseSopTitle" maxlength="255" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm dark:border-gray-600 dark:bg-gray-800" required>
                    @error('releaseSopTitle')<p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="release-sop-category" class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Kategori SOP</label>
                    <select id="release-sop-category" wire:model="releaseSopCategoryId" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm dark:border-gray-600 dark:bg-gray-800" required>
                        <option value="">Pilih kategori</option>
                        @foreach($categoryOptions as $optionId => $optionName)
                            <option value="{{ $optionId }}">{{ $optionName }}</option>
                        @endforeach
                    </select>
                    @error('releaseSopCategoryId')<p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="release-sop-brand" class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Brand</label>
                    <select id="release-sop-brand" wire:model.live="releaseSopBrandId" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm dark:border-gray-600 dark:bg-gray-800" required>
                        <option value="">Pilih brand</option>
                        @foreach($brandOptions as $optionId => $optionName)
                            <option value="{{ $optionId }}">{{ $optionName }}</option>
                        @endforeach
                    </select>
                    @error('releaseSopBrandId')<p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
                </div>
                <div class="sm:col-span-2">
                    <label for="release-sop-branches" class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Target Branch</label>
                    <select id="release-sop-branches" wire:model="releaseSopBranchIds" multiple size="5" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800" @disabled($selectedBrandId === null) required>
                        @foreach($branchOptions as $optionId => $optionName)
                            <option value="{{ $optionId }}">{{ $optionName }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-500">Pilih satu atau beberapa branch. Pada desktop, gunakan Ctrl/Cmd untuk memilih lebih dari satu.</p>
                    @error('releaseSopBranchIds')<p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
                    @error('releaseSopBranchIds.*')<p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="release-sop-effective-date" class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Berlaku Mulai</label>
                    <input id="release-sop-effective-date" type="date" wire:model="releaseSopEffectiveDate" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm dark:border-gray-600 dark:bg-gray-800" required>
                    @error('releaseSopEffectiveDate')<p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="release-sop-expires-at" class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Berlaku Sampai</label>
                    <input id="release-sop-expires-at" type="date" wire:model="releaseSopExpiresAt" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm dark:border-gray-600 dark:bg-gray-800" required>
                    @error('releaseSopExpiresAt')<p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
                </div>
                <div class="sm:col-span-2">
                    <label for="release-sop-summary" class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Ringkasan</label>
                    <textarea id="release-sop-summary" wire:model="releaseSopSummary" rows="3" maxlength="3000" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm dark:border-gray-600 dark:bg-gray-800"></textarea>
                    @error('releaseSopSummary')<p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        <footer class="flex flex-col-reverse gap-2 border-t border-gray-200 px-5 py-4 dark:border-gray-700 sm:flex-row sm:justify-end">
            <button type="button" wire:click="closeReleaseSopModal" class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-bold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800">Batal</button>
            <button type="submit" wire:loading.attr="disabled" wire:target="releaseBomToStoreSop" class="inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-emerald-700 disabled:cursor-wait disabled:opacity-60">
                <x-heroicon-o-paper-airplane class="h-4 w-4" />
                <span wire:loading.remove wire:target="releaseBomToStoreSop">Rilis &amp; Publikasikan</span>
                <span wire:loading wire:target="releaseBomToStoreSop">Merilis...</span>
            </button>
        </footer>
    </form>
</div>
