<x-filament-panels::page>
@php
    $fmt = fn ($value, int $decimals = 2): string => $value === null ? '—' : rtrim(rtrim(number_format((float) $value, $decimals, ',', '.'), '0'), ',');
    $fieldClass = 'w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100';
    $labelClass = 'mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400';
    $confidenceTone = [
        'high' => 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-300',
        'medium' => 'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-900 dark:bg-blue-950/30 dark:text-blue-300',
        'low' => 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-300',
        'blocked' => 'border-red-200 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-300',
    ];
    $stateTone = [
        'fresh' => ['border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-200', 'Fresh — data lengkap'],
        'stale' => ['border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200', 'Stale — sinkronisasi terakhir sudah lama'],
        'partial' => ['border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200', 'Partial — sebagian hari/source tidak lengkap'],
        'failed' => ['border-red-200 bg-red-50 text-red-800 dark:border-red-900 dark:bg-red-950/30 dark:text-red-200', 'Failed — data demand tidak tersedia'],
    ];
    $rankingTabs = ['priority' => 'Prioritas Order', 'top_demand' => 'Top Demand', 'trend_up' => 'Trend Naik', 'trend_down' => 'Trend Turun', 'waste' => 'Waste', 'stockout' => 'Stockout Risk'];
@endphp

<div class="space-y-6">
    {{-- Filter --}}
    <section aria-labelledby="ordering-filter-heading" class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900 sm:p-5">
        <h2 id="ordering-filter-heading" class="sr-only">Filter analisis</h2>
        <form wire:submit="applyFilters" class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {{-- Searchable multi-select follows the Sales Information filter pattern. --}}
                <div x-data="{ open: false, search: '' }" class="relative" @click.outside="open = false; search = ''">
                    <label for="ordering-brand-search" class="{{ $labelClass }}">Brand</label>
                    <div
                        @click="open = true; $nextTick(() => $refs.searchBrand.focus())"
                        class="flex min-h-[42px] cursor-text flex-wrap items-center gap-1 rounded-lg border border-gray-300 bg-white px-2 py-1.5 dark:border-gray-700 dark:bg-gray-900"
                    >
                        @foreach($brands->whereIn('id', $brandIds) as $brand)
                            <span class="inline-flex items-center gap-1 rounded bg-primary-100 px-1.5 py-0.5 text-xs font-medium text-primary-800 dark:bg-primary-900/40 dark:text-primary-300">
                                {{ $brand->name }}
                                <button type="button" wire:click.stop="toggleBrandId({{ $brand->id }})" aria-label="Hapus Brand {{ $brand->name }}" class="leading-none hover:text-red-500">×</button>
                            </span>
                        @endforeach
                        <input
                            id="ordering-brand-search"
                            x-ref="searchBrand"
                            x-model="search"
                            @focus="open = true"
                            placeholder="{{ count($brandIds) ? '' : 'Semua Brand' }}"
                            autocomplete="off"
                            class="min-w-[80px] flex-1 border-none bg-transparent p-0 text-sm text-gray-800 placeholder-gray-400 outline-none ring-0 focus:ring-0 dark:text-gray-100"
                        >
                    </div>
                    <div x-show="open" x-cloak x-transition class="absolute z-30 mt-1 max-h-56 w-full overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800">
                        @forelse($brands->whereNotIn('id', $brandIds) as $brand)
                            <button
                                type="button"
                                x-show="search === '' || @js(mb_strtolower($brand->name)).includes(search.toLowerCase())"
                                wire:click="toggleBrandId({{ $brand->id }})"
                                class="block w-full px-3 py-2 text-left text-sm text-gray-700 hover:bg-primary-50 dark:text-gray-200 dark:hover:bg-gray-700"
                            >
                                {{ $brand->name }}
                            </button>
                        @empty
                            <p class="px-3 py-2 text-xs text-gray-400">Semua Brand sudah dipilih.</p>
                        @endforelse
                    </div>
                </div>

                <div x-data="{ open: false, search: '' }" class="relative" @click.outside="open = false; search = ''">
                    <label for="ordering-branch-search" class="{{ $labelClass }}">Branch</label>
                    <div
                        @click="open = true; $nextTick(() => $refs.searchBranch.focus())"
                        class="flex min-h-[42px] cursor-text flex-wrap items-center gap-1 rounded-lg border border-gray-300 bg-white px-2 py-1.5 dark:border-gray-700 dark:bg-gray-900"
                    >
                        @foreach($branchOptions->whereIn('id', $branchIds) as $branch)
                            <span class="inline-flex items-center gap-1 rounded bg-primary-100 px-1.5 py-0.5 text-xs font-medium text-primary-800 dark:bg-primary-900/40 dark:text-primary-300">
                                {{ $branch->name }}
                                <button type="button" wire:click.stop="toggleBranchId({{ $branch->id }})" aria-label="Hapus Branch {{ $branch->name }}" class="leading-none hover:text-red-500">×</button>
                            </span>
                        @endforeach
                        <input
                            id="ordering-branch-search"
                            x-ref="searchBranch"
                            x-model="search"
                            @focus="open = true"
                            placeholder="{{ count($branchIds) ? '' : 'Semua Branch' }}"
                            autocomplete="off"
                            class="min-w-[80px] flex-1 border-none bg-transparent p-0 text-sm text-gray-800 placeholder-gray-400 outline-none ring-0 focus:ring-0 dark:text-gray-100"
                        >
                    </div>
                    <div x-show="open" x-cloak x-transition class="absolute z-30 mt-1 max-h-64 w-full overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800">
                        @forelse($branchOptions->whereNotIn('id', $branchIds) as $branch)
                            <button
                                type="button"
                                x-show="search === '' || @js(mb_strtolower($branch->name)).includes(search.toLowerCase())"
                                wire:click="toggleBranchId({{ $branch->id }})"
                                class="block w-full px-3 py-2 text-left text-sm text-gray-700 hover:bg-primary-50 dark:text-gray-200 dark:hover:bg-gray-700"
                            >
                                {{ $branch->name }}
                            </button>
                        @empty
                            <p class="px-3 py-2 text-xs text-gray-400">
                                {{ $branchOptions->isEmpty() ? 'Tidak ada Branch yang dapat Anda akses.' : 'Semua Branch sudah dipilih.' }}
                            </p>
                        @endforelse
                    </div>
                </div>
                <div class="space-y-3">
                    <div>
                        <label for="ordering-period" class="{{ $labelClass }}">Periode Tampilan</label>
                        <select id="ordering-period" wire:model.live="period" class="{{ $fieldClass }}">
                            <option value="7">7 hari terakhir</option>
                            <option value="30">30 hari terakhir</option>
                            <option value="custom">Custom</option>
                        </select>
                    </div>
                    @if($period === 'custom')
                        <div class="grid grid-cols-2 gap-2">
                            <div><label for="ordering-from" class="sr-only">Dari</label><input id="ordering-from" type="date" wire:model="dateFrom" class="{{ $fieldClass }}">@error('dateFrom')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                            <div><label for="ordering-to" class="sr-only">Sampai</label><input id="ordering-to" type="date" wire:model="dateTo" class="{{ $fieldClass }}">@error('dateTo')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                        </div>
                    @endif
                    <div>
                        <label for="ordering-horizon" class="{{ $labelClass }}">Forecast Horizon</label>
                        <select id="ordering-horizon" wire:model="horizon" class="{{ $fieldClass }}">
                            @foreach($horizons as $days)<option value="{{ $days }}">{{ $days }} hari</option>@endforeach
                        </select>
                    </div>
                </div>
                <div class="space-y-3">
                    <div>
                        <label for="ordering-category" class="{{ $labelClass }}">Kategori</label>
                        <select id="ordering-category" wire:model="category" class="{{ $fieldClass }}">
                            <option value="">Semua kategori</option>
                            @foreach($categoryOptions as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label for="ordering-confidence" class="{{ $labelClass }}">Confidence</label>
                            <select id="ordering-confidence" wire:model="confidence" class="{{ $fieldClass }}">
                                <option value="">Semua</option>
                                @foreach(\App\Enums\OrderingConfidence::cases() as $level)<option value="{{ $level->value }}">{{ $level->getLabel() }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label for="ordering-risk" class="{{ $labelClass }}">Stock Risk</label>
                            <select id="ordering-risk" wire:model="risk" class="{{ $fieldClass }}">
                                <option value="">Semua</option>
                                <option value="need_order">Perlu order</option>
                                <option value="stockout">Risiko stockout</option>
                                <option value="overstock">Potensi overstock</option>
                                <option value="blocked">Blocked</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label for="ordering-search" class="{{ $labelClass }}">Cari Produk</label>
                        <input id="ordering-search" type="search" wire:model="search" maxlength="100" placeholder="Kode atau nama produk" class="{{ $fieldClass }}">
                    </div>
                </div>
            </div>
            <div class="flex flex-wrap items-center justify-end gap-2">
                <button type="button" wire:click="resetFilters" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">Reset</button>
                @if($canExport)
                    <button type="button" wire:click="export" wire:loading.attr="disabled" wire:target="export" class="inline-flex items-center gap-2 rounded-lg border border-emerald-300 bg-white px-4 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-50 disabled:opacity-50 dark:border-emerald-800 dark:bg-gray-900 dark:text-emerald-300">
                        <x-heroicon-o-arrow-down-tray class="h-4 w-4" wire:loading.remove wire:target="export" />
                        <x-heroicon-o-arrow-path class="h-4 w-4 animate-spin" wire:loading wire:target="export" />
                        Export Excel
                    </button>
                @endif
                <button type="submit" wire:loading.attr="disabled" wire:target="applyFilters" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50">
                    <x-heroicon-o-arrow-path class="h-4 w-4 animate-spin" wire:loading wire:target="applyFilters" />
                    Tampilkan Analisis
                </button>
            </div>
        </form>
    </section>

    @if($runs->isEmpty())
        <section class="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-12 text-center dark:border-gray-700 dark:bg-gray-900">
            <x-heroicon-o-inbox class="mx-auto h-8 w-8 text-gray-300" />
            <p class="mt-3 text-sm font-semibold text-gray-700 dark:text-gray-200">Belum ada snapshot forecast</p>
            <p class="mx-auto mt-1 max-w-md text-xs text-gray-500 dark:text-gray-400">Branch terpilih belum memiliki forecast {{ $applied['horizon'] ?? $horizon }} hari yang selesai dibuat. Data disinkronkan otomatis oleh scheduler; ubah filter atau tunggu sinkronisasi berikutnya.</p>
        </section>
    @else
        {{-- Freshness / completeness --}}
        @if($sourceState)
            @php [$tone, $stateLabel] = $stateTone[$sourceState['state']]; @endphp
            <section aria-label="Kelengkapan data" class="rounded-2xl border px-4 py-3 text-sm {{ $tone }}">
                <div class="flex flex-wrap items-center gap-x-6 gap-y-1">
                    <span class="font-semibold">{{ $activeRun->branch?->name }} · {{ $stateLabel }}</span>
                    <span>Sinkron terakhir: {{ $sourceState['last_synced_at'] ? \Carbon\CarbonImmutable::parse($sourceState['last_synced_at'])->timezone(config('app.timezone'))->isoFormat('D MMM Y, HH:mm') : '—' }}</span>
                    <span>Forecast dibuat: {{ $activeRun->generated_at?->timezone(config('app.timezone'))->isoFormat('D MMM Y, HH:mm') }}</span>
                    <span>Histori: {{ $activeRun->history_from->isoFormat('D MMM') }} – {{ $activeRun->history_to->isoFormat('D MMM Y') }}</span>
                    <span>Algoritma {{ $activeRun->algorithm_version }}</span>
                </div>
                @if($sourceState['warnings'] !== [])
                    <ul class="mt-2 flex flex-wrap gap-2 text-xs">
                        @foreach($sourceState['warnings'] as $warning)
                            <li class="inline-flex items-center gap-1 rounded-md border border-current/20 px-2 py-0.5"><x-heroicon-o-exclamation-triangle class="h-3.5 w-3.5" /> {{ \App\Services\Ordering\OrderingReasonCodes::label($warning) }}</li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endif

        {{-- KPI --}}
        <section aria-label="Ringkasan" class="grid grid-cols-2 gap-3 sm:grid-cols-4 xl:grid-cols-7">
            @foreach([
                ['Branch dianalisis', $runs->count(), 'heroicon-o-building-storefront'],
                ['Produk dianalisis', $kpis['products'], 'heroicon-o-cube'],
                ['Perlu order', $kpis['need_order'], 'heroicon-o-shopping-cart'],
                ['Risiko stockout', $kpis['stockout'], 'heroicon-o-exclamation-triangle'],
                ['Trend naik', $kpis['trend_up'], 'heroicon-o-arrow-trending-up'],
                ['Waste tinggi', $kpis['high_waste'], 'heroicon-o-trash'],
                ['Data tidak lengkap', $kpis['incomplete'], 'heroicon-o-question-mark-circle'],
            ] as [$label, $value, $icon])
                <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                    <div class="flex items-center gap-2 text-xs font-medium text-gray-500 dark:text-gray-400"><x-dynamic-component :component="$icon" class="h-4 w-4 shrink-0" /> <span class="break-words">{{ $label }}</span></div>
                    <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format($value) }}</p>
                </div>
            @endforeach
        </section>

        {{-- Branch overview --}}
        @if($overview !== [])
            <section aria-labelledby="ordering-overview-heading" class="space-y-3">
                <h2 id="ordering-overview-heading" class="text-sm font-semibold text-gray-900 dark:text-white">Ringkasan per Branch</h2>
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    @foreach($overview as $row)
                        @php [$tone, $stateLabel] = $stateTone[$row['source_state']['state']]; @endphp
                        <article @class(['rounded-xl border bg-white p-4 dark:bg-gray-900', 'border-blue-400 dark:border-blue-600' => $row['branch_id'] === $activeRun->branch_id, 'border-gray-200 dark:border-gray-700' => $row['branch_id'] !== $activeRun->branch_id])>
                            <div class="flex items-start justify-between gap-2">
                                <h3 class="break-words text-sm font-semibold text-gray-900 dark:text-white">{{ $row['branch'] }}</h3>
                                <span class="shrink-0 rounded-md border px-2 py-0.5 text-[11px] font-semibold {{ $tone }}">{{ ucfirst($row['source_state']['state']) }}</span>
                            </div>
                            <dl class="mt-3 grid grid-cols-2 gap-2 text-xs">
                                <div><dt class="text-gray-500">Perlu order</dt><dd class="font-semibold text-gray-900 dark:text-white">{{ $row['kpis']['need_order'] }} SKU</dd></div>
                                <div><dt class="text-gray-500">Risiko stockout</dt><dd class="font-semibold text-gray-900 dark:text-white">{{ $row['kpis']['stockout'] }} SKU</dd></div>
                                <div class="col-span-2"><dt class="text-gray-500">Trend naik tertinggi</dt><dd class="break-words font-semibold text-gray-900 dark:text-white">{{ $row['top_rising'] ? $row['top_rising']->product_name.' (+'.$fmt($row['top_rising']->trendPercent(), 1).'%)' : '—' }}</dd></div>
                                <div class="col-span-2"><dt class="text-gray-500">Waste tertinggi</dt><dd class="break-words font-semibold text-gray-900 dark:text-white">{{ $row['top_waste'] ? $row['top_waste']->product_name.' ('.$fmt((float) $row['top_waste']->waste_rate_capped * 100, 1).'%)' : '—' }}</dd></div>
                                <div class="col-span-2"><dt class="text-gray-500">Confidence</dt><dd class="text-gray-700 dark:text-gray-300">@foreach($row['confidence'] as $level => $count){{ ucfirst($level) }} {{ $count }}@if(! $loop->last) · @endif @endforeach</dd></div>
                            </dl>
                            <button type="button" wire:click="selectBranch({{ $row['branch_id'] }})" class="mt-3 text-xs font-semibold text-blue-600 hover:underline dark:text-blue-400">Lihat Detail Branch</button>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Charts (active branch, one UOM at a time) --}}
        @if($activeRun && $demandChart)
            <section aria-labelledby="ordering-charts-heading" class="space-y-3">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <h2 id="ordering-charts-heading" class="text-sm font-semibold text-gray-900 dark:text-white">Grafik — {{ $activeRun->branch?->name }}</h2>
                    @if(count($demandChart['uoms']) > 1)
                        <div class="w-40"><label for="ordering-uom" class="{{ $labelClass }}">UOM grafik</label>
                            <select id="ordering-uom" wire:model.live="chartUom" class="{{ $fieldClass }}">@foreach($demandChart['uoms'] as $uom)<option value="{{ $uom }}" @selected($uom === $demandChart['uom'])>{{ $uom }}</option>@endforeach</select>
                        </div>
                    @endif
                </div>
                <div class="grid gap-4 xl:grid-cols-2">
                    @foreach([
                        ['demand', 'Actual Demand vs Forecast ('.($demandChart['uom'] ?? '-').')', ['type' => 'line', 'labels' => $demandChart['labels'], 'datasets' => [['label' => 'Actual ('.($demandChart['uom'] ?? '').')', 'data' => $demandChart['actual'], 'borderColor' => '#2563eb', 'backgroundColor' => '#2563eb'], ['label' => 'Forecast ('.($demandChart['uom'] ?? '').')', 'data' => $demandChart['forecast'], 'borderColor' => '#f59e0b', 'backgroundColor' => '#f59e0b', 'borderDash' => [6, 4]]]]],
                        ['coverage', 'Stock Coverage (jumlah produk)', ['type' => 'bar', 'labels' => array_keys($coverageChart), 'datasets' => [['label' => 'Produk', 'data' => array_values($coverageChart), 'backgroundColor' => '#60a5fa']]]],
                        ['trend', 'Top Trend Naik (%)', ['type' => 'bar', 'indexAxis' => 'y', 'labels' => $trendChart->map(fn ($item) => $item->product_name.' · '.$item->uom)->all(), 'datasets' => [['label' => 'Trend (%)', 'data' => $trendChart->map(fn ($item) => $item->trendPercent())->all(), 'backgroundColor' => '#10b981']]]],
                        ['waste', 'Top Waste Rate (%)', ['type' => 'bar', 'indexAxis' => 'y', 'labels' => $wasteChart->map(fn ($item) => $item->product_name.' · '.$item->uom)->all(), 'datasets' => [['label' => 'Waste rate (%)', 'data' => $wasteChart->map(fn ($item) => round((float) $item->waste_rate_capped * 100, 2))->all(), 'backgroundColor' => '#ef4444']]]],
                    ] as [$key, $chartTitle, $config])
                        <figure wire:key="ordering-chart-{{ $key }}-{{ md5(json_encode($config)) }}" class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                            <figcaption class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $chartTitle }}</figcaption>
                            @if($config['labels'] === [])
                                <p class="py-10 text-center text-xs text-gray-400">Tidak ada data untuk grafik ini.</p>
                            @else
                                <div class="relative h-64"
                                     x-data="{ chart: null }"
                                     x-init="$nextTick(() => { if (! window.Chart) return; const c = @js($config); chart = new Chart($refs.canvas, { type: c.type, data: { labels: c.labels, datasets: c.datasets }, options: { indexAxis: c.indexAxis ?? 'x', responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } } }); })"
                                     x-on:remove="chart?.destroy()">
                                    <canvas x-ref="canvas" role="img" aria-label="{{ $chartTitle }}"></canvas>
                                </div>
                            @endif
                        </figure>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Ranking per branch --}}
        <section aria-labelledby="ordering-ranking-heading" class="rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <div class="flex flex-wrap items-end justify-between gap-3 border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                <h2 id="ordering-ranking-heading" class="text-sm font-semibold text-gray-900 dark:text-white">Ranking — {{ $activeRun->branch?->name }}</h2>
                @if($runs->count() > 1)
                    <div class="w-56"><label for="ordering-active-branch" class="{{ $labelClass }}">Branch</label>
                        <select id="ordering-active-branch" wire:change="selectBranch($event.target.value)" class="{{ $fieldClass }}">@foreach($runs as $run)<option value="{{ $run->branch_id }}" @selected($run->branch_id === $activeRun->branch_id)>{{ $run->branch?->name }}</option>@endforeach</select>
                    </div>
                @endif
            </div>
            <div role="tablist" aria-label="Jenis ranking" class="flex gap-1 overflow-x-auto border-b border-gray-200 px-2 dark:border-gray-700">
                @foreach($rankingTabs as $type => $tabLabel)
                    <button type="button" role="tab" aria-selected="{{ $rankingType === $type ? 'true' : 'false' }}" wire:click="$set('rankingType', '{{ $type }}')" @class(['shrink-0 border-b-2 px-3 py-2.5 text-xs font-semibold', 'border-blue-600 text-blue-600 dark:text-blue-400' => $rankingType === $type, 'border-transparent text-gray-500 hover:text-gray-700' => $rankingType !== $type])>{{ $tabLabel }}</button>
                @endforeach
            </div>
            <ol class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse($ranking as $item)
                    <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-2.5 text-sm">
                        <span class="min-w-0 break-words"><span class="mr-2 font-mono text-xs text-gray-400">{{ $loop->iteration }}.</span><span class="font-semibold text-gray-800 dark:text-gray-200">{{ $item->product_name ?? $item->product_code }}</span> <span class="text-xs text-gray-500">{{ $item->product_code }} · {{ $item->uom }} · {{ $item->company_code }}</span></span>
                        <span class="text-xs text-gray-600 dark:text-gray-300">
                            @switch($rankingType)
                                @case('top_demand') Rata-rata {{ $fmt($item->average_daily_demand) }} {{ $item->uom }}/hari @break
                                @case('trend_up') @case('trend_down') {{ $item->trend_label->getLabel() }} {{ $fmt($item->trendPercent(), 1) }}% @break
                                @case('waste') Waste {{ $fmt((float) $item->waste_rate_capped * 100, 1) }}% ({{ $fmt($item->historical_waste) }} {{ $item->uom }}) @break
                                @case('stockout') Habis {{ $item->predicted_stockout_date?->isoFormat('D MMM') }} · coverage {{ $fmt($item->coverage_days, 1) }} hari @break
                                @default Order {{ $fmt($item->final_recommendation) }} {{ $item->uom }} · {{ $item->explanation }}
                            @endswitch
                        </span>
                    </li>
                @empty
                    <li class="px-4 py-8 text-center text-xs text-gray-400">Tidak ada produk untuk ranking ini pada branch aktif.</li>
                @endforelse
            </ol>
        </section>

        {{-- Recommendation table --}}
        <section aria-labelledby="ordering-table-heading" class="rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-700"><h2 id="ordering-table-heading" class="text-sm font-semibold text-gray-900 dark:text-white">Recommendation per Produk</h2><p class="mt-0.5 text-xs text-gray-500">Setiap baris adalah satu branch, company, produk, dan UOM. Quantity tidak dijumlahkan lintas branch atau UOM.</p></div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1100px] text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-800/60">
                        <tr>
                            <th scope="col" class="px-3 py-2">Branch</th>
                            <th scope="col" class="px-3 py-2"><button type="button" wire:click="sortBy('product')" class="font-semibold uppercase">Produk</button></th>
                            <th scope="col" class="px-3 py-2">UOM</th>
                            <th scope="col" class="px-3 py-2 text-right"><button type="button" wire:click="sortBy('trend')" class="font-semibold uppercase">Tren</button></th>
                            <th scope="col" class="px-3 py-2 text-right"><button type="button" wire:click="sortBy('waste')" class="font-semibold uppercase">Waste</button></th>
                            <th scope="col" class="px-3 py-2 text-right">Stok</th>
                            <th scope="col" class="px-3 py-2 text-right"><button type="button" wire:click="sortBy('coverage')" class="font-semibold uppercase">Coverage</button></th>
                            <th scope="col" class="px-3 py-2 text-right"><button type="button" wire:click="sortBy('forecast')" class="font-semibold uppercase">Forecast</button></th>
                            <th scope="col" class="px-3 py-2 text-right"><button type="button" wire:click="sortBy('recommendation')" class="font-semibold uppercase">Rekomendasi</button></th>
                            <th scope="col" class="px-3 py-2">Confidence</th>
                            <th scope="col" class="px-3 py-2"><span class="sr-only">Aksi</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($items as $item)
                            <tr wire:key="ordering-item-{{ $item->id }}" class="align-top">
                                <td class="px-3 py-2 text-xs text-gray-600 dark:text-gray-300">{{ $item->branch?->name }}<br><span class="text-gray-400">{{ $item->company_code }}</span></td>
                                <td class="px-3 py-2"><p class="break-words font-semibold text-gray-800 dark:text-gray-200">{{ $item->product_name ?? '—' }}</p><p class="font-mono text-xs text-gray-500">{{ $item->product_code }}</p><p class="text-xs text-gray-400">{{ $item->category_name ?? 'Tanpa kategori' }}</p></td>
                                <td class="px-3 py-2 text-xs">{{ $item->uom }}</td>
                                <td class="px-3 py-2 text-right text-xs">{{ $item->trendPercent() !== null ? ($item->trendPercent() > 0 ? '+' : '').$fmt($item->trendPercent(), 1).'%' : '' }}<br><span class="text-gray-500">{{ $item->trend_label->getLabel() }}</span></td>
                                <td class="px-3 py-2 text-right text-xs">{{ $item->waste_rate_capped !== null ? $fmt((float) $item->waste_rate_capped * 100, 1).'%' : 'Tidak tersedia' }}</td>
                                <td class="px-3 py-2 text-right text-xs">@if($item->available_stock === null)<span class="text-gray-500">Tidak tersedia</span>@else{{ $fmt($item->available_stock) }}@if((float) $item->available_stock < 0)<br><span class="text-red-600">Stok negatif</span>@endif @endif</td>
                                <td class="px-3 py-2 text-right text-xs">{{ $item->coverage_days !== null ? $fmt($item->coverage_days, 1).' hari' : ($item->confidence === \App\Enums\OrderingConfidence::Blocked ? '—' : 'Tidak ada demand') }}@if($item->predicted_stockout_date)<br><span class="text-amber-700 dark:text-amber-300">Habis {{ $item->predicted_stockout_date->isoFormat('D MMM') }}</span>@endif</td>
                                <td class="px-3 py-2 text-right text-xs">{{ $fmt($item->forecast_demand) }}</td>
                                <td class="px-3 py-2 text-right font-semibold">{{ $item->final_recommendation !== null ? $fmt($item->final_recommendation) : 'Tidak dapat dihitung' }}</td>
                                <td class="px-3 py-2"><span class="inline-flex rounded-md border px-2 py-0.5 text-[11px] font-semibold {{ $confidenceTone[$item->confidence->value] }}">{{ $item->confidence->getLabel() }}</span></td>
                                <td class="px-3 py-2 text-right"><button type="button" wire:click="openDetail({{ $item->id }})" class="inline-flex items-center gap-1 rounded-lg border border-gray-200 px-2 py-1 text-xs font-semibold text-blue-600 hover:bg-blue-50 dark:border-gray-700" aria-label="Lihat detail {{ $item->product_name ?? $item->product_code }}"><x-heroicon-o-eye class="h-4 w-4" /> Detail</button></td>
                            </tr>
                        @empty
                            <tr><td colspan="11" class="px-4 py-10 text-center text-xs text-gray-400">Tidak ada produk yang cocok dengan filter. Ubah filter lalu tekan Tampilkan Analisis.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-200 px-4 py-3 dark:border-gray-700">{{ $items->links() }}</div>
        </section>
    @endif

    {{-- Detail --}}
    <x-filament::modal id="ordering-recommendation-detail" width="4xl" :close-by-clicking-away="true" x-on:modal-closed.window="if ($event.detail.id === 'ordering-recommendation-detail') $wire.closeDetail()">
        <x-slot name="heading">Detail Recommendation</x-slot>
        @if($detail)
            @include('filament.helpdesk.pages.partials.ordering-recommendation-detail', ['item' => $detail, 'fmt' => $fmt])
        @endif
    </x-filament::modal>
</div>

@once
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
@endonce
</x-filament-panels::page>
