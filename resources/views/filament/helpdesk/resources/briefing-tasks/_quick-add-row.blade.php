<tr class="bg-primary-50/40 dark:bg-primary-900/10">
    <td class="px-4 py-2.5" colspan="6">
        <div class="flex flex-wrap items-center gap-2">
            <input
                wire:model="quickAdd.label"
                wire:keydown.enter="saveQuickTask"
                wire:keydown.escape="cancelQuickAdd"
                type="text"
                placeholder="Nama poin baru…"
                autofocus
                class="flex-1 min-w-40 rounded-lg border border-primary-300 bg-white px-3 py-1.5 text-sm text-gray-800 placeholder-gray-400 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-primary-700 dark:bg-gray-900 dark:text-gray-200 dark:placeholder-gray-500"
            />

            {{-- Period shown as a fixed badge (context from section) --}}
            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset {{ $badge }}">
                {{ $period['label'] }}
            </span>

            <select
                wire:model="quickAdd.submission_type"
                class="rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-sm text-gray-700 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-white/10 dark:bg-gray-900 dark:text-gray-300"
            >
                <option value="checkbox">Centang Selesai</option>
                <option value="photo">Foto Bebas</option>
                <option value="camera_only">Foto Kamera</option>
                <option value="photo_and_text">Foto + Teks</option>
                <option value="text_only">Teks Saja</option>
            </select>

            <button
                wire:click="saveQuickTask"
                wire:loading.attr="disabled"
                class="inline-flex items-center gap-1 rounded-lg bg-primary-600 px-3 py-1.5 text-sm font-medium text-white transition-colors hover:bg-primary-700 disabled:opacity-60"
            >
                <span wire:loading.remove wire:target="saveQuickTask">Simpan</span>
                <span wire:loading wire:target="saveQuickTask">…</span>
            </button>

            <button
                wire:click="cancelQuickAdd"
                class="rounded-lg px-2.5 py-1.5 text-sm text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-white/10 dark:hover:text-gray-300"
            >
                Batal
            </button>
        </div>
        @error('quickAdd.label')
            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
        @enderror
    </td>
</tr>
