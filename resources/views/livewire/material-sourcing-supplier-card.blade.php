@php
    $isSelected = $sourcing->material?->sourcing_selected_id === $sourcing->id;
@endphp

<div @class([
    'rounded-xl border p-4 shadow-sm transition-all',
    'border-success-400 bg-success-50/70 ring-1 ring-success-200 dark:bg-success-950/20 dark:ring-success-900' => $isSelected,
    'border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900' => ! $isSelected,
])>
    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
        <div class="flex items-center gap-2">
            <p class="text-sm font-semibold text-gray-900 dark:text-white">
                {{ $sourcing->supplier_name }}
            </p>
            @if ($isSelected)
                <x-filament::badge color="success" size="sm">Supplier Terpilih</x-filament::badge>
            @endif
        </div>
        <div class="flex items-center gap-2.5">
            <p class="whitespace-nowrap text-base font-bold text-primary-700 dark:text-primary-300">
                Rp{{ number_format((float) $sourcing->price, 0, ',', '.') }}
            </p>
            @can('submit material sourcing')
                <button
                    type="button"
                    wire:click="toggleEdit"
                    @class([
                        'inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-xs font-semibold transition',
                        'border-gray-300 bg-gray-100 text-gray-700 hover:bg-gray-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700' => $editing,
                        'border-primary-200 bg-primary-50 text-primary-700 hover:bg-primary-100 dark:border-primary-800 dark:bg-primary-950/40 dark:text-primary-300 dark:hover:bg-primary-900/60' => ! $editing,
                    ])
                >
                    @if ($editing)
                        <x-heroicon-m-chevron-up class="h-3.5 w-3.5" />
                        Tutup
                    @else
                        <x-heroicon-o-pencil-square class="h-3.5 w-3.5" />
                        Edit
                    @endif
                </button>
            @endcan
        </div>
    </div>

    <dl class="mt-3 grid grid-cols-2 gap-3 text-xs text-gray-600 dark:text-gray-300 sm:grid-cols-5">
        <div class="rounded-lg bg-gray-50 p-2 dark:bg-white/5">
            <dt class="text-gray-400">Merk</dt>
            <dd class="mt-0.5 font-medium text-gray-800 dark:text-gray-200">{{ $sourcing->brand ?: '—' }}</dd>
        </div>
        <div class="rounded-lg bg-gray-50 p-2 dark:bg-white/5">
            <dt class="text-gray-400">MOQ</dt>
            <dd class="mt-0.5 font-medium text-gray-800 dark:text-gray-200">{{ $sourcing->moq ?: '—' }}</dd>
        </div>
        <div class="rounded-lg bg-gray-50 p-2 dark:bg-white/5">
            <dt class="text-gray-400">Lead Time</dt>
            <dd class="mt-0.5 font-medium text-gray-800 dark:text-gray-200">{{ $sourcing->lead_time_days ? $sourcing->lead_time_days.' hari' : '—' }}</dd>
        </div>
        <div class="rounded-lg bg-gray-50 p-2 dark:bg-white/5">
            <dt class="text-gray-400">Kontak</dt>
            <dd class="mt-0.5 font-medium text-gray-800 dark:text-gray-200">{{ $sourcing->contact_name ?: '—' }}</dd>
        </div>
        <div class="rounded-lg bg-gray-50 p-2 dark:bg-white/5">
            <dt class="text-gray-400">Telepon</dt>
            <dd class="mt-0.5 font-medium text-gray-800 dark:text-gray-200">{{ $sourcing->contact_phone ?: '—' }}</dd>
        </div>
    </dl>

    @if ($sourcing->notes)
        <p class="mt-2 text-xs text-gray-600 dark:text-gray-300">{{ $sourcing->notes }}</p>
    @endif

    @if ($sourcing->attachmentUrl())
        <a
            href="{{ $sourcing->attachmentUrl() }}"
            target="_blank"
            class="mt-3 inline-flex items-center gap-1.5 rounded-lg border border-primary-200 bg-primary-50 px-3 py-2 text-xs font-semibold text-primary-700 transition hover:bg-primary-100 dark:border-primary-800 dark:bg-primary-950/40 dark:text-primary-300"
        >
            <x-heroicon-o-paper-clip class="h-3.5 w-3.5" />
            Lihat Lampiran
        </a>
    @endif

    @if ($editing)
        <div class="mt-4 border-t border-gray-200 pt-4 dark:border-gray-700">
            <div class="mb-3 flex items-center justify-between">
                <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Edit Data Supplier</h4>
            </div>

            <form wire:submit="save" class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">
                        Nama Supplier <span class="text-red-500">*</span>
                    </label>
                    <input
                        wire:model="supplierName"
                        type="text"
                        class="mt-1 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs text-gray-900 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                        placeholder="Contoh: PT Sumber Pangan"
                    />
                    @error('supplierName')
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">Merk</label>
                    <input
                        wire:model="brand"
                        type="text"
                        class="mt-1 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs text-gray-900 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                        placeholder="Contoh: Segitiga Biru"
                    />
                    @error('brand')
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">
                        Harga (Rp) <span class="text-red-500">*</span>
                    </label>
                    <input
                        wire:model="price"
                        type="number"
                        min="0"
                        step="any"
                        class="mt-1 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs text-gray-900 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                        placeholder="Contoh: 15000"
                    />
                    @error('price')
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">MOQ</label>
                    <input
                        wire:model="moq"
                        type="text"
                        class="mt-1 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs text-gray-900 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                        placeholder="Contoh: 100 kg / 10 sak"
                    />
                    @error('moq')
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">Lead Time (hari)</label>
                    <input
                        wire:model="leadTimeDays"
                        type="number"
                        min="0"
                        class="mt-1 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs text-gray-900 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                        placeholder="Contoh: 7"
                    />
                    @error('leadTimeDays')
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">Nama Kontak</label>
                    <input
                        wire:model="contactName"
                        type="text"
                        class="mt-1 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs text-gray-900 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                        placeholder="Contoh: Bpk. Budi"
                    />
                    @error('contactName')
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">Telepon Kontak</label>
                    <input
                        wire:model="contactPhone"
                        type="text"
                        class="mt-1 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs text-gray-900 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                        placeholder="Contoh: 08123456789"
                    />
                    @error('contactPhone')
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">
                        {{ $sourcing->attachment_path ? 'Ganti Lampiran' : 'Unggah Lampiran' }}
                    </label>
                    <input
                        wire:model="newAttachment"
                        type="file"
                        accept=".jpg,.jpeg,.png,.webp,.pdf"
                        class="mt-1 block w-full text-xs text-gray-500 file:mr-2 file:rounded-md file:border-0 file:bg-primary-50 file:px-2.5 file:py-1.5 file:text-xs file:font-semibold file:text-primary-700 hover:file:bg-primary-100 dark:text-gray-400 dark:file:bg-primary-950/40 dark:file:text-primary-300"
                    />
                    @if ($sourcing->attachmentUrl())
                        <span class="mt-1 block text-[11px] text-gray-500 dark:text-gray-400">
                            Lampiran saat ini tersimpan. Biarkan kosong jika tidak ingin mengubah.
                        </span>
                    @endif
                    @error('newAttachment')
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">Catatan</label>
                    <textarea
                        wire:model="notes"
                        rows="2"
                        class="mt-1 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs text-gray-900 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                        placeholder="Catatan tambahan..."
                    ></textarea>
                    @error('notes')
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 sm:col-span-2">
                    <button
                        type="button"
                        wire:click="toggleEdit"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        wire:target="save, newAttachment"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-3.5 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-primary-500 disabled:opacity-50"
                    >
                        <span wire:loading.remove wire:target="save">Simpan</span>
                        <span wire:loading wire:target="save" class="inline-flex items-center gap-1">
                            <svg class="h-3.5 w-3.5 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Menyimpan...
                        </span>
                    </button>
                </div>
            </form>
        </div>
    @endif
</div>
