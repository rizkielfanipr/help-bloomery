<x-filament-panels::page>
    @php
        $inputClass = 'w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white';
        $brandOptions = $this->brandOptions();
    @endphp
    @php
        $memos = $this->memos();
    @endphp
    <div class="space-y-6">
        <section class="rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                <div class="flex items-center gap-3">
                    <x-heroicon-o-document-text class="h-5 w-5 shrink-0 text-blue-600 dark:text-blue-400" aria-hidden="true" />
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">Memo Internal</h2>
                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-semibold text-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ $memos->count() }} Memo</span>
                </div>
                @if(\App\Filament\Helpdesk\Resources\RndInternalMemos\RndInternalMemoResource::canCreate())
                    <button type="button" wire:click="openCreateModal" wire:loading.attr="disabled" wire:target="openCreateModal" class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3 py-2 text-sm font-bold text-white transition hover:bg-blue-700 disabled:opacity-50">
                        <x-heroicon-o-plus class="h-4 w-4" aria-hidden="true" /> Buat Memo
                    </button>
                @endif
            </div>

            <div class="space-y-4 p-5">
                <div class="grid gap-3 sm:grid-cols-3">
                    <div>
                        <label for="memo-search" class="mb-1.5 block text-xs font-semibold text-gray-500 dark:text-gray-400">Cari</label>
                        <input id="memo-search" type="search" wire:model.live.debounce.400ms="search" class="{{ $inputClass }}" placeholder="Nomor, nama Memo, atau Brand...">
                    </div>
                    <div>
                        <label for="memo-period-filter" class="mb-1.5 block text-xs font-semibold text-gray-500 dark:text-gray-400">Bulan Memo</label>
                        <input id="memo-period-filter" type="month" wire:model.live="periodFilter" class="{{ $inputClass }}">
                    </div>
                    <div>
                        <label for="memo-brand-filter" class="mb-1.5 block text-xs font-semibold text-gray-500 dark:text-gray-400">Brand</label>
                        <select id="memo-brand-filter" wire:model.live="brandFilter" class="{{ $inputClass }}">
                            <option value="">Semua Brand</option>
                            @foreach($brandOptions as $brandOption)
                                <option value="{{ $brandOption->id }}">{{ $brandOption->name }}</option>
                            @endforeach
                            <option value="unresolved">Brand belum ditentukan</option>
                        </select>
                    </div>
                </div>

                <div class="relative">
                    <div wire:loading.flex wire:target="search,periodFilter,brandFilter,deleteMemo" class="absolute inset-0 z-10 hidden items-center justify-center rounded-xl bg-white/70 dark:bg-gray-900/70" role="status">
                        <span class="h-6 w-6 animate-spin rounded-full border-2 border-blue-200 border-t-blue-600" aria-hidden="true"></span>
                        <span class="sr-only">Memuat Memo</span>
                    </div>
                    @if($memos->isEmpty())
                        <div class="rounded-xl border border-dashed border-gray-300 p-8 text-center dark:border-gray-700">
                            <x-heroicon-o-document-text class="mx-auto h-10 w-10 text-gray-300" aria-hidden="true" />
                            <h4 class="mt-3 font-bold text-gray-700 dark:text-gray-200">Belum ada Memo Internal</h4>
                            <p class="mt-1 text-sm text-gray-500">Sesuaikan filter atau buat Memo baru.</p>
                        </div>
                    @else
                        <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
                            <table class="w-full min-w-[640px] text-sm">
                                <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-800">
                                    <tr>
                                        <th class="px-4 py-3 text-left">Memo</th>
                                        <th class="px-4 py-3 text-left">Bulan Memo</th>
                                        <th class="px-4 py-3 text-left">Brand</th>
                                        <th class="px-4 py-3 text-left">Diperbarui</th>
                                        <th class="px-4 py-3 text-right"><span class="sr-only">Aksi</span></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                    @foreach($memos as $memo)
                                        @php
                                            $brandLabel = $memo->brandLabel();
                                            $memoUrl = \App\Filament\Helpdesk\Resources\RndInternalMemos\RndInternalMemoResource::getUrl('view', ['record' => $memo]);
                                        @endphp
                                        <tr wire:key="memo-{{ $memo->id }}" class="transition hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                            <td class="px-4 py-3">
                                                <a href="{{ $memoUrl }}" class="font-semibold text-gray-900 hover:text-blue-700 hover:underline dark:text-white dark:hover:text-blue-300">{{ $memo->title }}</a>
                                                <p class="font-mono text-xs text-gray-400">{{ $memo->memo_number }}</p>
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-3 text-gray-700 dark:text-gray-300">{{ $memo->period_month->translatedFormat('F Y') }}</td>
                                            <td class="px-4 py-3">
                                                @if($brandLabel)
                                                    <span class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2 py-0.5 text-[11px] font-semibold text-blue-700 dark:bg-blue-950/40 dark:text-blue-300"><x-heroicon-o-tag class="h-3 w-3" aria-hidden="true" /> {{ $brandLabel }}</span>
                                                @else
                                                    <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-700 dark:bg-amber-950/40 dark:text-amber-300"><x-heroicon-o-exclamation-triangle class="h-3 w-3" aria-hidden="true" /> Brand belum ditentukan</span>
                                                @endif
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-3 text-xs text-gray-500 dark:text-gray-400">{{ $memo->updated_at->diffForHumans() }}</td>
                                            <td class="px-4 py-3">
                                                <div class="flex items-center justify-end gap-1">
                                                    <a href="{{ $memoUrl }}" title="Buka Memo" aria-label="Buka Memo {{ $memo->title }}" class="inline-flex rounded-lg p-1.5 text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-950/40">
                                                        <x-heroicon-o-arrow-right class="h-4 w-4" aria-hidden="true" />
                                                    </a>
                                                    @if(\App\Filament\Helpdesk\Resources\RndInternalMemos\RndInternalMemoResource::canDelete($memo))
                                                        <button type="button" x-on:click="window.BloomeryConfirm.show({ title: 'Hapus Memo?', text: @js('Memo '.$memo->title.' akan dihapus.'), confirmText: 'Hapus Memo' }).then((confirmed) => { if (confirmed) $wire.deleteMemo({{ $memo->id }}) })" title="Hapus Memo" aria-label="Hapus Memo {{ $memo->title }}" class="inline-flex rounded-lg p-1.5 text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/40">
                                                            <x-heroicon-o-trash class="h-4 w-4" aria-hidden="true" />
                                                        </button>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </section>
    </div>

    @if($createModalOpen)
        <div class="fixed inset-0 z-[130] flex items-center justify-center p-4" x-data x-trap.inert.noscroll="true" x-on:keydown.escape.window="$wire.closeCreateModal()">
            <button type="button" wire:click="closeCreateModal" class="absolute inset-0 bg-gray-950/60" aria-label="Tutup modal buat memo"></button>
            <form wire:submit="createMemo" class="relative flex max-h-[90vh] w-full max-w-lg flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900" role="dialog" aria-modal="true" aria-label="Buat Memo Internal">
                <div class="flex items-start justify-between gap-4 border-b border-gray-200 p-5 dark:border-gray-700">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">Buat Memo Internal</h3>
                        <p class="mt-1 text-sm text-gray-500">Brand menjadi identitas Memo. Data Menu dan BOM selalu bersumber dari ESB BLSS.</p>
                    </div>
                    <button type="button" wire:click="closeCreateModal" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-200" aria-label="Tutup">
                        <x-heroicon-o-x-mark class="h-5 w-5" />
                    </button>
                </div>
                <div class="space-y-4 overflow-y-auto p-5">
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Nama Memo *</label>
                        <input wire:model="memoTitle" class="{{ $inputClass }}" placeholder="mis. Rilis Menu September 2026">
                        @error('memoTitle')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Bulan Memo *</label>
                        <input type="month" wire:model="periodMonth" class="{{ $inputClass }}">
                        @error('periodMonth')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <div class="mb-1.5 flex items-center justify-between gap-2">
                            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200">Nomor Memo *</label>
                            @if($memoNumberGenerated)
                                <button type="button" wire:click="useManualMemoNumber" class="text-xs font-bold text-blue-700 hover:underline dark:text-blue-300">Isi Manual</button>
                            @else
                                <button type="button" wire:click="generateMemoNumberField" wire:loading.attr="disabled" wire:target="generateMemoNumberField" class="inline-flex items-center gap-1 text-xs font-bold text-blue-700 hover:underline disabled:opacity-50 dark:text-blue-300">
                                    <x-heroicon-o-sparkles class="h-3.5 w-3.5" /> Generate
                                </button>
                            @endif
                        </div>
                        <input wire:model="memoNumber" @if($memoNumberGenerated) readonly @endif placeholder="mis. 001/RND/IX/2026" class="{{ $inputClass }} {{ $memoNumberGenerated ? 'cursor-not-allowed bg-gray-50 dark:bg-gray-800/60' : '' }}">
                        @error('memoNumber')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        <p class="mt-1 text-[11px] text-gray-400">Isi manual, atau tekan Generate setelah Bulan Memo dipilih.</p>
                    </div>
                    <div>
                        <label for="create-memo-brand" class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Brand *</label>
                        <select id="create-memo-brand" wire:model="brandId" required @error('brandId') aria-invalid="true" aria-describedby="create-memo-brand-error" @enderror class="{{ $inputClass }}">
                            <option value="">Pilih Brand</option>
                            @foreach($brandOptions as $brandOption)
                                <option value="{{ $brandOption->id }}">{{ $brandOption->name }}</option>
                            @endforeach
                        </select>
                        @error('brandId')<p id="create-memo-brand-error" class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        @if($brandOptions->isEmpty())
                            <p class="mt-1 text-[11px] text-amber-600 dark:text-amber-400">Belum ada Master Brand. Tambahkan Brand terlebih dahulu.</p>
                        @endif
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Catatan</label>
                        <textarea wire:model="notes" rows="3" class="{{ $inputClass }}"></textarea>
                        @error('notes')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div class="flex justify-end gap-2 border-t border-gray-200 p-5 dark:border-gray-700">
                    <button type="button" wire:click="closeCreateModal" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Batal</button>
                    <button type="submit" wire:loading.attr="disabled" wire:target="createMemo" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-50">
                        <span wire:loading.remove wire:target="createMemo">Simpan</span>
                        <span wire:loading wire:target="createMemo">Menyimpan...</span>
                    </button>
                </div>
            </form>
        </div>
    @endif
</x-filament-panels::page>
