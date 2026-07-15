<x-filament-panels::page>

<div
    class="space-y-5"
    x-data="{
        charts: {},

        init() {
            $wire.on('promo-loaded', (data) => { this.buildCharts(data); });
            $wire.on('fetch-next-page', () => { $wire.call('fetchNextPage'); });
        },
        destroyAll() {
            Object.values(this.charts).forEach(c => { if (c) c.destroy(); });
            this.charts = {};
        },
        buildCharts(d) {
            this.destroyAll();
            const dark        = () => document.documentElement.classList.contains('dark');
            const grid        = () => dark() ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.05)';
            const tick        = () => dark() ? '#475569' : '#94a3b8';
            const tBg         = () => dark() ? '#1e293b' : '#fff';
            const tText       = () => dark() ? '#e2e8f0' : '#0f172a';
            const tMuted      = () => dark() ? '#94a3b8' : '#64748b';
            const tBorder     = () => dark() ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)';
            const palette     = ['#8b5cf6','#6366f1','#3b82f6','#14b8a6','#10b981','#f59e0b','#ec4899','#0ea5e9','#f97316','#06b6d4'];
            const tooltipBase = { backgroundColor: tBg(), titleColor: tText(), bodyColor: tMuted(), borderColor: tBorder(), borderWidth: 1, cornerRadius: 10, padding: 12 };
            const axisBase    = { grid: { color: grid(), drawBorder: false }, ticks: { color: tick(), font: { size: 11 } }, border: { display: false } };
            const rupiah      = v => 'Rp ' + new Intl.NumberFormat('id-ID').format(Math.round(v));

            // ── Promo Usage (horizontal bar) ───────────────────────────
            const usageEl = document.getElementById('chartPromoUsage');
            if (usageEl && d.promoUsage && d.promoUsage.labels.length) {
                this.charts.promoUsage = new Chart(usageEl.getContext('2d'), {
                    type: 'bar',
                    data: { labels: d.promoUsage.labels, datasets: [{ label: 'Jumlah Transaksi', data: d.promoUsage.data, backgroundColor: palette.slice(0, d.promoUsage.labels.length), borderRadius: 6, borderSkipped: false }] },
                    options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { ...tooltipBase } }, scales: { x: { ...axisBase, beginAtZero: true, ticks: { ...axisBase.ticks, precision: 0 } }, y: axisBase } },
                });
            }

            // ── Promo Discount Distribution (doughnut) ─────────────────
            const discEl = document.getElementById('chartPromoDiscount');
            if (discEl && d.promoDiscount && d.promoDiscount.labels.length) {
                this.charts.promoDiscount = new Chart(discEl.getContext('2d'), {
                    type: 'doughnut',
                    data: { labels: d.promoDiscount.labels, datasets: [{ data: d.promoDiscount.data, backgroundColor: palette, borderWidth: 0, hoverOffset: 6 }] },
                    options: { responsive: true, maintainAspectRatio: false, cutout: '65%', plugins: { legend: { position: 'right', labels: { color: tick(), font: { size: 11 }, boxWidth: 12, padding: 10 } }, tooltip: { ...tooltipBase, callbacks: { label: ctx => ctx.label + ': ' + rupiah(ctx.parsed) } } } },
                });
            }

            // ── Promo Usage Trend (line) ───────────────────────────────
            const trendEl = document.getElementById('chartPromoTrend');
            if (trendEl && d.promoTrend && d.promoTrend.labels.length) {
                const ctx = trendEl.getContext('2d');
                const grad = ctx.createLinearGradient(0, 0, 0, 220);
                grad.addColorStop(0, '#8b5cf620');
                grad.addColorStop(1, '#8b5cf600');
                this.charts.promoTrend = new Chart(ctx, {
                    type: 'line',
                    data: { labels: d.promoTrend.labels, datasets: [{ label: 'Transaksi Promo', data: d.promoTrend.data, borderColor: '#8b5cf6', backgroundColor: grad, fill: true, tension: 0.4, borderWidth: 2, pointRadius: 3, pointBackgroundColor: '#8b5cf6', pointHoverRadius: 5 }] },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { ...tooltipBase } }, scales: { x: axisBase, y: { ...axisBase, beginAtZero: true, ticks: { ...axisBase.ticks, precision: 0 } } } },
                });
            }
        }
    }"
