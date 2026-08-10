<x-filament-panels::page>

<div
    class="space-y-5"
    x-data="{
        charts: {},
        materialTable: [],

        init() {
            $wire.on('stock-fetch-next', () => { $wire.call('fetchNextBranch'); });
            $wire.on('stock-loaded', (data) => { this.buildCharts(data); });
        },

        destroyAll() {
            Object.values(this.charts).forEach(c => { if (c) c.destroy(); });
            this.charts = {};
        },

        buildCharts(d) {
            this.destroyAll();
            this.materialTable = d.materialTable ?? [];

            const dark        = () => document.documentElement.classList.contains('dark');
            const grid        = () => dark() ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.05)';
            const tick        = () => dark() ? '#475569' : '#94a3b8';
            const tBg         = () => dark() ? '#1e293b' : '#fff';
            const tText       = () => dark() ? '#e2e8f0' : '#0f172a';
            const tMuted      = () => dark() ? '#94a3b8' : '#64748b';
            const tBorder     = () => dark() ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)';
            const tooltipBase = { backgroundColor: tBg(), titleColor: tText(), bodyColor: tMuted(), borderColor: tBorder(), borderWidth: 1, cornerRadius: 10, padding: 12 };
            const axisBase    = { grid: { color: grid(), drawBorder: false }, ticks: { color: tick(), font: { size: 11 } }, border: { display: false } };
            const palette     = ['#3b82f6','#6366f1','#ec4899','#f59e0b','#14b8a6','#10b981','#0ea5e9','#8b5cf6','#f97316','#06b6d4'];
            const num         = v => new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(v);

            // ── Tren Pemakaian Harian ──────────────────────────────────────
            const trendEl = document.getElementById('chartTrend');
            if (trendEl && d.trend?.labels?.length) {
                const ctx = trendEl.getContext('2d');
                const grad = ctx.createLinearGradient(0, 0, 0, 280);
                grad.addColorStop(0, '#3b82f630');
                grad.addColorStop(1, '#3b82f600');
                this.charts.trend = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: d.trend.labels,
                        datasets: [{
                            label: 'Total Pemakaian',
                            data: d.trend.data,
                            borderColor: '#3b82f6',
                            backgroundColor: grad,
                            fill: true, tension: 0.4, borderWidth: 2,
                            pointRadius: 3, pointBackgroundColor: '#3b82f6', pointHoverRadius: 5,
                        }],
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: { ...tooltipBase, callbacks: { label: ctx => ` ${num(ctx.parsed.y)}` } },
                            datalabels: { display: false },
                        },
                        scales: { x: axisBase, y: { ...axisBase, beginAtZero: true } },
                    },
                });
            }

            // ── Top 10 Material (Horizontal Bar) ──────────────────────────
            const matEl = document.getElementById('chartTopMaterials');
            if (matEl && d.topMaterials?.labels?.length) {
                const totalMat = d.topMaterials.data.reduce((a, b) => a + b, 0);
                this.charts.topMaterials = new Chart(matEl.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: d.topMaterials.labels,
                        datasets: [{
                            label: 'Total Pemakaian',
                            data: d.topMaterials.data,
                            backgroundColor: palette.slice(0, d.topMaterials.labels.length),
                            borderRadius: 5, borderSkipped: false,
                        }],
                    },
                    options: {
                        indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                        layout: { padding: { right: 80 } },
                        plugins: {
                            legend: { display: false },
                            tooltip: { ...tooltipBase, callbacks: { label: ctx => ` ${num(ctx.parsed.x)} (${totalMat > 0 ? (ctx.parsed.x / totalMat * 100).toFixed(1) : 0}%)` } },
                            datalabels: {
                                anchor: 'end', align: 'right',
                                color: tick, font: { size: 11, weight: '600' },
                                formatter: (val) => num(val),
                            },
                        },
                        scales: { x: { ...axisBase, beginAtZero: true }, y: axisBase },
                    },
                });
            }

            // ── Distribusi Material (Doughnut) ─────────────────────────────
            const distEl = document.getElementById('chartDistribution');
            if (distEl && d.distribution?.labels?.length) {
                const totalDist = d.distribution.data.reduce((a, b) => a + b, 0);
                this.charts.distribution = new Chart(distEl.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: d.distribution.labels,
                        datasets: [{ data: d.distribution.data, backgroundColor: [...palette, '#94a3b8'], borderWidth: 0, hoverOffset: 6 }],
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false, cutout: '62%',
                        plugins: {
                            legend: {
                                position: 'right',
                                labels: {
                                    color: tick(), font: { size: 10 }, boxWidth: 10, padding: 8,
                                    generateLabels: (chart) => {
                                        const ds = chart.data.datasets[0];
                                        return chart.data.labels.map((label, i) => ({
                                            text: `${label}  ${totalDist > 0 ? (ds.data[i] / totalDist * 100).toFixed(1) : 0}%`,
                                            fillStyle: ds.backgroundColor[i], hidden: false, index: i,
                                        }));
                                    },
                                },
                            },
                            tooltip: { ...tooltipBase, callbacks: { label: ctx => ` ${ctx.label}: ${num(ctx.parsed)} (${totalDist > 0 ? (ctx.parsed / totalDist * 100).toFixed(1) : 0}%)` } },
                            datalabels: {
                                display: (ctx) => totalDist > 0 && ctx.dataset.data[ctx.dataIndex] / totalDist > 0.05,
                                color: '#fff', font: { size: 10, weight: '700' },
                                formatter: (val) => totalDist > 0 ? (val / totalDist * 100).toFixed(1) + '%' : '0%',
                            },
                        },
                    },
                });
            }

            // ── Pemakaian per Cabang (Bar) ─────────────────────────────────
            const branchEl = document.getElementById('chartBranchComparison');
            if (branchEl && d.branchComparison?.labels?.length) {
                const totalBranch = d.branchComparison.data.reduce((a, b) => a + b, 0);
                this.charts.branchComparison = new Chart(branchEl.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: d.branchComparison.labels,
                        datasets: [{
                            label: 'Total Pemakaian',
                            data: d.branchComparison.data,
                            backgroundColor: '#3b82f6',
                            borderRadius: 5, borderSkipped: false,
                        }],
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: { ...tooltipBase, callbacks: { label: ctx => ` ${num(ctx.parsed.y)} (${totalBranch > 0 ? (ctx.parsed.y / totalBranch * 100).toFixed(1) : 0}%)` } },
                            datalabels: { display: false },
                        },
                        scales: { x: axisBase, y: { ...axisBase, beginAtZero: true } },
                    },
                });
            }
        },
    }"
