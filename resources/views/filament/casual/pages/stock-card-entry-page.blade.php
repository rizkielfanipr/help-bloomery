@php
    $labelClass = 'mb-1.5 block text-xs font-semibold text-slate-600 dark:text-slate-400';
@endphp

<div class="flex flex-col bg-blue-600 dark:bg-blue-900"
     style="min-height:100dvh"
     x-data="{
         search: '',
         matchesSearch(name, code) {
             if (this.search === '') return true;
             const q = this.search.toLowerCase();
             return name.toLowerCase().includes(q) || code.toLowerCase().includes(q);
         }
     }">

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

            {{-- Submitted banner --}}
            @if($isSubmitted)
                <div class="flex items-center gap-3 rounded-2xl border border-green-200 bg-green-50 p-4 dark:border-green-800 dark:bg-green-900/20">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/50">
                        <svg class="h-5 w-5 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-green-700 dark:text-green-400">Data sudah terkunci</p>
                        <p class="text-xs text-green-600 dark:text-green-500">Stock card tidak dapat diubah kembali.</p>
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

            {{-- ESB Fetch card --}}
            @if(! $isSubmitted)
                <div class="rounded-2xl border p-4
                    {{ $esbFetched ? 'border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-900/20' : 'border-blue-100 bg-blue-50 dark:border-blue-900 dark:bg-blue-900/20' }}">

                    {{-- Flag unit selector --}}
                    <div class="mb-3">
                        <label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Unit Konversi</label>
                        <select wire:model="flagUnit"
                                class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-blue-400 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-slate-200">
                            @foreach(\App\Filament\Casual\Pages\StockCardEntryPage::FLAG_UNITS as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold {{ $esbFetched ? 'text-green-800 dark:text-green-300' : 'text-blue-800 dark:text-blue-300' }}">
                                {{ $esbFetched ? 'Data ESB sudah dimuat' : 'Fetch Data dari ESB' }}
                            </p>
                            <p class="mt-0.5 text-xs {{ $esbFetched ? 'text-green-600 dark:text-green-400' : 'text-blue-600 dark:text-blue-400' }}">
                                {{ $esbFetched ? 'Isi kolom Qty Aktual untuk setiap material.' : 'Wajib fetch ESB sebelum bisa menyimpan.' }}
                            </p>
                        </div>
                        <button wire:click="fetchFromEsb" wire:loading.attr="disabled"
                                class="flex shrink-0 items-center gap-1.5 rounded-xl px-3.5 py-2.5 text-sm font-semibold text-white transition active:scale-95 disabled:opacity-60
                                       {{ $esbFetched ? 'bg-green-600 active:bg-green-700' : 'bg-blue-600 active:bg-blue-700' }}">
                            <svg wire:loading wire:target="fetchFromEsb" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            <svg wire:loading.remove wire:target="fetchFromEsb" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                            </svg>
                            <span wire:loading.remove wire:target="fetchFromEsb">{{ $esbFetched ? 'Refresh' : 'Fetch ESB' }}</span>
                            <span wire:loading wire:target="fetchFromEsb">Mengambil...</span>
                        </button>
                    </div>
                </div>
            @endif

            {{-- Material rows --}}
            @if(! empty($rows))

                @php
                    $filledCount = collect($rows)->filter(fn ($r) => $r['actual_qty'] !== '')->count();
                    $okCount      = collect($rows)->filter(fn ($r) => $r['actual_qty'] !== '' && (float)$r['actual_qty'] === (float)$r['system_qty'])->count();
                    $surplusCount = collect($rows)->filter(fn ($r) => $r['actual_qty'] !== '' && (float)$r['actual_qty'] > (float)$r['system_qty'])->count();
                    $deficitCount = collect($rows)->filter(fn ($r) => $r['actual_qty'] !== '' && (float)$r['actual_qty'] < (float)$r['system_qty'])->count();
                @endphp

                {{-- Summary bar --}}
                <div class="grid grid-cols-3 gap-2">
                    <div class="flex flex-col items-center rounded-2xl border border-green-100 bg-green-50 py-3 dark:border-green-900/40 dark:bg-green-900/20">
                        <span class="text-lg font-bold text-green-600 dark:text-green-400">{{ $okCount }}</span>
                        <span class="text-[10px] font-semibold uppercase tracking-wide text-green-500 dark:text-green-600">Sesuai</span>
                    </div>
                    <div class="flex flex-col items-center rounded-2xl border border-yellow-100 bg-yellow-50 py-3 dark:border-yellow-900/40 dark:bg-yellow-900/20">
                        <span class="text-lg font-bold text-yellow-600 dark:text-yellow-400">{{ $surplusCount }}</span>
                        <span class="text-[10px] font-semibold uppercase tracking-wide text-yellow-500 dark:text-yellow-600">Surplus</span>
                    </div>
                    <div class="flex flex-col items-center rounded-2xl border border-red-100 bg-red-50 py-3 dark:border-red-900/40 dark:bg-red-900/20">
                        <span class="text-lg font-bold text-red-600 dark:text-red-400">{{ $deficitCount }}</span>
                        <span class="text-[10px] font-semibold uppercase tracking-wide text-red-500 dark:text-red-600">Defisit</span>
                    </div>
                </div>

                {{-- Search bar --}}
                <div class="relative">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 15.803a7.5 7.5 0 0 0 10.607 0Z"/>
                    </svg>
                    <input type="search" x-model="search" placeholder="Cari nama atau kode produk..."
                           class="w-full rounded-2xl border border-gray-200 bg-white py-3 pl-9 pr-4 text-sm text-slate-700 placeholder-slate-400 focus:border-blue-400 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-slate-200">
                </div>

                <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">

                    {{-- Column header --}}
                    <div class="grid grid-cols-[1fr_auto_auto] border-b border-gray-100 bg-gray-50 px-4 py-2 dark:border-gray-800 dark:bg-gray-800/50">
                        <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Material</span>
                        <span class="px-2 text-right text-[10px] font-bold uppercase tracking-wide text-slate-400">Sistem</span>
                        <span class="w-24 text-center text-[10px] font-bold uppercase tracking-wide text-slate-400">Aktual</span>
                    </div>

                    {{-- Rows --}}
                    @foreach($rows as $idx => $row)
                        @php
                            $variance = $this->getVariance($idx);
                            $hasVariance = $variance !== null && $variance != 0;
                            $isDeficit = $variance !== null && $variance < 0;
                            $isSurplus = $variance !== null && $variance > 0;

                            if ($variance === null) {
                                $varBadgeClass = 'bg-slate-100 text-slate-400 dark:bg-slate-800 dark:text-slate-500';
                                $varLabel = '—';
                            } elseif ($variance == 0) {
                                $varBadgeClass = 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400';
                                $varLabel = 'OK';
                            } elseif ($isSurplus) {
                                $varBadgeClass = 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-400';
                                $varLabel = '+' . rtrim(rtrim(number_format(abs($variance), 4, '.', ''), '0'), '.');
                            } else {
                                $varBadgeClass = 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400';
                                $varLabel = '-' . rtrim(rtrim(number_format(abs($variance), 4, '.', ''), '0'), '.');
                            }

                            $hasError = $hasVariance && empty(trim($row['notes']));
                        @endphp

                        <div x-show="matchesSearch('{{ addslashes($row['product_name']) }}', '{{ addslashes($row['product_code']) }}')"
                             class="border-b border-gray-100 dark:border-gray-800 last:border-0">

                            {{-- Product name + variance badge --}}
                            <div class="flex items-start justify-between gap-2 px-4 pb-1.5 pt-3">
                                <div class="min-w-0 flex-1">
                                    <p class="text-xs font-semibold leading-snug text-slate-700 dark:text-slate-200">{{ $row['product_name'] }}</p>
                                    @if($row['product_code'])
                                        <p class="text-[10px] text-slate-400 dark:text-slate-500">{{ $row['product_code'] }}</p>
                                    @endif
                                </div>
                                <span class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold {{ $varBadgeClass }}">
                                    {{ $varLabel }}
                                </span>
                            </div>

                            {{-- 3 columns: Sistem | (spacer) | Aktual input --}}
                            <div class="flex items-center gap-2 px-4 pb-3">
                                <div class="flex-1 text-xs text-slate-500 dark:text-slate-400">
                                    <span class="font-mono">{{ rtrim(rtrim(number_format((float)$row['system_qty'], 4, '.', ''), '0'), '.') }}</span>
                                    <span class="ml-1 text-slate-400">{{ $row['system_unit'] }}</span>
                                </div>

                                @if($isSubmitted)
                                    <div class="w-24 text-center text-xs font-mono font-semibold text-slate-700 dark:text-slate-200">
                                        {{ $row['actual_qty'] !== '' ? rtrim(rtrim(number_format((float)$row['actual_qty'], 4, '.', ''), '0'), '.') : '—' }}
                                    </div>
                                @else
                                    <input type="number"
                                           wire:model.live="rows.{{ $idx }}.actual_qty"
                                           min="0" step="0.0001" placeholder="0"
                                           class="w-24 rounded-lg border bg-gray-50 px-2 py-1.5 text-center text-xs font-mono text-slate-700 placeholder-slate-300 focus:outline-none focus:ring-0 dark:bg-gray-800 dark:text-slate-200
                                                  {{ $hasError ? 'border-red-400 focus:border-red-400 dark:border-red-500' : 'border-gray-200 focus:border-blue-400 dark:border-gray-700' }}">
                                @endif
                            </div>

                            {{-- Notes field (when variance exists) --}}
                            @if($hasVariance || ! empty($row['notes']))
                                <div class="px-4 pb-3">
                                    @if($isSubmitted)
                                        @if(! empty($row['notes']))
                                            <div class="flex items-start gap-1.5 rounded-lg px-3 py-2
                                                {{ $isDeficit ? 'bg-red-50 dark:bg-red-900/20' : 'bg-yellow-50 dark:bg-yellow-900/20' }}">
                                                <svg class="mt-0.5 h-3.5 w-3.5 shrink-0 {{ $isDeficit ? 'text-red-400' : 'text-yellow-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                                                </svg>
                                                <p class="text-xs {{ $isDeficit ? 'text-red-600 dark:text-red-400' : 'text-yellow-700 dark:text-yellow-400' }}">{{ $row['notes'] }}</p>
                                            </div>
                                        @endif
                                    @else
                                        <div class="flex items-center gap-1 mb-1">
                                            <svg class="h-3 w-3 shrink-0 {{ $isDeficit ? 'text-red-400' : 'text-yellow-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/>
                                            </svg>
                                            <span class="text-[10px] font-semibold {{ $isDeficit ? 'text-red-500' : 'text-yellow-600' }}">
                                                Catatan {{ $isDeficit ? 'defisit' : 'surplus' }} wajib diisi
                                            </span>
                                        </div>
                                        <input type="text"
                                               wire:model.live="rows.{{ $idx }}.notes"
                                               placeholder="Tulis keterangan selisih..."
                                               class="w-full rounded-lg border bg-gray-50 px-3 py-2 text-xs text-slate-600 placeholder-slate-400 focus:outline-none focus:ring-0 dark:bg-gray-800 dark:text-slate-300
                                                      {{ ($hasError && empty(trim($row['notes']))) ? 'border-red-400 focus:border-red-400 dark:border-red-500' : 'border-gray-200 focus:border-blue-400 dark:border-gray-700' }}">
                                    @endif
                                </div>
                            @endif

                        </div>
                    @endforeach

                    {{-- Progress footer --}}
                    <div class="border-t border-gray-100 bg-gray-50 px-4 py-2.5 dark:border-gray-800 dark:bg-gray-800/50">
                        <p class="text-[11px] text-slate-400 dark:text-slate-500">
                            {{ $filledCount }} / {{ count($rows) }} item sudah diisi · unit: {{ \App\Filament\Casual\Pages\StockCardEntryPage::FLAG_UNITS[$flagUnit] ?? $flagUnit }}
                        </p>
                    </div>

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
                            class="w-full rounded-2xl py-4 text-sm font-semibold text-white transition active:scale-95 disabled:opacity-60
                                   {{ $esbFetched ? 'bg-blue-600 active:bg-blue-700' : 'cursor-not-allowed bg-slate-400' }}">
                        <span wire:loading.remove wire:target="requestConfirm">
                            {{ $esbFetched ? 'Simpan Stock Card' : 'Fetch ESB Dulu Sebelum Simpan' }}
                        </span>
                        <span wire:loading wire:target="requestConfirm">Memvalidasi...</span>
                    </button>
                @endif
            @endif

        </div>
    </div>

    <x-stock-card.bottom-nav active="card" />

</div>
