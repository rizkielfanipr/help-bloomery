{{-- Sales Number picker shared by Compliment and Void rows. Verified numbers are searched on the
     server against this shift's ESB snapshot; manual entry is an explicit, unverified mode. --}}
@php
    $path = "{$section}.{$index}";
    $isManual = ($row['sales_number_source'] ?? 'esb_snapshot') === 'manual';
@endphp

<div>
    <div class="mb-1.5 flex items-center justify-between gap-2">
        <label for="{{ $section }}-sales-number-{{ $index }}" class="block text-xs font-semibold text-slate-600 dark:text-slate-400">Sales Number <span class="text-red-400">*</span></label>
        @if($isManual)
            <span class="inline-flex items-center gap-1 rounded-md bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-700 dark:bg-amber-950/40 dark:text-amber-300">
                <x-heroicon-o-exclamation-triangle class="h-3 w-3" /> Manual · Unverified
            </span>
        @endif
    </div>

    @if($isManual)
        <input id="{{ $section }}-sales-number-{{ $index }}" type="text" maxlength="50" autocomplete="off" inputmode="text"
               wire:model.blur="{{ $path }}.sales_number" placeholder="Ketik Sales Number sesuai nota"
               class="{{ $fieldClass }}">
        <p class="mt-1.5 text-[11px] leading-relaxed text-slate-400">Belum cocok dengan transaksi ESB; Finance akan melihatnya sebagai data belum terverifikasi.</p>
        <button type="button" wire:click="setEntryMode('{{ $section }}', {{ $index }}, 'sales_number', 'esb_snapshot')"
                class="mt-1.5 text-xs font-semibold text-blue-600 hover:underline dark:text-blue-400">Pilih dari transaksi ESB</button>
    @else
        <div x-data="{
                open: false,
                term: '',
                results: [],
                loading: false,
                failed: false,
                async search() {
                    this.loading = true;
                    this.failed = false;
                    try { this.results = await $wire.searchSalesNumbers(this.term); }
                    catch (e) { this.failed = true; this.results = []; }
                    this.loading = false;
                },
                show() { this.open = true; this.search(); this.$nextTick(() => this.$refs.search.focus()); },
                close() { this.open = false; this.$refs.trigger.focus(); },
                choose(item) { $wire.set('{{ $path }}.sales_number', item.sales_number); this.close(); },
                money(value) { return 'Rp ' + Number(value).toLocaleString('id-ID'); },
             }"
             x-on:keydown.escape.prevent.stop="close()"
             x-on:click.outside="open = false"
             class="relative">
            <button id="{{ $section }}-sales-number-{{ $index }}" x-ref="trigger" type="button" x-on:click="open ? close() : show()"
                    aria-haspopup="listbox" :aria-expanded="open"
                    class="{{ $fieldClass }} flex items-center justify-between gap-2 text-left">
                <span class="truncate {{ filled($row['sales_number'] ?? null) ? 'font-mono text-slate-700 dark:text-slate-200' : 'text-slate-300' }}">{{ filled($row['sales_number'] ?? null) ? $row['sales_number'] : 'Pilih Sales Number' }}</span>
                <x-heroicon-o-magnifying-glass class="h-4 w-4 shrink-0 text-slate-400" />
            </button>

            <div x-show="open" x-cloak x-transition.opacity
                 class="absolute inset-x-0 z-30 mt-1 overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                <div class="border-b border-gray-100 p-2 dark:border-gray-800">
                    <input x-ref="search" x-model="term" x-on:input.debounce.300ms="search()" type="search" maxlength="50"
                           placeholder="Cari Sales Number..." aria-label="Cari Sales Number"
                           class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-base text-slate-700 focus:border-blue-400 focus:outline-none focus:ring-0 dark:border-gray-700 dark:bg-gray-800 dark:text-slate-200">
                </div>
                <ul role="listbox" class="max-h-[min(18rem,60vh)] overflow-y-auto py-1">
                    <li x-show="loading" class="flex items-center gap-2 px-3 py-3 text-xs text-slate-400">
                        <x-heroicon-o-arrow-path class="h-4 w-4 animate-spin" /> Mencari transaksi...
                    </li>
                    <li x-show="! loading && failed" class="px-3 py-3 text-xs text-red-500">Gagal memuat transaksi. Coba lagi.</li>
                    <template x-if="! loading && ! failed">
                        <div>
                            <template x-for="item in results" :key="item.sales_number">
                                <li role="option">
                                    <button type="button" x-on:click="choose(item)" class="flex w-full items-center justify-between gap-3 px-3 py-2.5 text-left hover:bg-blue-50 focus:bg-blue-50 focus:outline-none dark:hover:bg-blue-950/30">
                                        <span class="min-w-0">
                                            <span class="block break-all font-mono text-sm font-semibold text-slate-700 dark:text-slate-200" x-text="item.sales_number"></span>
                                            <span class="block text-[11px] text-slate-400" x-text="item.sales_date_out"></span>
                                        </span>
                                        <span class="shrink-0 font-mono text-xs text-slate-500" x-text="money(item.payment_total)"></span>
                                    </button>
                                </li>
                            </template>
                            <li x-show="results.length === 0" class="px-3 py-3 text-xs leading-relaxed text-slate-400">
                                {{ $hasSnapshot ? 'Tidak ada transaksi yang cocok pada shift ini.' : 'Transaksi ESB shift ini belum dimuat. Tekan Muat Daftar di atas, atau gunakan Input Manual.' }}
                            </li>
                        </div>
                    </template>
                </ul>
            </div>
        </div>
        <button type="button" wire:click="setEntryMode('{{ $section }}', {{ $index }}, 'sales_number', 'manual')"
                class="mt-1.5 text-xs font-semibold text-slate-500 hover:text-blue-600 hover:underline dark:text-slate-400">Input Manual</button>
    @endif

    @error("{$path}.sales_number") <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p> @enderror
    @error("{$path}.sales_number_source") <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p> @enderror
</div>
