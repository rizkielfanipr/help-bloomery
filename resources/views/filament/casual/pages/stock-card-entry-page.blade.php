@php
    $labelClass = 'mb-1.5 block text-xs font-semibold text-slate-600 dark:text-slate-400';
@endphp

<div class="flex flex-col bg-blue-600 dark:bg-blue-900"
     style="min-height:100dvh"
     @if(! $isSubmitted) wire:init="loadProductCatalog" @endif
     x-data="{
         search: '',
         categoryPage: 0,
         draftRequestFailed: false,
         matchesSearch(name, code) {
             if (this.search === '') return true;
             const q = this.search.toLowerCase();
             return name.toLowerCase().includes(q) || code.toLowerCase().includes(q);
         },
         groupMatches(products) {
             return products.some((product) => this.matchesSearch(product.name, product.code));
         }
     }"
     x-init="
         $wire.on('stock-card-fetch-next', () => $wire.call('fetchNextCatalogMovement'));
         $wire.$interceptRequest(({ onFailure, onError, onSuccess }) => {
             onFailure(() => draftRequestFailed = true);
             onError(() => draftRequestFailed = true);
             onSuccess(() => draftRequestFailed = false);
         });
     ">

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

            @if(! $isSubmitted && $rows !== [])
                @php $draftFilledCount = collect($rows)->filter(fn ($r) => filled($r['actual_qty'] ?? null))->count(); @endphp
                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-900/20" role="status" aria-live="polite">
                    <p class="text-sm font-semibold text-amber-800 dark:text-amber-300">Draft · {{ $draftFilledCount }}/{{ count($rows) }} item terisi</p>
                    <p class="mt-1 text-xs text-amber-700 dark:text-amber-400">
                        <span wire:loading wire:target.except="requestConfirm,cancelConfirm,submit,loadProductCatalog,fetchNextCatalogMovement">Menyimpan draft…</span>
                        <span wire:loading.remove wire:target.except="requestConfirm,cancelConfirm,submit,loadProductCatalog,fetchNextCatalogMovement">
                            @if($draftSaveState === 'failed')
                                <span class="font-medium text-red-600 dark:text-red-400">Gagal menyimpan draft — <button type="button" wire:click="saveDraft" class="font-semibold underline">Coba lagi</button></span>
                            @else
                                <span x-show="draftRequestFailed" x-cloak class="font-medium text-red-600 dark:text-red-400">Gagal menyimpan draft — <button type="button" wire:click="saveDraft" class="font-semibold underline">Coba lagi</button></span>
                                <span x-show="! draftRequestFailed">
                                    @if($draftSaveState === 'saved' && $draftSavedAt)
                                        Draft tersimpan pukul {{ $draftSavedAt }}
                                    @else
                                        Isian tersimpan otomatis saat Anda mengetik.
                                    @endif
                                </span>
                            @endif
                        </span>
                    </p>
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

            {{-- Daily Stock Movement catalog --}}
            @if(! $isSubmitted)
                <div class="rounded-2xl border border-blue-100 bg-blue-50 p-4 dark:border-blue-900/50 dark:bg-blue-950/30">
                    <div class="flex items-start gap-3">
                        <svg @class(['h-5 w-5 shrink-0 text-blue-500', 'animate-spin' => $catalogLoading]) fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20 7h-7m7 0v7m0-7-3 3a8 8 0 1 0 2 8" />
                        </svg>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-blue-800 dark:text-blue-200">
                                {{ $catalogLoading ? 'Memuat daftar produk…' : 'Produk dari Stock Movement' }}
                            </p>
                            <p class="mt-1 text-xs leading-5 text-blue-700 dark:text-blue-300">
                                Tanggal {{ \Carbon\Carbon::parse($catalogPeriodTo)->format('d M Y') }}.
                                @if($selectionSnapshot !== [])
                                    {{ count($rows) }} produk wajib dihitung hari ini berdasarkan pengaturan kategori yang tersimpan.
                                @elseif(collect($this->categorySettingsSnapshot)->contains(fn ($rule) => ! $rule['all_categories'] || ! $rule['show_uncategorized']))
                                    Produk mengikuti kategori yang ditetapkan saat laporan dibuat. Rincian transaksi ESB tetap tersedia lengkap.
                                @else
                                    Seluruh produk dari Stock Movement ESB ditampilkan tanpa batas jumlah atau kategori.
                                @endif
                            </p>
                            @if($catalogLoading && $catalogTaskTotal > 0)
                                @php
                                    $catalogProgressIndex = $catalogTaskIndex;
                                    $catalogProgressTotal = $catalogTaskTotal;
                                    $catalogProgress = $catalogProgressTotal > 0
                                        ? min(100, (int) floor(($catalogProgressIndex / $catalogProgressTotal) * 100))
                                        : 0;
                                @endphp
                                <div class="mt-3">
                                    <div class="mb-1.5 flex items-center justify-between gap-3 text-[11px] text-blue-700 dark:text-blue-300">
                                        <span class="truncate">
                                            @if($catalogCurrentDate)
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
                            @foreach($selectionSnapshot['warnings'] ?? [] as $warning)
                                <p class="mt-1 text-xs font-medium text-amber-700 dark:text-amber-300">{{ $warning }}</p>
                            @endforeach
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

                @php
                    $filledCount = collect($rows)->filter(fn ($r) => filled($r['actual_qty'] ?? null))->count();
                    $groupedRows = collect($rows)
                        ->map(fn (array $row, int $index): array => ['index' => $index, 'row' => $row])
                        ->sort(function (array $left, array $right): int {
                            $categoryComparison = strnatcasecmp(
                                trim((string) ($left['row']['product_category'] ?? '')) ?: 'Tanpa Kategori',
                                trim((string) ($right['row']['product_category'] ?? '')) ?: 'Tanpa Kategori',
                            );

                            return $categoryComparison !== 0
                                ? $categoryComparison
                                : strnatcasecmp($left['row']['product_name'], $right['row']['product_name']);
                        })
                        ->groupBy(fn (array $item): string => trim((string) ($item['row']['product_category'] ?? '')) ?: 'Tanpa Kategori');
                @endphp

                {{-- Search bar --}}
                <div class="relative">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 15.803a7.5 7.5 0 0 0 10.607 0Z"/>
                    </svg>
                    <input type="search" x-model="search" placeholder="Cari nama atau kode produk..."
                           class="w-full rounded-2xl border border-gray-200 bg-white py-3 pl-9 pr-4 text-sm text-slate-700 placeholder-slate-400 focus:border-blue-400 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-slate-200">
                </div>

                <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">

                    {{-- Rows grouped by product category --}}
                    @foreach($groupedRows as $category => $categoryRows)
                        <div x-show="categoryPage === {{ $loop->index }}" data-stock-category-page="{{ $loop->index }}">
                            <div
                                data-stock-category="{{ $category }}"
                                class="border-b border-gray-200 bg-slate-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-800/70"
                            >
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <p class="text-[10px] font-semibold uppercase tracking-wider text-blue-600 dark:text-blue-400">Kategori {{ $loop->iteration }} dari {{ $groupedRows->count() }}</p>
                                        <p class="mt-0.5 text-sm font-bold text-slate-700 dark:text-slate-200">{{ $category }}</p>
                                    </div>
                                    <span class="rounded-full border border-gray-200 bg-white px-2.5 py-1 text-[10px] font-semibold text-slate-500 dark:border-gray-700 dark:bg-gray-900 dark:text-slate-400">{{ $categoryRows->count() }} Produk</span>
                                </div>
                            </div>

                            @foreach($categoryRows as $item)
                                @php
                                    $idx = $item['index'];
                                    $row = $item['row'];
                                @endphp
                                <div wire:key="row-{{ $idx }}"
                                 x-show="matchesSearch('{{ addslashes($row['product_name']) }}', '{{ addslashes($row['product_code']) }}')"
                                 class="border-b border-gray-100 px-4 py-3 dark:border-gray-800">

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
                                @error("rows.{$idx}.actual_qty")
                                    <p class="mt-1 text-right text-xs text-red-500">{{ $message }}</p>
                                @enderror

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
                        </div>
                    @endforeach

                    @if($groupedRows->count() > 1)
                        <div class="flex items-center justify-center gap-3 border-t border-gray-200 bg-white px-4 py-3 dark:border-gray-700 dark:bg-gray-900">
                            <button
                                type="button"
                                x-on:click="search = ''; categoryPage--"
                                x-bind:disabled="categoryPage === 0"
                                aria-label="Kategori sebelumnya"
                                title="Kategori sebelumnya"
                                class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 text-slate-600 transition hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700 disabled:cursor-not-allowed disabled:opacity-35 dark:border-gray-700 dark:text-slate-300 dark:hover:border-blue-900 dark:hover:bg-blue-950/40 dark:hover:text-blue-300"
                            >
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m15.75 19.5-7.5-7.5 7.5-7.5"/></svg>
                            </button>
                            <div class="min-w-20 text-center">
                                <p class="text-xs font-semibold text-slate-600 dark:text-slate-300"><span x-text="categoryPage + 1"></span> / {{ $groupedRows->count() }}</p>
                                <p class="mt-0.5 text-[10px] text-slate-400">Kategori</p>
                            </div>
                            <button
                                type="button"
                                x-on:click="search = ''; categoryPage++"
                                x-bind:disabled="categoryPage === {{ $groupedRows->count() - 1 }}"
                                aria-label="Kategori selanjutnya"
                                title="Kategori selanjutnya"
                                class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 text-slate-600 transition hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700 disabled:cursor-not-allowed disabled:opacity-35 dark:border-gray-700 dark:text-slate-300 dark:hover:border-blue-900 dark:hover:bg-blue-950/40 dark:hover:text-blue-300"
                            >
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                            </button>
                        </div>
                    @endif

                    {{-- Progress footer --}}
                    <div class="border-t border-gray-100 bg-gray-50 px-4 py-2.5 dark:border-gray-800 dark:bg-gray-800/50">
                        <p class="text-[11px] text-slate-400 dark:text-slate-500">
                            {{ $filledCount }} / {{ count($rows) }} item sudah diisi
                        </p>
                    </div>

                </div>
            @elseif(! $isSubmitted)
                <div class="rounded-2xl border border-dashed border-gray-200 bg-white px-5 py-8 text-center dark:border-gray-700 dark:bg-gray-900">
                    <p class="text-sm text-slate-400 dark:text-slate-500">{{ $catalogLoading ? 'Daftar produk sedang disiapkan…' : 'Belum ada produk Stock Movement pada periode ini.' }}</p>
                </div>
            @endif

            {{-- Submit / Confirm area --}}
            @if(! $isSubmitted)
                @if($showConfirm)
                    <p class="text-center text-xs text-slate-500 dark:text-slate-400">
                        Setelah dikirim, data akan terkunci. Koreksi hanya dapat dilakukan melalui Supervisor/Finance.
                    </p>
                    <div class="flex gap-3">
                        <button wire:click="cancelConfirm" wire:loading.attr="disabled" wire:target="submit"
                                class="flex flex-1 items-center justify-center rounded-2xl bg-gray-100 py-4 text-sm font-semibold text-slate-600 transition active:bg-gray-200 disabled:opacity-60 dark:bg-gray-800 dark:text-slate-300">
                            Batal
                        </button>
                        <button wire:click="submit" wire:loading.attr="disabled" wire:target="submit"
                                class="flex flex-[2] items-center justify-center gap-1.5 rounded-2xl bg-blue-600 py-4 text-sm font-semibold text-white transition active:scale-95 active:bg-blue-700 disabled:opacity-60">
                            <span wire:loading.remove wire:target="submit">Ya, Kirim</span>
                            <span wire:loading wire:target="submit">Mengirim...</span>
                        </button>
                    </div>
                @else
                    <div class="flex gap-3">
                        <button wire:click="saveDraft" wire:loading.attr="disabled" wire:target="saveDraft"
                                @disabled($rows === [])
                                class="flex-1 rounded-2xl border border-gray-200 bg-white py-4 text-sm font-semibold text-slate-700 transition active:bg-gray-100 disabled:opacity-60 dark:border-gray-700 dark:bg-gray-900 dark:text-slate-200">
                            <span wire:loading.remove wire:target="saveDraft">Simpan Draft</span>
                            <span wire:loading wire:target="saveDraft">Menyimpan...</span>
                        </button>
                        <button wire:click="requestConfirm" wire:loading.attr="disabled" wire:target="requestConfirm"
                                @disabled(! $catalogLoaded || $catalogLoading)
                                class="flex-[2] rounded-2xl bg-blue-600 py-4 text-sm font-semibold text-white transition active:scale-95 active:bg-blue-700 disabled:opacity-60">
                            <span wire:loading.remove wire:target="requestConfirm">{{ $catalogLoaded ? 'Kirim untuk Review' : 'Menyiapkan Produk…' }}</span>
                            <span wire:loading wire:target="requestConfirm">Memvalidasi...</span>
                        </button>
                    </div>
                @endif
            @endif

        </div>
    </div>

    <x-stock-card.bottom-nav active="card" />

</div>
