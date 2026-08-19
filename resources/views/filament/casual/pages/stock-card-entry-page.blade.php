@php
    $labelClass = 'mb-1.5 block text-xs font-semibold text-slate-600 dark:text-slate-400';
@endphp

<div class="flex flex-col bg-blue-600 dark:bg-blue-900"
     style="min-height:100dvh"
     @if(! $isSubmitted) wire:init="loadProductCatalog" @endif
     x-data="{
         search: '',
         matchesSearch(name, code) {
             if (this.search === '') return true;
             const q = this.search.toLowerCase();
             return name.toLowerCase().includes(q) || code.toLowerCase().includes(q);
         }
     }"
     x-init="$wire.on('stock-card-fetch-next', () => $wire.call('fetchNextCatalogUsage'))">

    {{-- HEADER --}}
    <div class="flex-shrink-0 px-5 pb-8 pt-14">
        <div class="mb-4 flex items-center gap-3">
            <a href="{{ route('filament.casual.pages.stock-card-page') }}?reportDate={{ $reportDate }}"
               wire:navigate
               class="flex h-9 w-9 items-center justify-center rounded-full bg-white/20 text-white transition active:bg-white/30">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
                </svg>
            </a>
            <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/20 text-white">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 2.625c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125m16.5 5.625c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125"/>
                </svg>
            </div>
            <span class="text-base font-semibold text-white">Stock Card Harian</span>
        </div>
        <p class="text-blue-200">{{ auth()->user()->branch?->name ?? 'Tanpa Cabang' }}</p>
        <p class="text-xl font-semibold text-white">
            {{ \Carbon\Carbon::parse($reportDate)->locale('id')->isoFormat('dddd, D MMMM Y') }}
        </p>
    </div>

    {{-- WHITE CONTENT CARD --}}
    <div class="flex-1 overflow-y-auto rounded-t-3xl bg-gray-50 pb-28 pt-6 dark:bg-gray-950">
        <div class="flex flex-col gap-4 px-4">

            {{-- Status banner --}}
            @if($status === \App\Enums\StockCardStatus::PendingSupervisor)
                <div class="flex items-center gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-900/20">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900/50">
                        <svg class="h-5 w-5 text-amber-600 dark:text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-amber-700 dark:text-amber-400">{{ \App\Enums\StockCardStatus::PendingSupervisor->getLabel() }}</p>
                        <p class="text-xs text-amber-600 dark:text-amber-500">Data terkunci. Kalau ada koreksi, akan dilakukan Supervisor/Finance di back office.</p>
                    </div>
                </div>
            @elseif($status === \App\Enums\StockCardStatus::PendingFinance)
                <div class="flex items-center gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-900/20">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900/50">
                        <svg class="h-5 w-5 text-amber-600 dark:text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-amber-700 dark:text-amber-400">{{ \App\Enums\StockCardStatus::PendingFinance->getLabel() }}</p>
                        <p class="text-xs text-amber-600 dark:text-amber-500">Sudah disetujui Supervisor, menunggu review Finance.</p>
                    </div>
                </div>
            @elseif($status === \App\Enums\StockCardStatus::Completed)
                <div class="flex items-center gap-3 rounded-2xl border border-green-200 bg-green-50 p-4 dark:border-green-800 dark:bg-green-900/20">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/50">
                        <svg class="h-5 w-5 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-green-700 dark:text-green-400">{{ \App\Enums\StockCardStatus::Completed->getLabel() }}</p>
                        <p class="text-xs text-green-600 dark:text-green-500">Stock card sudah final dan tidak dapat diubah kembali.</p>
                    </div>
                </div>
            @endif

            {{-- Tanggal --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <label class="{{ $labelClass }}">Tanggal</label>
                <div class="flex items-center gap-2 rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 dark:border-gray-700 dark:bg-gray-800">
                    <svg class="h-4 w-4 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>
                    </svg>
                    <span class="text-sm text-slate-600 dark:text-slate-300">
                        {{ \Carbon\Carbon::parse($reportDate)->locale('id')->isoFormat('dddd, D MMMM Y') }}
                    </span>
                </div>
            </div>

            {{-- Staff In Charge --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <label class="{{ $labelClass }}">Staff In Charge <span class="text-red-500">*</span></label>
                @if($isSubmitted)
                    <div class="space-y-2">
                        @forelse($submittedEmployees as $emp)
                            <div class="rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 dark:border-gray-700 dark:bg-gray-800">
                                <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $emp['name'] ?? 'Data employee tersimpan' }}</p>
                                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                    {{ collect([$emp['code'] ?? null, $emp['position'] ?? null])->filter()->join(' · ') ?: '-' }}
                                </p>
                            </div>
                        @empty
                            <p class="text-sm text-slate-400">-</p>
                        @endforelse
                    </div>
                @else
                    <div class="space-y-2">
                        @foreach($this->employees as $employee)
                            @php
                                $isChecked = in_array($employee->id, $employeeIds);
                                $borderColor = $isChecked ? '#60a5fa' : '#e5e7eb';
                            @endphp
                            <label style="-webkit-tap-highlight-color: transparent; outline: none !important; box-shadow: none !important; border-color: {{ $borderColor }} !important;"
                                   class="flex cursor-pointer items-center gap-3 rounded-xl border px-3 py-2.5 transition
                                          {{ $isChecked ? 'bg-blue-50 dark:bg-blue-950/30' : 'bg-gray-50 dark:bg-gray-800' }}">
                                <input type="checkbox" wire:model="employeeIds" value="{{ $employee->id }}"
                                       style="outline: none !important; box-shadow: none !important;"
                                       class="h-4 w-4 shrink-0 rounded border-gray-300 text-blue-600 dark:border-gray-600">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $employee->name }}</p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ $employee->employee_code }} · {{ $employee->position }}</p>
                                </div>
                            </label>
                        @endforeach
                    </div>
                    @if($this->employees->isEmpty())
                        <p class="mt-1.5 text-xs text-amber-600">Belum ada employee aktif pada branch ini. Tambahkan melalui Master › Employee.</p>
                    @endif
                    @error('employeeIds')
                        <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                    @error('employeeIds.*')
                        <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                @endif
            </div>

            {{-- Rolling Daily Usage catalog --}}
            @if(! $isSubmitted)
                <div class="rounded-2xl border border-blue-100 bg-blue-50 p-4 dark:border-blue-900/50 dark:bg-blue-950/30">
                    <div class="flex items-start gap-3">
                        <svg @class(['h-5 w-5 shrink-0 text-blue-500', 'animate-spin' => $catalogLoading]) fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20 7h-7m7 0v7m0-7-3 3a8 8 0 1 0 2 8" />
                        </svg>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-blue-800 dark:text-blue-200">
                                @if($catalogLoading && $catalogPhase === 'category')
                                    Memuat kategori produk…
                                @else
                                    {{ $catalogLoading ? 'Memuat daftar produk…' : 'Produk dari Daily Usage' }}
                                @endif
                            </p>
                            <p class="mt-1 text-xs leading-5 text-blue-700 dark:text-blue-300">
                                Periode {{ \Carbon\Carbon::parse($catalogPeriodFrom)->format('d M Y') }}–{{ \Carbon\Carbon::parse($catalogPeriodTo)->format('d M Y') }}.
                                Semua Barang WIP dan 30 produk kategori lain yang paling aktif.
                            </p>
                            @if($catalogLoading && $catalogTaskTotal > 0)
                                @php
                                    $catalogProgressIndex = $catalogPhase === 'category' ? $catalogCategoryIndex : $catalogTaskIndex;
                                    $catalogProgressTotal = $catalogPhase === 'category' ? $catalogCategoryTotal : $catalogTaskTotal;
                                    $catalogProgress = $catalogProgressTotal > 0
                                        ? min(100, (int) floor(($catalogProgressIndex / $catalogProgressTotal) * 100))
                                        : 0;
                                @endphp
                                <div class="mt-3">
                                    <div class="mb-1.5 flex items-center justify-between gap-3 text-[11px] text-blue-700 dark:text-blue-300">
                                        <span class="truncate">
                                            @if($catalogPhase === 'category')
                                                Batch kategori produk
                                            @elseif($catalogCurrentDate)
                                                {{ \Carbon\Carbon::parse($catalogCurrentDate)->format('d M Y') }}
                                                @if($catalogCurrentCode) · {{ $catalogCurrentCode }} @endif
                                            @else
                                                Menyiapkan proses pengambilan data…
                                            @endif
                                        </span>
                                        <span class="shrink-0 font-semibold">{{ $catalogProgressIndex }}/{{ $catalogProgressTotal }} ({{ $catalogProgress }}%)</span>
                                    </div>
                                    <div class="h-2 overflow-hidden rounded-full bg-blue-200 dark:bg-blue-900">
                                        <div class="h-full rounded-full bg-blue-600 transition-all duration-300"
                                             style="width: {{ $catalogProgress }}%"></div>
                                    </div>
                                </div>
                            @endif
                            @if($catalogError)
                                <p class="mt-1 text-xs font-medium text-red-600 dark:text-red-400">{{ $catalogError }}</p>
                            @elseif($catalogFailedRequests > 0)
                                <p class="mt-1 text-xs text-amber-600 dark:text-amber-400">{{ $catalogFailedRequests }} request ESB gagal; daftar berhasil dimuat sebagian.</p>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            {{-- Product rows --}}
            @if(! empty($rows))

                @php $filledCount = collect($rows)->filter(fn ($r) => $r['actual_qty'] !== '')->count(); @endphp

                {{-- Search bar --}}
                <div class="relative">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 15.803a7.5 7.5 0 0 0 10.607 0Z"/>
                    </svg>
                    <input type="search" x-model="search" placeholder="Cari nama atau kode produk..."
                           class="w-full rounded-2xl border border-gray-200 bg-white py-3 pl-9 pr-4 text-sm text-slate-700 placeholder-slate-400 focus:border-blue-400 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-slate-200">
                </div>

                <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">

                    {{-- Rows --}}
                    @foreach($rows as $idx => $row)
                        <div wire:key="row-{{ $idx }}"
                             x-show="matchesSearch('{{ addslashes($row['product_name']) }}', '{{ addslashes($row['product_code']) }}')"
                             class="border-b border-gray-100 px-4 py-3 last:border-0 dark:border-gray-800">

                            <div class="grid grid-cols-[1fr_140px] items-center gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-xs font-semibold text-slate-700 dark:text-slate-200">{{ $row['product_name'] }}</p>
                                    <p class="text-[10px] text-slate-400 dark:text-slate-500">
                                        {{ $row['product_code'] }} &middot; {{ $row['product_category'] ?? 'Tanpa Kategori' }}@if($row['system_unit']) &middot; {{ $row['system_unit'] }}@endif
                                    </p>
                                </div>

                                @if($isSubmitted)
                                    <div class="text-right text-xs font-mono font-semibold text-slate-700 dark:text-slate-200">
                                        {{ $row['actual_qty'] !== '' ? rtrim(rtrim(number_format((float)$row['actual_qty'], 4, '.', ''), '0'), '.') : '—' }}
                                    </div>
                                @else
                                    <input type="number"
                                           inputmode="decimal"
                                           wire:model.live.debounce.600ms="rows.{{ $idx }}.actual_qty"
                                           min="0" step="0.0001" placeholder="0"
                                           class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-3 text-right text-base font-mono text-slate-700 placeholder-slate-300 focus:border-blue-400 focus:outline-none focus:ring-0 dark:border-gray-700 dark:bg-gray-800 dark:text-slate-200">
                                @endif
                            </div>

                            @if($isSubmitted)
                                @if(! empty($row['notes']))
                                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ $row['notes'] }}</p>
                                @endif
                            @else
                                <input type="text"
                                       wire:model.live.debounce.600ms="rows.{{ $idx }}.notes"
                                       placeholder="Catatan (opsional)"
                                       class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-base text-slate-600 placeholder-slate-400 focus:border-blue-400 focus:outline-none focus:ring-0 dark:border-gray-700 dark:bg-gray-800 dark:text-slate-300">
                            @endif

                        </div>
                    @endforeach

                    {{-- Progress footer --}}
                    <div class="border-t border-gray-100 bg-gray-50 px-4 py-2.5 dark:border-gray-800 dark:bg-gray-800/50">
                        <p class="text-[11px] text-slate-400 dark:text-slate-500">
                            {{ $filledCount }} / {{ count($rows) }} item sudah diisi
                        </p>
                    </div>

                </div>
            @elseif(! $isSubmitted)
                <div class="rounded-2xl border border-dashed border-gray-200 bg-white px-5 py-8 text-center dark:border-gray-700 dark:bg-gray-900">
                    <p class="text-sm text-slate-400 dark:text-slate-500">{{ $catalogLoading ? 'Daftar produk sedang disiapkan…' : 'Belum ada produk Daily Usage pada periode ini.' }}</p>
                </div>
            @endif

            {{-- Submit / Confirm area --}}
            @if(! $isSubmitted)
                @if($showConfirm)
                    <p class="text-center text-xs text-slate-400 dark:text-slate-500">
                        Data tidak dapat diubah setelah disimpan
                    </p>
                    <div class="flex gap-3">
                        <button wire:click="cancelConfirm"
                                class="flex flex-1 items-center justify-center rounded-2xl bg-gray-100 py-4 text-sm font-semibold text-slate-600 transition active:bg-gray-200 dark:bg-gray-800 dark:text-slate-300">
                            Batal
                        </button>
                        <button wire:click="save" wire:loading.attr="disabled"
                                class="flex flex-[2] items-center justify-center gap-1.5 rounded-2xl bg-blue-600 py-4 text-sm font-semibold text-white transition active:scale-95 active:bg-blue-700 disabled:opacity-60">
                            <svg wire:loading.remove wire:target="save" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                            </svg>
                            <span wire:loading.remove wire:target="save">Ya, Simpan</span>
                            <span wire:loading wire:target="save">Menyimpan...</span>
                        </button>
                    </div>
                @else
                    <button wire:click="requestConfirm" wire:loading.attr="disabled"
                            @disabled(! $catalogLoaded || $catalogLoading)
                            class="w-full rounded-2xl bg-blue-600 py-4 text-sm font-semibold text-white transition active:scale-95 active:bg-blue-700 disabled:opacity-60">
                        <span wire:loading.remove wire:target="requestConfirm">{{ $catalogLoaded ? 'Simpan Stock Card' : 'Menyiapkan Produk…' }}</span>
                        <span wire:loading wire:target="requestConfirm">Memvalidasi...</span>
                    </button>
                @endif
            @endif

        </div>
    </div>

    <x-stock-card.bottom-nav active="card" />

</div>