>

    {{-- ── Filter Card ──────────────────────────────────────────────── --}}
    <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
        <div class="flex flex-wrap items-end gap-4">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Start Date</label>
                <input type="date" wire:model="dateFrom"
                       class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">End Date <span class="text-red-500">*</span></label>
                <input type="date" wire:model="dateTo" required
                       class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 @error('dateTo') border-red-500 @enderror">
                @error('dateTo') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Brand</label>
                <select wire:model.live="selectedBrandId"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                    <option value="">— All Brands —</option>
                    @foreach($this->getBrands() as $brand)
                        <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="min-w-[200px] flex-1">
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Branch</label>
                <select wire:model="selectedBranchId"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                    <option value="">— All Branches —</option>
                    @foreach($this->getBranches() as $branch)
                        <option value="{{ $branch->id }}">{{ $branch->name }} ({{ $branch->esb_branch_code }})</option>
                    @endforeach
                </select>
                @error('selectedBranchId') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>
            <button wire:click="fetch" @disabled($isFetching)
                    class="flex items-center gap-2 rounded-lg bg-violet-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-violet-700 active:bg-violet-800 disabled:cursor-not-allowed disabled:opacity-60">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                </svg>
                Fetch Data
            </button>
        </div>
    </div>

    {{-- Progress bar --}}
    @if($isFetching)
    @php
        $totalBranches = count($fetchBranchIds);
        $branchDone    = $totalBranches > 0 ? $fetchBranchIndex / $totalBranches : 0;
        $pageSlice     = ($totalBranches > 0 && $fetchTotalPages > 0)
            ? ($fetchCurrentPage / $fetchTotalPages) / $totalBranches : 0;
        $progressWidth = max(3, (int) round(($branchDone + $pageSlice) * 100));
    @endphp
    <div class="rounded-xl border border-violet-100 bg-white p-4 dark:border-violet-900/40 dark:bg-gray-900">
        <div class="mb-2 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <svg class="h-4 w-4 animate-spin text-violet-500" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Synchronizing data...</span>
            </div>
            <span class="text-xs text-gray-500 dark:text-gray-400">
                @if($totalBranches > 1)
                    Branch {{ $fetchBranchIndex + 1 }}/{{ $totalBranches }}
                    @if($fetchTotalPages > 0) &mdash; page {{ $fetchCurrentPage }}/{{ $fetchTotalPages }} @endif
                @elseif($fetchTotalPages > 0)
                    Page {{ $fetchCurrentPage }}/{{ $fetchTotalPages }}
                @endif
            </span>
        </div>
        <div class="h-1.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
            <div class="h-full rounded-full transition-all duration-300"
                 style="width: {{ $progressWidth }}%; background-color: #8b5cf6;"></div>
        </div>
    </div>
    @endif

    @if($fetched)

    @php
        $isPercentType = fn ($t) => str_contains(strtolower((string) $t), 'persen')
            || str_contains(strtolower((string) $t), 'percent')
            || str_contains(strtolower((string) $t), '%');
        $fmtDiscount = fn ($val, $typeDesc) => $isPercentType($typeDesc)
            ? number_format((float) $val, 1, '.', '') . '%'
            : 'Rp ' . number_format((float) $val, 0, ',', '.');
        $promoTypes = collect($promotionCatalog)
            ->pluck('promotionTypeDesc')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
        $filteredRows = $selectedPromoType
            ? collect($promoPerformanceTable)->where('type', $selectedPromoType)->values()->all()
            : $promoPerformanceTable;
        $filteredPromoTxns     = collect($filteredRows)->sum('count');
        $filteredTotalDiscount = collect($filteredRows)->sum('totalDiscount');
        $filteredPromoRevenue  = collect($filteredRows)->sum('totalRevenue');
        $filteredAvgTxn        = $filteredPromoTxns > 0 ? $filteredPromoRevenue / $filteredPromoTxns : 0.0;
        $filteredUsedCount     = collect($filteredRows)->where('count', '>', 0)->count();
        $filteredUnusedCount   = collect($filteredRows)->where('count', 0)->count();
    @endphp

    {{-- ── Promotion Type Filter ──────────────────────────────────────── --}}
    @if(count($promoTypes) > 0)
    <div class="flex flex-wrap items-center gap-3 rounded-xl border border-violet-100 bg-violet-50/40 px-4 py-3 dark:border-violet-900/30 dark:bg-violet-950/20">
        <span class="text-xs font-semibold uppercase tracking-wider text-violet-600 dark:text-violet-400">Filter Tipe Promo</span>
        <select wire:model.live="selectedPromoType"
                class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm text-gray-700 focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
            <option value="">— Semua Tipe —</option>
            @foreach($promoTypes as $pt)
            <option value="{{ $pt }}" @selected($selectedPromoType === $pt)>{{ $pt }}</option>
            @endforeach
        </select>
        @if($selectedPromoType)
        <button wire:click="$set('selectedPromoType', '')"
                class="text-xs text-violet-500 hover:text-violet-700 dark:text-violet-400">× Hapus filter</button>
        @endif
    </div>
    @endif

    {{-- ── KPI Cards ────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
        @php
            $nonPromoTransactions = $totalTransactions - $promoTransactions;
            $kpis = [
                [
                    'label' => 'Promo Transactions',
                    'value' => number_format($promoTransactions).' / '.number_format($totalTransactions),
                    'sub'   => 'Adoption rate '.$promoAdoptionRate.'%',
                    'color' => 'violet',
                    'path'  => 'M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z M6 6h.008v.008H6V6Z',
                ],
                [
                    'label' => 'Adoption Rate',
                    'value' => $promoAdoptionRate.'%',
                    'sub'   => $promoTransactions > 0 ? number_format($promoTransactions).' transaksi pakai promo' : 'Tidak ada promo digunakan',
                    'color' => 'indigo',
                    'path'  => 'M7.5 14.25v2.25m3-4.5v4.5m3-6.75v6.75m3-9v9M6 20.25h12A2.25 2.25 0 0 0 20.25 18V6A2.25 2.25 0 0 0 18 3.75H6A2.25 2.25 0 0 0 3.75 6v12A2.25 2.25 0 0 0 6 20.25Z',
                ],
                [
                    'label' => 'Total Discount Diberikan',
                    'value' => 'Rp '.number_format($totalDiscount, 0, ',', '.'),
                    'sub'   => $promoTransactions > 0 ? 'Avg Rp '.number_format($promoTransactions > 0 ? $totalDiscount / $promoTransactions : 0, 0, ',', '.').' / transaksi promo' : '',
                    'color' => 'red',
                    'path'  => 'M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z',
                ],
                [
                    'label' => 'Revenue dari Promo',
                    'value' => 'Rp '.number_format($promoRevenue, 0, ',', '.'),
                    'sub'   => $totalRevenue > 0 ? round($promoRevenue / $totalRevenue * 100, 1).'% dari total revenue' : '',
                    'color' => 'emerald',
                    'path'  => 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z',
                ],
                [
                    'label' => 'Avg Transaksi (dengan Promo)',
                    'value' => 'Rp '.number_format($avgPromoTransaction, 0, ',', '.'),
                    'sub'   => '',
                    'color' => 'blue',
                    'path'  => 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z',
                ],
                [
                    'label' => 'Avg Transaksi (tanpa Promo)',
                    'value' => 'Rp '.number_format($avgNonPromoTransaction, 0, ',', '.'),
                    'sub'   => $avgPromoTransaction > 0 && $avgNonPromoTransaction > 0
                        ? ($avgPromoTransaction > $avgNonPromoTransaction ? '+' : '').number_format($avgPromoTransaction - $avgNonPromoTransaction, 0, ',', '.').' vs non-promo'
                        : '',
                    'color' => 'amber',
                    'path'  => 'M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941',
                ],
            ];
            $colorMap = [
                'violet'  => ['icon' => 'text-violet-600',  'bg' => 'bg-violet-50 dark:bg-violet-500/10'],
                'indigo'  => ['icon' => 'text-indigo-600',  'bg' => 'bg-indigo-50 dark:bg-indigo-500/10'],
                'red'     => ['icon' => 'text-red-600',     'bg' => 'bg-red-50 dark:bg-red-500/10'],
                'emerald' => ['icon' => 'text-emerald-600', 'bg' => 'bg-emerald-50 dark:bg-emerald-500/10'],
                'blue'    => ['icon' => 'text-blue-600',    'bg' => 'bg-blue-50 dark:bg-blue-500/10'],
                'amber'   => ['icon' => 'text-amber-600',   'bg' => 'bg-amber-50 dark:bg-amber-500/10'],
            ];
        @endphp
        @foreach($kpis as $kpi)
        @php $c = $colorMap[$kpi['color']]; @endphp
        <div class="rounded-xl border border-gray-100 bg-white p-5 dark:border-gray-700/50 dark:bg-gray-900">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $kpi['label'] }}</p>
                    <p class="mt-1.5 truncate text-lg font-bold tracking-tight text-gray-900 dark:text-white">{{ $kpi['value'] }}</p>
                    @if($kpi['sub'])
                    <p class="mt-0.5 truncate text-xs text-gray-400">{{ $kpi['sub'] }}</p>
                    @endif
                </div>
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $c['bg'] }}">
                    <svg class="h-5 w-5 {{ $c['icon'] }}" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $kpi['path'] }}"/>
                    </svg>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    {{-- ── Charts ── Usage + Discount side by side ──────────────────── --}}
    @if(count($chartPromoUsage['labels']) > 0)
    <div class="grid grid-cols-1 gap-4 xl:grid-cols-5">
        <div class="rounded-xl border border-gray-100 bg-white p-5 dark:border-gray-700/50 dark:bg-gray-900 xl:col-span-3">
            <div class="mb-4">
                <h3 class="font-semibold text-gray-800 dark:text-white">Top Promosi Terbanyak Digunakan</h3>
                <p class="mt-0.5 text-xs text-gray-400">Berdasarkan jumlah transaksi yang menggunakan promo</p>
            </div>
            <div wire:ignore style="height: 300px">
                <canvas id="chartPromoUsage"></canvas>
            </div>
        </div>
        <div class="rounded-xl border border-gray-100 bg-white p-5 dark:border-gray-700/50 dark:bg-gray-900 xl:col-span-2">
            <div class="mb-4">
                <h3 class="font-semibold text-gray-800 dark:text-white">Distribusi Discount</h3>
                <p class="mt-0.5 text-xs text-gray-400">Proporsi total discount yang diberikan per promo</p>
            </div>
            <div wire:ignore style="height: 300px">
                <canvas id="chartPromoDiscount"></canvas>
            </div>
        </div>
    </div>

    {{-- ── Promo Usage Trend ─────────────────────────────────────────── --}}
    <div class="rounded-xl border border-gray-100 bg-white p-5 dark:border-gray-700/50 dark:bg-gray-900">
        <div class="mb-4">
            <h3 class="font-semibold text-gray-800 dark:text-white">Tren Penggunaan Promo</h3>
            <p class="mt-0.5 text-xs text-gray-400">Jumlah transaksi yang menggunakan promo per hari</p>
        </div>
        <div wire:ignore style="height: 220px">
            <canvas id="chartPromoTrend"></canvas>
        </div>
    </div>
    @endif

    {{-- ── Promotion Performance Table ──────────────────────────────── --}}
    @if(count($filteredRows) > 0)
    <div class="overflow-hidden rounded-xl border border-gray-100 bg-white dark:border-gray-700/50 dark:bg-gray-900">
        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3.5 dark:border-gray-800">
            <div>
                <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">Performa per Promosi</p>
                <p class="mt-0.5 text-xs text-gray-400">Digabungkan dari catalog promo ESB + data transaksi. Promo dengan 0 transaksi = belum dipakai dalam periode ini.</p>
            </div>
            <div class="flex gap-2">
                @if($filteredUsedCount > 0)
                <span class="rounded-full bg-violet-50 px-3 py-1 text-xs font-semibold text-violet-700 dark:bg-violet-500/10 dark:text-violet-400">{{ $filteredUsedCount }} digunakan</span>
                @endif
                @if($filteredUnusedCount > 0)
                <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-600 dark:bg-gray-700 dark:text-gray-300">{{ $filteredUnusedCount }} tidak digunakan</span>
                @endif
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:border-gray-800 dark:bg-gray-800/50 dark:text-gray-400">
                        <th class="px-4 py-3 text-left">Nama Promo</th>
                        <th class="px-4 py-3 text-left">Tipe</th>
                        <th class="px-4 py-3 text-right">Jml. Transaksi</th>
                        <th class="px-4 py-3 text-right">Adoption Rate</th>
                        <th class="px-4 py-3 text-right">Total Discount</th>
                        <th class="px-4 py-3 text-right">Total Revenue</th>
                        <th class="px-4 py-3 text-right">Avg/Transaksi</th>
                        <th class="px-4 py-3 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach($filteredRows as $row)
                    <tr class="{{ $row['count'] === 0 ? 'opacity-50' : '' }} hover:bg-gray-50 dark:hover:bg-gray-800/40">
                        <td class="px-4 py-3">
                            <p class="font-medium text-gray-800 dark:text-gray-100">{{ $row['name'] }}</p>
                            @if($row['code'] !== '—' && $row['code'] !== $row['name'])
                            <p class="text-xs text-gray-400">{{ $row['code'] }}</p>
                            @endif
                            @if($row['configDiscount'] > 0)
                            <p class="mt-0.5 text-xs text-gray-400">Nilai disc: {{ $fmtDiscount($row['configDiscount'], $row['type']) }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">{{ $row['type'] }}</td>
                        <td class="px-4 py-3 text-right">
                            @if($row['count'] > 0)
                            <span class="font-semibold text-gray-800 dark:text-gray-100">{{ number_format($row['count']) }}</span>
                            @else
                            <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            @if($row['count'] > 0)
                            <span class="rounded-full bg-violet-50 px-2 py-0.5 text-xs font-semibold text-violet-700 dark:bg-violet-500/10 dark:text-violet-400">{{ $row['adoptionRate'] }}%</span>
                            @else
                            <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right font-mono text-sm {{ $row['count'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-400' }}">
                            {{ $row['count'] > 0 ? 'Rp '.number_format($row['totalDiscount'], 0, ',', '.') : '—' }}
                        </td>
                        <td class="px-4 py-3 text-right font-mono text-sm text-gray-800 dark:text-gray-100">
                            {{ $row['count'] > 0 ? 'Rp '.number_format($row['totalRevenue'], 0, ',', '.') : '—' }}
                        </td>
                        <td class="px-4 py-3 text-right font-mono text-sm text-gray-700 dark:text-gray-300">
                            {{ $row['count'] > 0 ? 'Rp '.number_format($row['avgTransaction'], 0, ',', '.') : '—' }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if($row['isActive'])
                            <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400">Aktif</span>
                            @else
                            <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-500 dark:bg-gray-700 dark:text-gray-400">Expired</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800/50">
                        <td colspan="2" class="px-4 py-3.5 text-sm font-bold text-gray-700 dark:text-gray-200">
                            Total{{ $selectedPromoType ? ' ('.$selectedPromoType.')' : '' }}
                        </td>
                        <td class="px-4 py-3.5 text-right font-bold text-gray-800 dark:text-gray-100">{{ number_format($filteredPromoTxns) }}</td>
                        <td class="px-4 py-3.5 text-right font-bold text-violet-700 dark:text-violet-400">
                            {{ $totalTransactions > 0 ? round($filteredPromoTxns / $totalTransactions * 100, 1) : 0 }}%
                        </td>
                        <td class="px-4 py-3.5 text-right font-mono font-bold text-red-600 dark:text-red-400">Rp {{ number_format($filteredTotalDiscount, 0, ',', '.') }}</td>
                        <td class="px-4 py-3.5 text-right font-mono font-bold text-primary-700 dark:text-primary-400">Rp {{ number_format($filteredPromoRevenue, 0, ',', '.') }}</td>
                        <td class="px-4 py-3.5 text-right font-mono font-bold text-gray-700 dark:text-gray-200">Rp {{ number_format($filteredAvgTxn, 0, ',', '.') }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    @endif

    {{-- ── Branch × Promotion Usage ──────────────────────────────────── --}}
    @if(count($branchPromoTable) > 1)
    @php
        $allPromoNames = collect($branchPromoTable)->flatMap(fn ($p) => array_keys($p))->unique()->sort()->values()->all();
    @endphp
    <div class="overflow-hidden rounded-xl border border-gray-100 bg-white dark:border-gray-700/50 dark:bg-gray-900">
        <div class="border-b border-gray-100 px-5 py-3.5 dark:border-gray-800">
            <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">Penggunaan Promo per Cabang</p>
            <p class="mt-0.5 text-xs text-gray-400">Jumlah transaksi menggunakan masing-masing promo di setiap cabang</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:border-gray-800 dark:bg-gray-800/50 dark:text-gray-400">
                        <th class="sticky left-0 z-10 min-w-[160px] bg-gray-50 px-4 py-3 text-left dark:bg-gray-800/50">Cabang</th>
                        @foreach($allPromoNames as $pname)
                            <th class="min-w-[120px] px-4 py-3 text-right">{{ Str::limit($pname, 20) }}</th>
                        @endforeach
                        <th class="min-w-[80px] px-4 py-3 text-right text-primary-600 dark:text-primary-400">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach($branchPromoTable as $branchCode => $promos)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40">
                        <td class="sticky left-0 z-10 bg-white px-4 py-2.5 font-medium text-gray-800 dark:bg-gray-900 dark:text-gray-100">{{ $branchCode }}</td>
                        @foreach($allPromoNames as $pname)
                            @php $cnt = $promos[$pname] ?? 0; @endphp
                            <td class="px-4 py-2.5 text-right {{ $cnt > 0 ? 'font-semibold text-violet-700 dark:text-violet-400' : 'text-gray-300 dark:text-gray-600' }}">
                                {{ $cnt > 0 ? number_format($cnt) : '—' }}
                            </td>
                        @endforeach
                        <td class="px-4 py-2.5 text-right font-bold text-gray-800 dark:text-gray-100">{{ number_format(array_sum($promos)) }}</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800/50">
                        <td class="sticky left-0 z-10 bg-gray-50 px-4 py-3 text-sm font-bold text-gray-700 dark:bg-gray-800/50 dark:text-gray-200">Total</td>
                        @foreach($allPromoNames as $pname)
                            <td class="px-4 py-3 text-right font-bold text-gray-700 dark:text-gray-200">
                                {{ number_format(collect($branchPromoTable)->sum(fn ($p) => $p[$pname] ?? 0)) }}
                            </td>
                        @endforeach
                        <td class="px-4 py-3 text-right font-bold text-primary-700 dark:text-primary-400">
                            {{ number_format(collect($branchPromoTable)->sum(fn ($p) => array_sum($p))) }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    @endif

    {{-- ── Promotion Catalog (full metadata from ESB promo API) ─────── --}}
    @if(count($promotionCatalog) > 0)
    <div class="overflow-hidden rounded-xl border border-gray-100 bg-white dark:border-gray-700/50 dark:bg-gray-900">
        <div class="border-b border-gray-100 px-5 py-3.5 dark:border-gray-800">
            <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">Katalog Promosi</p>
            <p class="mt-0.5 text-xs text-gray-400">Data lengkap promosi dari ESB — termasuk tanggal berlaku, nilai discount, dan cabang yang terdaftar</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:border-gray-800 dark:bg-gray-800/50 dark:text-gray-400">
                        <th class="px-4 py-3 text-left">Kode Promo</th>
                        <th class="px-4 py-3 text-left">Tipe</th>
                        <th class="px-4 py-3 text-right">Nilai Discount</th>
                        <th class="px-4 py-3 text-center">Berlaku</th>
                        <th class="px-4 py-3 text-center">Berakhir</th>
                        <th class="px-4 py-3 text-left">Cabang</th>
                        <th class="px-4 py-3 text-left">Payment Method</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach($promotionCatalog as $promo)
                    @php
                        $endStr = substr((string) ($promo['endDate'] ?? ''), 0, 10);
                        $isActive = $endStr ? $endStr >= now()->toDateString() : true;
                        $catalogType = $promo['promotionTypeDesc'] ?? '';
                    @endphp
                    @if(!$selectedPromoType || $catalogType === $selectedPromoType)
                    <tr class="{{ !$isActive ? 'opacity-50' : '' }} hover:bg-gray-50 dark:hover:bg-gray-800/40">
                        <td class="px-4 py-3">
                            <p class="font-semibold text-gray-800 dark:text-gray-100">{{ $promo['promotionCode'] ?? '—' }}</p>
                            <p class="text-xs text-gray-400">ID: {{ $promo['promotionID'] ?? '—' }}</p>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-400">{{ $promo['promotionTypeDesc'] ?? '—' }}</td>
                        <td class="px-4 py-3 text-right font-mono text-gray-800 dark:text-gray-100">
                            {{ $fmtDiscount($promo['discount'] ?? 0, $catalogType) }}
                        </td>
                        <td class="px-4 py-3 text-center text-xs text-gray-600 dark:text-gray-400">
                            {{ substr((string) ($promo['startDate'] ?? '—'), 0, 10) }}
                        </td>
                        <td class="px-4 py-3 text-center text-xs {{ $isActive ? 'text-gray-600 dark:text-gray-400' : 'text-red-500' }}">
                            {{ $endStr ?: '—' }}
                        </td>
                        <td class="px-4 py-3">
                            @if(!empty($promo['branches']))
                            <div class="flex flex-wrap gap-1">
                                @foreach($promo['branches'] as $branch)
                                <span class="rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-600 dark:bg-gray-700 dark:text-gray-300">{{ $branch['branchCode'] ?? '' }}</span>
                                @endforeach
                            </div>
                            @else
                            <span class="text-gray-400 text-xs">Semua cabang</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @php
                                $pm = $promo['paymentMethod'] ?? null;
                                $pmName = is_array($pm) ? ($pm['paymentMethodName'] ?? null) : null;
                            @endphp
                            @if($pmName)
                            <span class="rounded bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-500/10 dark:text-blue-400">{{ $pmName }}</span>
                            @else
                            <span class="text-xs text-gray-400">Semua metode</span>
                            @endif
                        </td>
                    </tr>
                    @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    @else
    {{-- Empty state --}}
    <div class="flex flex-col items-center gap-3 rounded-xl border border-dashed border-gray-200 bg-white py-16 text-center dark:border-gray-700 dark:bg-gray-900">
        <div class="flex h-14 w-14 items-center justify-center rounded-full bg-violet-50 dark:bg-violet-500/10">
            <svg class="h-7 w-7 text-violet-400 dark:text-violet-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z M6 6h.008v.008H6V6Z"/>
            </svg>
        </div>
        <div>
            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Pilih branch, tentukan periode, lalu klik <strong>Fetch Data</strong></p>
            <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Data promosi + transaksi akan ditarik dari ESB dan dianalisis secara otomatis</p>
        </div>
    </div>
    @endif

</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
@endpush

</x-filament-panels::page>
