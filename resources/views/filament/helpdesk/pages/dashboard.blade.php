<div class="space-y-5">

    {{-- ─── Welcome Banner ───────────────────────────────────────────────── --}}
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-blue-600 via-blue-700 to-indigo-800 px-6 py-8 shadow-xl shadow-blue-900/20 md:px-8">
        <div class="pointer-events-none absolute -right-16 -top-16 h-64 w-64 rounded-full bg-white/5"></div>
        <div class="pointer-events-none absolute -bottom-12 right-24 h-48 w-48 rounded-full bg-indigo-500/20"></div>
        <div class="pointer-events-none absolute right-8 top-4 h-24 w-24 rounded-full bg-white/5"></div>

        <div class="relative flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-medium text-blue-200">{{ now()->translatedFormat('l, d F Y') }}</p>
                @php
                    $hour = now()->setTimezone('Asia/Jakarta')->hour;
                    $greeting = match(true) {
                        $hour >= 4  && $hour < 11 => 'Selamat Pagi',
                        $hour >= 11 && $hour < 15 => 'Selamat Siang',
                        $hour >= 15 && $hour < 19 => 'Selamat Sore',
                        default                    => 'Selamat Malam',
                    };
                    $totalAll = collect($moduleStats)->sum('total');
                    $pendingAll = collect($moduleStats)->sum('pending');
                    $completedAll = collect($moduleStats)->sum('completed');
                @endphp
                <h1 class="mt-1 text-2xl font-bold text-white md:text-3xl">
                    {{ $greeting }}, {{ auth()->user()->name }}!
                </h1>
                <p class="mt-1 text-sm text-blue-200/80">Ringkasan semua modul helpdesk.</p>
            </div>
            <div class="flex shrink-0 gap-3">
                <div class="rounded-2xl bg-white/10 px-4 py-3 text-center backdrop-blur-sm ring-1 ring-white/10">
                    <p class="text-2xl font-bold text-white">{{ number_format($totalAll) }}</p>
                    <p class="mt-0.5 text-xs font-medium text-blue-200">Total</p>
                </div>
                <div class="rounded-2xl bg-white/10 px-4 py-3 text-center backdrop-blur-sm ring-1 ring-white/10">
                    <p class="text-2xl font-bold text-white">{{ number_format($pendingAll) }}</p>
                    <p class="mt-0.5 text-xs font-medium text-blue-200">Aktif</p>
                </div>
                <div class="rounded-2xl bg-emerald-400/20 px-4 py-3 text-center backdrop-blur-sm ring-1 ring-emerald-400/20">
                    <p class="text-2xl font-bold text-white">{{ number_format($completedAll) }}</p>
                    <p class="mt-0.5 text-xs font-medium text-emerald-200">Selesai</p>
                </div>
            </div>
        </div>
    </div>

    {{-- ─── Module Summary Cards ─────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($moduleStats as $mod)
            <a href="{{ $mod['href'] }}"
               class="group relative overflow-hidden rounded-2xl border bg-white p-5 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md dark:bg-[#0F172A] dark:shadow-none {{ $mod['border'] }}">

                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate text-xs font-medium text-slate-500 dark:text-slate-400">{{ $mod['label'] }}</p>
                        <p class="mt-1.5 text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">{{ number_format($mod['total']) }}</p>
                    </div>
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl {{ $mod['icon_bg'] }}">
                        <svg class="h-5 w-5 {{ $mod['icon_color'] }}" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $mod['path'] }}"/>
                        </svg>
                    </div>
                </div>

                <div class="mt-4 flex items-center gap-2">
                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $mod['badge_bg'] }}">
                        <span class="h-1.5 w-1.5 rounded-full bg-current opacity-70"></span>
                        {{ $mod['pending'] }} aktif
                    </span>
                    <span class="text-[11px] text-slate-400 dark:text-slate-600">·</span>
                    <span class="text-[11px] text-slate-400 dark:text-slate-500">{{ $mod['completed'] }} selesai</span>
                </div>
            </a>
        @endforeach
    </div>

    {{-- ─── Trend Chart + Distribution ──────────────────────────────────── --}}
    <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">

        {{-- Multi-line trend chart (2/3 width) --}}
        <div class="xl:col-span-2 rounded-2xl border border-slate-100 bg-white p-5 shadow-sm dark:border-white/5 dark:bg-[#0F172A]">
            <div class="mb-5 flex items-center justify-between">
                <div>
                    <h3 class="font-semibold text-slate-800 dark:text-white">Tren Permintaan</h3>
                    <p class="mt-0.5 text-xs text-slate-400">30 hari terakhir</p>
                </div>
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                    @php
                        $legendColors = ['bg-blue-500', 'bg-indigo-500', 'bg-pink-500', 'bg-amber-400'];
                    @endphp
                    @foreach ($trendDatasets as $i => $ds)
                        <div class="flex items-center gap-1.5">
                            <div class="h-2.5 w-2.5 rounded-full {{ $legendColors[$i] }}"></div>
                            <span class="text-[11px] font-medium text-slate-500 dark:text-slate-400">{{ $ds['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
            <div wire:ignore style="height:220px">
                <canvas id="trendChart"></canvas>
            </div>
        </div>

        {{-- Distribution donut (1/3 width) --}}
        @php
            $distColors = ['bg-blue-500', 'bg-indigo-500', 'bg-pink-500', 'bg-amber-400'];
            $distTotal = array_sum($distributionDataset);
        @endphp
        <div class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm dark:border-white/5 dark:bg-[#0F172A]">
            <div class="mb-4">
                <h3 class="font-semibold text-slate-800 dark:text-white">Distribusi Modul</h3>
                <p class="mt-0.5 text-xs text-slate-400">Perbandingan semua permintaan</p>
            </div>
            <div wire:ignore class="relative flex justify-center" style="height:160px">
                <canvas id="distChart"></canvas>
                <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                    <p class="text-2xl font-extrabold text-slate-800 dark:text-white">{{ $distTotal }}</p>
                    <p class="text-[11px] text-slate-400">Total</p>
                </div>
            </div>
            <div class="mt-4 space-y-2.5">
                @foreach ($distributionLabels as $i => $dlabel)
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <div class="h-2.5 w-2.5 shrink-0 rounded-full {{ $distColors[$i] }}"></div>
                            <span class="text-[13px] text-slate-600 dark:text-slate-300">{{ $dlabel }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-[13px] font-bold text-slate-800 dark:text-white">{{ $distributionDataset[$i] }}</span>
                            <span class="w-8 text-right text-[11px] text-slate-400">
                                {{ $distTotal > 0 ? round(($distributionDataset[$i] / $distTotal) * 100) : 0 }}%
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ─── Recent Requests ─────────────────────────────────────────────── --}}
    <div class="rounded-2xl border border-slate-100 bg-white shadow-sm dark:border-white/5 dark:bg-[#0F172A]">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-white/5">
            <div>
                <h3 class="font-semibold text-slate-800 dark:text-white">Permintaan Terbaru</h3>
                <p class="mt-0.5 text-xs text-slate-400">10 permintaan terakhir dari semua modul</p>
            </div>
        </div>

        @php
            $statusBadge = fn (string $color): string => match ($color) {
                'success' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-400',
                'warning' => 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-400',
                'danger'  => 'bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-500/10 dark:text-red-400',
                'info'    => 'bg-sky-50 text-sky-700 ring-sky-600/20 dark:bg-sky-500/10 dark:text-sky-400',
                'primary' => 'bg-blue-50 text-blue-700 ring-blue-600/20 dark:bg-blue-500/10 dark:text-blue-400',
                default   => 'bg-slate-50 text-slate-600 ring-slate-500/20 dark:bg-slate-500/10 dark:text-slate-400',
            };
        @endphp

        @if (count($recentRequests) > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-50 dark:border-white/5">
                            <th class="px-5 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-600">Modul</th>
                            <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-600">Permintaan</th>
                            <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-600">Pemohon</th>
                            <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-600">Status</th>
                            <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-600">Waktu</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50 dark:divide-white/[0.03]">
                        @foreach ($recentRequests as $req)
                            <tr class="group transition-colors hover:bg-slate-50/70 dark:hover:bg-white/[0.02]">
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center gap-2">
                                        <div class="h-2 w-2 shrink-0 rounded-full {{ $req['dot'] }}"></div>
                                        <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $req['type'] }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3.5">
                                    <a href="{{ $req['href'] }}" class="text-[13px] font-semibold text-slate-700 hover:text-blue-600 dark:text-slate-200 dark:hover:text-blue-400">
                                        {{ \Illuminate\Support\Str::limit($req['label'], 35) }}
                                    </a>
                                </td>
                                <td class="px-4 py-3.5">
                                    <span class="text-[13px] text-slate-500 dark:text-slate-400">{{ $req['sub'] }}</span>
                                </td>
                                <td class="px-4 py-3.5">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold ring-1 ring-inset {{ $statusBadge($req['status_color']) }}">
                                        {{ $req['status_label'] }}
                                    </span>
                                </td>
                                <td class="px-4 py-3.5">
                                    <span class="text-[12px] text-slate-400 dark:text-slate-500">
                                        {{ \Carbon\Carbon::parse($req['date'])->diffForHumans() }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="flex flex-col items-center justify-center px-6 py-16 text-center">
                <div class="flex h-14 w-14 items-center justify-center rounded-full bg-slate-50 dark:bg-white/5">
                    <svg class="h-7 w-7 text-slate-300 dark:text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 13.5h3.86a2.25 2.25 0 0 1 2.012 1.244l.256.512a2.25 2.25 0 0 0 2.013 1.244h3.218a2.25 2.25 0 0 0 2.013-1.244l.256-.512a2.25 2.25 0 0 1 2.013-1.244h3.859m-19.5.338V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18v-4.162c0-.224-.034-.447-.1-.661L19.24 5.338a2.25 2.25 0 0 0-2.15-1.588H6.911a2.25 2.25 0 0 0-2.15 1.588L2.35 13.177a2.25 2.25 0 0 0-.1.661Z"/>
                    </svg>
                </div>
                <p class="mt-4 text-sm font-medium text-slate-500 dark:text-slate-400">Belum ada permintaan</p>
            </div>
        @endif
    </div>

    {{-- ─── Chart.js ─────────────────────────────────────────────────────── --}}
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        (function () {
            const dark         = () => document.documentElement.classList.contains('dark');
            const gridColor    = () => dark() ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.05)';
            const tickColor    = () => dark() ? '#475569' : '#94a3b8';
            const tooltipBg    = () => dark() ? '#1e293b' : '#ffffff';
            const tooltipText  = () => dark() ? '#e2e8f0' : '#0f172a';
            const tooltipMuted = () => dark() ? '#94a3b8' : '#64748b';
            const tooltipBorder = () => dark() ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)';

            // ── Multi-line Trend ─────────────────────────────────────────
            const trendEl = document.getElementById('trendChart');
            if (trendEl) {
                if (window._trendChart) window._trendChart.destroy();
                const ctx = trendEl.getContext('2d');
                const datasets = @json($trendDatasets);
                window._trendChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: @json($trendLabels),
                        datasets: datasets.map(function (ds) {
                            const grad = ctx.createLinearGradient(0, 0, 0, 220);
                            grad.addColorStop(0, ds.color + '20');
                            grad.addColorStop(1, ds.color + '00');
                            return {
                                label: ds.label,
                                data: ds.data,
                                borderColor: ds.color,
                                backgroundColor: grad,
                                fill: true, tension: 0.4, borderWidth: 2,
                                pointRadius: 0, pointHoverRadius: 4,
                                pointHoverBackgroundColor: ds.color,
                                pointHoverBorderColor: '#fff', pointHoverBorderWidth: 2,
                            };
                        }),
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                backgroundColor: tooltipBg(), titleColor: tooltipText(),
                                bodyColor: tooltipMuted(), borderColor: tooltipBorder(),
                                borderWidth: 1, cornerRadius: 10, padding: 12,
                            },
                        },
                        scales: {
                            x: {
                                grid: { color: gridColor(), drawBorder: false },
                                ticks: { color: tickColor(), font: { size: 11 }, maxTicksLimit: 8 },
                                border: { display: false },
                            },
                            y: {
                                beginAtZero: true,
                                grid: { color: gridColor(), drawBorder: false },
                                ticks: { color: tickColor(), font: { size: 11 }, precision: 0, stepSize: 1 },
                                border: { display: false },
                            },
                        },
                    },
                });
            }

            // ── Distribution Donut ───────────────────────────────────────
            const distEl = document.getElementById('distChart');
            if (distEl) {
                if (window._distChart) window._distChart.destroy();
                const data = @json($distributionDataset);
                const hasData = data.some(v => v > 0);
                window._distChart = new Chart(distEl.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: @json($distributionLabels),
                        datasets: [{
                            data: hasData ? data : [1],
                            backgroundColor: hasData
                                ? ['#3b82f6', '#6366f1', '#ec4899', '#f59e0b']
                                : [dark() ? '#1e293b' : '#e5e7eb'],
                            borderWidth: 0,
                            hoverOffset: hasData ? 6 : 0,
                        }],
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false, cutout: '72%',
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                enabled: hasData,
                                backgroundColor: tooltipBg(), titleColor: tooltipText(),
                                bodyColor: tooltipMuted(), borderColor: tooltipBorder(),
                                borderWidth: 1, cornerRadius: 10, padding: 12,
                            },
                        },
                    },
                });
            }
        })();
    });
    </script>

</div>
