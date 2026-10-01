<x-filament-panels::page>
    @php
        $inputClass = 'w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white';
    @endphp
    <div class="space-y-6">
        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <div class="flex flex-col justify-between gap-5 p-5 sm:p-6 md:flex-row md:items-center">
                <div class="flex min-w-0 items-start gap-4">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-950/40 dark:text-blue-300">
                        <x-heroicon-o-document-text class="h-6 w-6" />
                    </div>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider text-blue-600 dark:text-blue-400">Research &amp; Development</p>
                        <h2 class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">Memo Internal</h2>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-gray-500 dark:text-gray-400">Daftar Menu yang akan dirilis beserta kebutuhan Bahan dan WIP berdasarkan Branch Tujuan.</p>
                    </div>
                </div>
                @if(\App\Filament\Helpdesk\Resources\RndInternalMemos\RndInternalMemoResource::canCreate())
                    <button type="button" wire:click="openCreateModal" class="inline-flex shrink-0 items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-blue-700">
                        <x-heroicon-o-plus class="h-5 w-5" /> Buat Memo
                    </button>
                @endif
            </div>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900 sm:p-5">
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-500 dark:text-gray-400">Cari</label>
                    <input wire:model.live.debounce.400ms="search" class="{{ $inputClass }}" placeholder="Nomor atau judul memo...">
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-500 dark:text-gray-400">Periode</label>
                    <input type="month" wire:model.live="periodFilter" class="{{ $inputClass }}">
                </div>
            </div>
        </section>

        <section class="space-y-3">
            @forelse($this->memos() as $memo)
                <div wire:key="memo-{{ $memo->id }}" class="flex flex-col gap-3 rounded-2xl border border-gray-200 bg-white p-4 transition hover:border-blue-400 dark:border-gray-700 dark:bg-gray-900 dark:hover:border-blue-600 sm:flex-row sm:items-center sm:justify-between">
                    <a href="{{ \App\Filament\Helpdesk\Resources\RndInternalMemos\RndInternalMemoResource::getUrl('view', ['record' => $memo]) }}" class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-mono text-xs font-bold text-blue-600 dark:text-blue-400">{{ $memo->memo_number }}</span>
                        </div>
                        <h3 class="mt-1 truncate text-base font-bold text-gray-900 dark:text-white">{{ $memo->title }}</h3>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Periode {{ $memo->period_month->translatedFormat('F Y') }} · {{ $memo->menus_count }} Menu · Diperbarui {{ $memo->updated_at->diffForHumans() }}</p>
                    </a>
                    <div class="flex shrink-0 items-center gap-2">
                        <a href="{{ \App\Filament\Helpdesk\Resources\RndInternalMemos\RndInternalMemoResource::getUrl('view', ['record' => $memo]) }}" class="rounded-lg border border-gray-300 p-2 text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800" title="Buka Memo" aria-label="Buka Memo {{ $memo->title }}">
                            <x-heroicon-o-arrow-right class="h-4 w-4" />
                        </a>
                        @if(\App\Filament\Helpdesk\Resources\RndInternalMemos\RndInternalMemoResource::canDelete($memo))
                            <button type="button" x-on:click="window.BloomeryConfirm.show({ title: 'Hapus Memo?', text: @js('Memo '.$memo->title.' akan dihapus.'), confirmText: 'Hapus Memo' }).then((confirmed) => { if (confirmed) $wire.deleteMemo({{ $memo->id }}) })" class="rounded-lg border border-red-200 p-2 text-red-600 hover:bg-red-50 dark:border-red-900 dark:text-red-300 dark:hover:bg-red-950/30" title="Hapus Memo" aria-label="Hapus Memo {{ $memo->title }}">
                                <x-heroicon-o-trash class="h-4 w-4" />
                            </button>
                        @endif
                    </div>
                </div>
            @empty
                <div class="rounded-2xl border border-dashed border-gray-300 p-10 text-center dark:border-gray-700">
                    <x-heroicon-o-document-text class="mx-auto h-10 w-10 text-gray-300" />
                    <h4 class="mt-3 font-bold text-gray-700 dark:text-gray-200">Belum ada Memo Internal</h4>
                    <p class="mt-1 text-sm text-gray-500">Sesuaikan filter atau buat Memo baru.</p>
                </div>
            @endforelse
        </section>
    </div>

    @if($createModalOpen)
        <div class="fixed inset-0 z-[130] flex items-center justify-center p-4" x-data x-trap.inert.noscroll="true" x-on:keydown.escape.window="$wire.closeCreateModal()">
            <button type="button" wire:click="closeCreateModal" class="absolute inset-0 bg-gray-950/60" aria-label="Tutup modal buat memo"></button>
            <form wire:submit="createMemo" class="relative flex max-h-[90vh] w-full max-w-lg flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900" role="dialog" aria-modal="true" aria-label="Buat Memo Internal">
                <div class="flex items-start justify-between gap-4 border-b border-gray-200 p-5 dark:border-gray-700">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">Buat Memo Internal</h3>
                        <p class="mt-1 text-sm text-gray-500">Company Code dan Branch Code mengikuti mapping ESB Branch Tujuan yang dipilih.</p>
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
                        <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Branch Tujuan *</label>
                        <select wire:model="branchIds" multiple size="5" class="{{ $inputClass }}">
                            @foreach($this->branchOptions() as $option)
                                @php $resolved = $option['resolution']->isResolved(); @endphp
                                <option value="{{ $option['branch']->id }}" @disabled(! $resolved)>
                                    {{ $option['branch']->name }}
                                    @if($resolved)
                                        — {{ $option['resolution']->mapping->esb_comcode }} · {{ $option['resolution']->mapping->esb_branch_code }}
                                    @else
                                        ({{ $option['resolution']->blockedReason }})
                                    @endif
                                </option>
                            @endforeach
                        </select>
                        @error('branchIds')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        @error('branchIds.*')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        <p class="mt-1 text-[11px] text-gray-400">Tahan Ctrl/Cmd untuk memilih lebih dari satu Branch.</p>
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