>

    {{-- ── Filter Card ─────────────────────────────────────────────────────── --}}
    <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
        <div class="flex flex-wrap items-end gap-4">

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Periode Awal</label>
                <input type="date" wire:model="dateFrom"
                       class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Periode Akhir <span class="text-red-500">*</span></label>
                <input type="date" wire:model="dateTo" required
                       class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 @error('dateTo') border-red-500 @enderror">
                @error('dateTo') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>

            {{-- Branch multiselect --}}
            <div x-data="{ open: false, search: '' }" class="relative min-w-[220px] flex-1" @click.outside="open = false; search = ''">
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Cabang</label>
                <div @click="open = true; $nextTick(() => $refs.searchBranch.focus())"
                     class="flex min-h-[38px] cursor-text flex-wrap items-center gap-1 rounded-lg border border-gray-300 bg-white px-2 py-1.5 dark:border-gray-600 dark:bg-gray-800">
                    @foreach(collect($this->getAllBranches())->whereIn('id', $selectedBranchIds) as $branch)
                        <span class="inline-flex items-center gap-1 rounded bg-primary-100 px-1.5 py-0.5 text-xs font-medium text-primary-800 dark:bg-primary-900/40 dark:text-primary-300">
                            {{ $branch->name }}
                            <button type="button" wire:click.stop="toggleBranchId({{ $branch->id }})" class="leading-none hover:text-red-500">×</button>
                        </span>
                    @endforeach
                    <input x-ref="searchBranch" x-model="search" @focus="open = true"
                           placeholder="{{ count($selectedBranchIds) ? '' : 'Semua Cabang' }}"
                           class="min-w-[80px] flex-1 border-none bg-transparent text-sm text-gray-700 placeholder-gray-400 outline-none dark:text-gray-200">
                </div>
                <div x-show="open" x-transition class="absolute z-30 mt-1 max-h-64 w-full overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800">
                    @foreach(collect($this->getAllBranches())->whereNotIn('id', $selectedBranchIds) as $branch)
                        <div x-show="search === '' || '{{ strtolower($branch->name) }}'.includes(search.toLowerCase())"
                             wire:click="toggleBranchId({{ $branch->id }})"
                             class="cursor-pointer px-3 py-2 text-sm text-gray-700 hover:bg-primary-50 dark:text-gray-200 dark:hover:bg-gray-700">
                            {{ $branch->name }} <span class="text-xs text-gray-400">({{ $branch->activeEsbCodes()->pluck('esb_branch_code')->join(', ') }})</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <button wire:click="fetch" @disabled($isFetching)
                    class="flex items-center gap-2 rounded-lg bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-700 active:bg-primary-800 disabled:cursor-not-allowed disabled:opacity-60">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                </svg>
                Tarik Data
            </button>
        </div>
    </div>

    {{-- ── Progress bar ────────────────────────────────────────────────────── --}}
    @if($isFetching)
    @php
        $total = count($fetchBranchIds);
        $pct   = $total > 0 ? max(3, (int) round($fetchBranchIndex / $total * 100)) : 3;
    @endphp
    <div class="rounded-xl border border-blue-100 bg-white p-4 dark:border-blue-900/40 dark:bg-gray-900">
        <div class="mb-2 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <svg class="h-4 w-4 animate-spin text-blue-500" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Mengambil data material dari ESB…</span>
            </div>
            <span class="text-xs text-gray-500 dark:text-gray-400">
                Cabang {{ $fetchBranchIndex }}/{{ $total }}
                @if($fetchCurrentBranchCode) — {{ $fetchCurrentBranchCode }} @endif
            </span>
        </div>
        <div class="h-1.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
            <div class="h-full rounded-full bg-blue-500 transition-all duration-300" style="width: {{ $pct }}%"></div>
        </div>
    </div>
    @endif

    @if($fetched)

    {{-- ── KPI Cards ───────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        @php
            $kpis = [
                [
                    'label'      => 'Total Pemakaian',
                    'value'      => number_format($totalQty, 2, ',', '.'),
                    'sub'        => 'jumlah seluruh material',
                    'icon_color' => 'text-blue-600',
                    'icon_bg'    => 'bg-blue-50 dark:bg-blue-500/10',
                    'path'       => 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z',
                ],
                [
                    'label'      => 'Jumlah Cabang',
                    'value'      => number_format($totalBranches),
                    'sub'        => 'cabang dengan data',
                    'icon_color' => 'text-indigo-600',
                    'icon_bg'    => 'bg-indigo-50 dark:bg-indigo-500/10',
                    'path'       => 'M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21',
                ],
                [
                    'label'      => 'Total Baris Data',
                    'value'      => number_format($totalRecords),
                    'sub'        => 'material × cabang unik',
                    'icon_color' => 'text-cyan-600',
                    'icon_bg'    => 'bg-cyan-50 dark:bg-cyan-500/10',
                    'path'       => 'M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM3.75 12h.007v.008H3.75V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm-.375 5.25h.007v.008H3.75v-.008Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z',
                ],
                [
                    'label'      => 'Material Terbanyak',
                    'value'      => $topMaterial,
                    'sub'        => 'pemakaian tertinggi',
                    'icon_color' => 'text-amber-600',
                    'icon_bg'    => 'bg-amber-50 dark:bg-amber-500/10',
                    'path'       => 'M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z',
                ],
            ];
        @endphp
        @foreach($kpis as $kpi)
        <div class="rounded-xl border border-gray-100 bg-white p-5 dark:border-gray-700/50 dark:bg-gray-900">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $kpi['label'] }}</p>
                    <p class="mt-1.5 truncate text-xl font-bold tracking-tight text-gray-900 dark:text-white">{{ $kpi['value'] }}</p>
                    <p class="mt-0.5 text-[11px] text-gray-400">{{ $kpi['sub'] }}</p>
                </div>
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $kpi['icon_bg'] }}">
                    <svg class="h-5 w-5 {{ $kpi['icon_color'] }}" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $kpi['path'] }}"/>
                    </svg>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    {{-- ── Row 1: Tren + Distribusi ────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">

        <div class="overflow-hidden rounded-xl border border-gray-100 bg-white dark:border-gray-700/50 dark:bg-gray-900">
            <div class="border-b border-gray-100 px-5 py-3.5 dark:border-gray-800">
                <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">Tren Pemakaian Harian</p>
                <p class="mt-0.5 text-xs text-gray-400">Total pemakaian material dari ESB per hari (semua cabang)</p>
            </div>
            <div class="p-5">
                <div class="h-64"><canvas id="chartTrend"></canvas></div>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-100 bg-white dark:border-gray-700/50 dark:bg-gray-900">
            <div class="border-b border-gray-100 px-5 py-3.5 dark:border-gray-800">
                <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">Distribusi Material (Top 8)</p>
                <p class="mt-0.5 text-xs text-gray-400">Proporsi pemakaian berdasarkan jenis material</p>
            </div>
            <div class="p-5">
                <div class="h-64"><canvas id="chartDistribution"></canvas></div>
            </div>
        </div>

    </div>

    {{-- ── Top 10 Material ─────────────────────────────────────────────────── --}}
    <div class="overflow-hidden rounded-xl border border-gray-100 bg-white dark:border-gray-700/50 dark:bg-gray-900">
        <div class="border-b border-gray-100 px-5 py-3.5 dark:border-gray-800">
            <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">Top 10 Material Tertinggi</p>
            <p class="mt-0.5 text-xs text-gray-400">Material dengan total pemakaian terbanyak dari semua cabang dalam periode berjalan</p>
        </div>
        <div class="p-5">
            <div style="height: {{ max(280, count($chartTopMaterials['labels']) * 44) }}px">
                <canvas id="chartTopMaterials"></canvas>
            </div>
        </div>
    </div>

    {{-- ── Pemakaian per Cabang ────────────────────────────────────────────── --}}
    <div class="overflow-hidden rounded-xl border border-gray-100 bg-white dark:border-gray-700/50 dark:bg-gray-900">
        <div class="border-b border-gray-100 px-5 py-3.5 dark:border-gray-800">
            <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">Total Pemakaian per Cabang</p>
            <p class="mt-0.5 text-xs text-gray-400">Perbandingan total pemakaian material antar cabang</p>
        </div>
        <div class="overflow-x-auto p-5">
            <div style="min-width: {{ max(600, count($chartBranchComparison['labels']) * 70) }}px; height: 300px;">
                <canvas id="chartBranchComparison"></canvas>
            </div>
        </div>
    </div>

    {{-- ── Tabel Ranking Material ──────────────────────────────────────────── --}}
    @if(!empty($materialTable))
    <div class="overflow-hidden rounded-xl border border-gray-100 bg-white dark:border-gray-700/50 dark:bg-gray-900">
        <div class="border-b border-gray-100 px-5 py-3.5 dark:border-gray-800">
            <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">Ranking Material — Top 10</p>
            <p class="mt-0.5 text-xs text-gray-400">Detail pemakaian material berdasarkan data ESB</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:border-gray-800 dark:bg-gray-800/50 dark:text-gray-400">
                        <th class="px-5 py-3 text-left">#</th>
                        <th class="px-5 py-3 text-left">Nama Material</th>
                        <th class="px-5 py-3 text-right">Satuan</th>
                        <th class="px-5 py-3 text-right">Total Pemakaian</th>
                        <th class="px-5 py-3 text-right">Jumlah Cabang</th>
                        <th class="px-5 py-3 text-right">Rata-rata / Hari</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach($materialTable as $i => $mat)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40">
                        <td class="px-5 py-3 text-gray-400">{{ $i + 1 }}</td>
                        <td class="px-5 py-3 font-medium text-gray-800 dark:text-gray-100">{{ $mat['name'] }}</td>
                        <td class="px-5 py-3 text-right text-xs font-semibold uppercase text-gray-500">{{ $mat['unit'] }}</td>
                        <td class="px-5 py-3 text-right font-mono font-semibold text-blue-600 dark:text-blue-400">
                            {{ number_format($mat['totalQty'], 4, ',', '.') }}
                        </td>
                        <td class="px-5 py-3 text-right">
                            <span class="inline-flex items-center rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-semibold text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300">
                                {{ $mat['branchCount'] }} cabang
                            </span>
                        </td>
                        <td class="px-5 py-3 text-right font-mono text-gray-600 dark:text-gray-300">
                            {{ number_format($mat['avgPerDay'], 4, ',', '.') }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    @else

    <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-gray-200 bg-white py-16 dark:border-gray-700 dark:bg-gray-900">
        <svg class="h-12 w-12 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"/>
        </svg>
        <p class="mt-4 text-sm font-medium text-gray-500 dark:text-gray-400">Pilih periode dan klik <strong>Tarik Data</strong> untuk memuat analisis dari ESB</p>
        <p class="mt-1 text-xs text-gray-400">Data material usage diambil langsung dari sistem ESB per cabang</p>
    </div>

    @endif

</div>

</x-filament-panels::page>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>
<script>Chart.register(ChartDataLabels);</script>
@endpush
