<x-filament-panels::page>
    @php
        $summary = $this->summary();
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
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-gray-500 dark:text-gray-400">Memo bulanan berisi Menu yang akan dirilis, Shelf Life, dan forecast bahan dari BOM Menu dan Assembly Company Code BLSS.</p>
                    </div>
                </div>
                @if(\App\Filament\Helpdesk\Resources\RndInternalMemos\RndInternalMemoResource::canCreate())
                    <button type="button" wire:click="openCreateModal" class="inline-flex shrink-0 items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-blue-700">
                        <x-heroicon-o-plus class="h-5 w-5" /> Buat Memo
                    </button>
                @endif
            </div>
        </section>

        <section class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-xs font-semibold text-gray-500 dark:text-gray-400">Draft</p>
                <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ $summary['draft'] }}</p>
            </div>
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-900 dark:bg-amber-950/30">
                <p class="text-xs font-semibold text-amber-700 dark:text-amber-300">Needs Attention</p>
                <p class="mt-1 text-2xl font-bold text-amber-900 dark:text-amber-100">{{ $summary['needs_attention'] }}</p>
            </div>
            <div class="rounded-xl border border-blue-200 bg-blue-50 p-4 dark:border-blue-900 dark:bg-blue-950/30">
                <p class="text-xs font-semibold text-blue-700 dark:text-blue-300">Ready</p>
                <p class="mt-1 text-2xl font-bold text-blue-900 dark:text-blue-100">{{ $summary['ready'] }}</p>
            </div>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-900 dark:bg-emerald-950/30">
                <p class="text-xs font-semibold text-emerald-700 dark:text-emerald-300">Finalized</p>
                <p class="mt-1 text-2xl font-bold text-emerald-900 dark:text-emerald-100">{{ $summary['finalized'] }}</p>
            </div>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900 sm:p-5">
            <div class="grid gap-3 sm:grid-cols-3">
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-500 dark:text-gray-400">Cari</label>
                    <input wire:model.live.debounce.400ms="search" class="{{ $inputClass }}" placeholder="Nomor atau judul memo...">
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-500 dark:text-gray-400">Periode</label>
                    <input type="month" wire:model.live="periodFilter" class="{{ $inputClass }}">
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-500 dark:text-gray-400">Status</label>
                    <select wire:model.live="statusFilter" class="{{ $inputClass }}">
                        <option value="">Semua Status</option>
                        @foreach(\App\Enums\RndInternalMemoStatus::cases() as $status)
                            <option value="{{ $status->value }}">{{ $status->getLabel() }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </section>

        <section class="space-y-3">
            @forelse($this->memos() as $memo)
                @php
                    $statusClass = match($memo->status->getColor()) {
                        'success' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300',
                        'warning' => 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300',
                        'danger' => 'bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-300',
                        'info' => 'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300',
                        default => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
                    };
                @endphp
                <a href="{{ \App\Filament\Helpdesk\Resources\RndInternalMemos\RndInternalMemoResource::getUrl('view', ['record' => $memo]) }}" wire:key="memo-{{ $memo->id }}" class="flex flex-col gap-3 rounded-2xl border border-gray-200 bg-white p-4 transition hover:border-blue-400 dark:border-gray-700 dark:bg-gray-900 dark:hover:border-blue-600 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-mono text-xs font-bold text-blue-600 dark:text-blue-400">{{ $memo->memo_number }}</span>
                            <span class="rounded-full px-2 py-0.5 text-[11px] font-bold {{ $statusClass }}">{{ $memo->status->getLabel() }}</span>
                            @if($memo->revision > 1)
                                <span class="rounded-full border border-gray-200 px-2 py-0.5 text-[11px] font-semibold text-gray-500 dark:border-gray-700 dark:text-gray-400">Revisi {{ $memo->revision }}</span>
                            @endif
                        </div>
                        <h3 class="mt-1 truncate text-base font-bold text-gray-900 dark:text-white">{{ $memo->title }}</h3>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Periode {{ $memo->period_month->translatedFormat('F Y') }} · {{ $memo->menus()->count() }} Menu</p>
                    </div>
                    <x-heroicon-o-chevron-right class="hidden h-5 w-5 shrink-0 text-gray-300 sm:block" />
                </a>
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
        <div class="fixed inset-0 z-[130] flex items-center justify-center p-4">
            <button type="button" wire:click="closeCreateModal" class="absolute inset-0 bg-gray-950/60" aria-label="Tutup modal buat memo"></button>
            <form wire:submit="createMemo" class="relative flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                <div class="flex items-start justify-between gap-4 border-b border-gray-200 p-5 dark:border-gray-700">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">Buat Memo Internal</h3>
                        <p class="mt-1 text-sm text-gray-500">Company Code BLSS otomatis diterapkan.</p>
                    </div>
                    <button type="button" wire:click="closeCreateModal" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-200" aria-label="Tutup">
                        <x-heroicon-o-x-mark class="h-5 w-5" />
                    </button>
                </div>
                <div class="grid gap-4 overflow-y-auto p-5 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Nomor Memo *</label>
                        <input wire:model="memoNumber" class="{{ $inputClass }}" placeholder="mis. 001/RND/IX/2026">
                        @error('memoNumber')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Bulan Rilis *</label>
                        <input type="month" wire:model="periodMonth" class="{{ $inputClass }}">
                        @error('periodMonth')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Judul *</label>
                        <input wire:model="memoTitle" class="{{ $inputClass }}" placeholder="mis. Rilis Menu September 2026">
                        @error('memoTitle')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Tanggal Memo *</label>
                        <input type="date" wire:model="memoDate" class="{{ $inputClass }}">
                        @error('memoDate')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Kepada *</label>
                        <input wire:model="recipient" class="{{ $inputClass }}">
                        @error('recipient')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Dari *</label>
                        <input wire:model="sender" class="{{ $inputClass }}">
                        @error('sender')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Perihal *</label>
                        <input wire:model="subject" class="{{ $inputClass }}">
                        @error('subject')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Catatan</label>
                        <textarea wire:model="notes" rows="2" class="{{ $inputClass }}"></textarea>
                    </div>
                </div>
                <div class="flex justify-end gap-2 border-t border-gray-200 p-5 dark:border-gray-700">
                    <button type="button" wire:click="closeCreateModal" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Batal</button>
                    <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-bold text-white hover:bg-blue-700">Simpan</button>
                </div>
            </form>
        </div>
    @endif
</x-filament-panels::page>
