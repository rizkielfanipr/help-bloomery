@if($copyModalOpen)
    <div class="fixed inset-0 z-[130] flex items-center justify-center p-4" x-data x-trap.inert.noscroll="true" x-on:keydown.escape.window="$wire.closeCopyTaskModal()">
        <button type="button" wire:click="closeCopyTaskModal" class="absolute inset-0 bg-gray-950/60" aria-label="Tutup modal Copy Task"></button>
        <form wire:submit="copyTask" class="relative flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900" role="dialog" aria-modal="true" aria-labelledby="copy-task-heading">
            <div class="flex items-start justify-between gap-4 border-b border-gray-200 p-5 dark:border-gray-700">
                <div>
                    <h3 id="copy-task-heading" class="text-lg font-bold text-gray-900 dark:text-white">Copy Task</h3>
                    <p class="mt-1 text-sm text-gray-500">Task baru dibuat di Project yang sama dengan status awal dan PIC yang Anda konfirmasi.</p>
                </div>
                <button type="button" wire:click="closeCopyTaskModal" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-200" aria-label="Tutup">
                    <x-heroicon-o-x-mark class="h-5 w-5" />
                </button>
            </div>
            <div class="space-y-4 overflow-y-auto p-5">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label for="copy-title" class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Nama Task *</label>
                        <input id="copy-title" wire:model="copyTitle" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                        @error('copyTitle')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="copy-assigned-date" class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Tanggal Assign *</label>
                        <input id="copy-assigned-date" type="date" wire:model.live="copyAssignedDate" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                        @error('copyAssignedDate')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="copy-due-date" class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Deadline *</label>
                        <input id="copy-due-date" type="date" wire:model="copyDueDate" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                        <p class="mt-1 text-[11px] text-gray-400">Default mempertahankan durasi Task sumber.</p>
                        @error('copyDueDate')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>

                @include('filament.helpdesk.rnd-projects.partials.task-branch-rows', ['rowsProperty' => 'copyBranchRows', 'rows' => $copyBranchRows])

                <div class="rounded-xl border border-gray-200 bg-gray-50 p-3.5 text-xs text-gray-600 dark:border-gray-700 dark:bg-gray-800/60 dark:text-gray-300">
                    <p class="font-bold text-gray-700 dark:text-gray-200">Yang disalin: kategori, catatan, dan prioritas.</p>
                    <p class="mt-1">Tidak disalin: status, progress, tindak lanjut, review, reminder, data selesai, histori, dan attachment.</p>
                </div>
            </div>
            <div class="flex justify-end gap-2 border-t border-gray-200 p-5 dark:border-gray-700">
                <button type="button" wire:click="closeCopyTaskModal" class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-bold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800">Batal</button>
                <button type="submit" wire:loading.attr="disabled" wire:target="copyTask" class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-50">
                    <span wire:loading.remove wire:target="copyTask">Buat Salinan</span>
                    <span wire:loading wire:target="copyTask">Menyimpan...</span>
                </button>
            </div>
        </form>
    </div>
@endif
