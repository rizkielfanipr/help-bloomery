<div class="space-y-5">

    {{-- ─── Welcome Banner ───────────────────────────────────────────────── --}}
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-blue-600 via-blue-700 to-indigo-800 px-6 py-8 shadow-xl shadow-blue-900/20 md:px-8">

        {{-- Decorative circles --}}
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
                @endphp
                <h1 class="mt-1 text-2xl font-bold text-white md:text-3xl">
                    {{ $greeting }}, {{ auth()->user()->name }}!
                </h1>
                <p class="mt-1 text-sm text-blue-200/80">
                    Berikut adalah ringkasan aktivitas helpdesk hari ini.
                </p>
            </div>
            <div class="flex shrink-0 gap-3">
                <div class="rounded-2xl bg-white/10 px-4 py-3 text-center backdrop-blur-sm ring-1 ring-white/10">
                    <p class="text-2xl font-bold text-white">{{ number_format($stats[1]['value']) }}</p>
                    <p class="mt-0.5 text-xs font-medium text-blue-200">Diajukan</p>
                </div>
                <div class="rounded-2xl bg-white/10 px-4 py-3 text-center backdrop-blur-sm ring-1 ring-white/10">
                    <p class="text-2xl font-bold text-white">{{ number_format($stats[2]['value']) }}</p>
                    <p class="mt-0.5 text-xs font-medium text-blue-200">Dikerjakan</p>
                </div>
                <div class="rounded-2xl bg-emerald-400/20 px-4 py-3 text-center backdrop-blur-sm ring-1 ring-emerald-400/20">
                    <p class="text-2xl font-bold text-white">{{ number_format($stats[3]['value']) }}</p>
                    <p class="mt-0.5 text-xs font-medium text-emerald-200">Selesai</p>
                </div>
            </div>
        </div>
    </div>

    {{-- ─── Stats Cards ──────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($stats as $stat)
            <div class="group relative overflow-hidden rounded-2xl border bg-white p-5 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md dark:bg-[#0F172A] dark:shadow-none {{ $stat['border_color'] }}">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate text-xs font-medium text-slate-500 dark:text-slate-400">{{ $stat['label'] }}</p>
                        <p class="mt-1.5 text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">{{ number_format($stat['value']) }}</p>
                        <p class="mt-1 text-xs text-slate-400 dark:text-slate-600">{{ $stat['sub'] }}</p>
                    </div>
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl {{ $stat['bg'] }}">
                        <x-dynamic-component :component="$stat['icon']" class="h-5 w-5 {{ $stat['icon_color'] }}" />
                    </div>
                </div>

                <div class="mt-4 flex items-center gap-2">
                    @if ($stat['change'] !== null)
                        @if ($stat['change'] >= 0)
                            <span class="inline-flex items-center gap-0.5 rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400">
                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 10.5 12 3m0 0 7.5 7.5M12 3v18"/></svg>
                                +{{ $stat['change'] }}%
                            </span>
                        @else
                            <span class="inline-flex items-center gap-0.5 rounded-full bg-red-50 px-2 py-0.5 text-[11px] font-semibold text-red-600 dark:bg-red-500/10 dark:text-red-400">
                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 13.5 12 21m0 0-7.5-7.5M12 21V3"/></svg>
                                {{ $stat['change'] }}%
                            </span>
                        @endif
                        <span class="text-[11px] text-slate-400 dark:text-slate-600">dari bulan lalu</span>
                    @else
                        <span class="text-[11px] text-slate-400 dark:text-slate-600">status saat ini</span>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    {{-- ─── Charts + Table Row ───────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">

        {{-- Recent Tickets Table (2/3 width) --}}
        <div class="xl:col-span-2 rounded-2xl border border-slate-100 bg-white shadow-sm dark:border-white/5 dark:bg-[#0F172A]">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-white/5">
                <div>
                    <h3 class="font-semibold text-slate-800 dark:text-white">Tiket Terbaru</h3>
                    <p class="mt-0.5 text-xs text-slate-400">8 permintaan terakhir</p>
                </div>
                <div class="flex items-center gap-2">
                    <a
                        href="{{ route('filament.helpdesk.resources.service-requests.index') }}"
                        class="flex items-center gap-1.5 rounded-xl bg-blue-600 px-3 py-1.5 text-xs font-medium text-white transition-all hover:bg-blue-700"
                    >
                        Lihat semua
                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
                    </a>
                </div>
            </div>

            @if (count($recentTickets) > 0)
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-50 dark:border-white/5">
                                <th class="px-5 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-600">Ref</th>
                                <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-600">Pemohon</th>
                                <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-600">Divisi</th>
                                <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-600">Status</th>
                                <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-600">Jadwal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50 dark:divide-white/[0.03]">
                            @foreach ($recentTickets as $ticket)
                                @php
                                    $badgeClass = match($ticket['status']) {
                                        'submitted'   => 'bg-blue-50 text-blue-700 ring-blue-600/20 dark:bg-blue-500/10 dark:text-blue-400 dark:ring-blue-500/20',
                                        'in_progress' => 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-400 dark:ring-amber-500/20',
                                        'warranty'    => 'bg-violet-50 text-violet-700 ring-violet-600/20 dark:bg-violet-500/10 dark:text-violet-400 dark:ring-violet-500/20',
                                        'completed'   => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-400 dark:ring-emerald-500/20',
                                        default       => 'bg-slate-50 text-slate-600 ring-slate-500/20',
                                    };
                                @endphp
                                <tr class="group transition-colors hover:bg-slate-50/70 dark:hover:bg-white/[0.02]">
                                    <td class="px-5 py-3.5">
                                        <a href="{{ route('filament.helpdesk.resources.service-requests.view', $ticket['id']) }}"
                                           class="font-mono text-xs font-bold text-blue-600 hover:underline dark:text-blue-400">
                                            {{ $ticket['ref'] }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <span class="text-[13px] font-medium text-slate-700 dark:text-slate-200">{{ $ticket['requestor'] }}</span>
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <span class="text-[13px] text-slate-500 dark:text-slate-400">{{ $ticket['department'] }}</span>
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold ring-1 ring-inset {{ $badgeClass }}">
                                            {{ $ticket['status_label'] }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <span class="text-[12px] text-slate-400 dark:text-slate-500">{{ $ticket['scheduled_date'] }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="flex flex-col items-center justify-center px-6 py-16 text-center">
                    <div class="flex h-14 w-14 items-center justify-center rounded-full bg-slate-50 dark:bg-white/5">
                        <svg class="h-7 w-7 text-slate-300 dark:text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 13.5h3.86a2.25 2.25 0 0 1 2.012 1.244l.256.512a2.25 2.25 0 0 0 2.013 1.244h3.218a2.25 2.25 0 0 0 2.013-1.244l.256-.512a2.25 2.25 0 0 1 2.013-1.244h3.859m-19.5.338V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18v-4.162c0-.224-.034-.447-.1-.661L19.24 5.338a2.25 2.25 0 0 0-2.15-1.588H6.911a2.25 2.25 0 0 0-2.15 1.588L2.35 13.177a2.25 2.25 0 0 0-.1.661Z"/></svg>
                    </div>
                    <p class="mt-4 text-sm font-medium text-slate-500 dark:text-slate-400">Belum ada tiket masuk</p>
                    <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">Tiket baru akan muncul di sini</p>
                </div>
            @endif
        </div>

        {{-- Right column: Donut + Activity --}}
        <div class="flex flex-col gap-4">

            {{-- Status Donut --}}
            @php
                $statusMeta = [
                    ['label' => 'Diajukan',   'color' => '#3b82f6', 'dot' => 'bg-blue-500'],
                    ['label' => 'Dikerjakan', 'color' => '#f59e0b', 'dot' => 'bg-amber-400'],
                    ['label' => 'Garansi',    'color' => '#8b5cf6', 'dot' => 'bg-violet-500'],
                    ['label' => 'Selesai',    'color' => '#10b981', 'dot' => 'bg-emerald-500'],
                ];
                $statusTotal = array_sum($statusDataset);
            @endphp
            <div class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm dark:border-white/5 dark:bg-[#0F172A]">
                <div class="mb-4">
                    <h3 class="font-semibold text-slate-800 dark:text-white">Ringkasan Status</h3>
                    <p class="mt-0.5 text-xs text-slate-400">Distribusi tiket saat ini</p>
                </div>
                <div wire:ignore class="relative flex justify-center" style="height:170px">
                    <canvas id="statusChart"></canvas>
                    <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                        <p class="text-2xl font-extrabold text-slate-800 dark:text-white">{{ $statusTotal }}</p>
                        <p class="text-[11px] text-slate-400">Total Tiket</p>
                    </div>
                </div>
                <div class="mt-4 space-y-2.5">
                    @foreach ($statusMeta as $i => $meta)
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <div class="h-2.5 w-2.5 rounded-full shrink-0 {{ $meta['dot'] }}"></div>
                                <span class="text-[13px] text-slate-600 dark:text-slate-300">{{ $meta['label'] }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-[13px] font-bold text-slate-800 dark:text-white">{{ $statusDataset[$i] }}</span>
                                <span class="w-8 text-right text-[11px] text-slate-400">
                                    {{ $statusTotal > 0 ? round(($statusDataset[$i] / $statusTotal) * 100) : 0 }}%
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Activity Feed --}}
            <div class="flex-1 rounded-2xl border border-slate-100 bg-white p-5 shadow-sm dark:border-white/5 dark:bg-[#0F172A]">
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <h3 class="font-semibold text-slate-800 dark:text-white">Aktivitas Terbaru</h3>
                        <p class="mt-0.5 text-xs text-slate-400">Riwayat perbaikan terkini</p>
                    </div>
                </div>

                @if (count($recentActivity) > 0)
                    <ol class="relative space-y-4">
                        @foreach ($recentActivity as $i => $activity)
                            <li class="relative flex gap-3">
                                @if (!$loop->last)
                                    <div class="absolute left-[14px] top-7 bottom-0 w-px bg-slate-100 dark:bg-white/5"></div>
                                @endif
                                <div class="relative z-10 flex h-7 w-7 shrink-0 items-center justify-center rounded-full ring-4 ring-white dark:ring-[#0F172A] {{ $activity['is_complete'] ? 'bg-emerald-500' : 'bg-amber-400' }}">
                                    @if ($activity['is_complete'])
                                        <svg class="h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                                    @else
                                        <svg class="h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l5.654-4.654"/></svg>
                                    @endif
                                </div>
                                <div class="min-w-0 flex-1 pt-0.5 pb-2">
                                    <p class="text-[13px] font-medium leading-snug text-slate-700 dark:text-slate-200">{{ $activity['title'] }}</p>
                                    <div class="mt-1 flex items-center gap-1.5">
                                        <span class="font-mono text-[11px] font-bold text-blue-600 dark:text-blue-400">{{ $activity['ref'] }}</span>
                                        <span class="text-slate-200 dark:text-slate-700">·</span>
                                        <span class="text-[11px] text-slate-400">{{ $activity['dept'] }}</span>
                                    </div>
                                    <p class="mt-0.5 text-[11px] text-slate-400 dark:text-slate-500">{{ $activity['time'] }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @else
                    <div class="flex flex-col items-center justify-center py-10 text-center">
                        <p class="text-sm text-slate-400 dark:text-slate-500">Belum ada aktivitas</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- ─── Trend Chart (full width) ────────────────────────────────────── --}}
    <div class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm dark:border-white/5 dark:bg-[#0F172A]">
        <div class="mb-5 flex items-center justify-between">
            <div>
                <h3 class="font-semibold text-slate-800 dark:text-white">Tren Tiket</h3>
                <p class="mt-0.5 text-xs text-slate-400">30 hari terakhir</p>
            </div>
            <div class="flex items-center gap-2 rounded-xl bg-blue-50 px-3 py-1.5 dark:bg-blue-500/10">
                <div class="h-2.5 w-2.5 rounded-full bg-blue-500"></div>
                <span class="text-xs font-medium text-blue-600 dark:text-blue-400">Permintaan Servis</span>
            </div>
        </div>
        <div wire:ignore style="height:220px">
            <canvas id="trendChart"></canvas>
        </div>
    </div>

    {{-- ─── Chart.js ─────────────────────────────────────────────────────── --}}
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        (function () {
            const dark        = () => document.documentElement.classList.contains('dark');
            const gridColor   = () => dark() ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.05)';
            const tickColor   = () => dark() ? '#475569' : '#94a3b8';
            const tooltipBg   = () => dark() ? '#1e293b' : '#ffffff';
            const tooltipText = () => dark() ? '#e2e8f0' : '#0f172a';
            const tooltipMuted= () => dark() ? '#94a3b8' : '#64748b';
            const tooltipBorder = () => dark() ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)';

            // ── Trend Line ───────────────────────────────────────────────
            const trendEl = document.getElementById('trendChart');
            if (trendEl) {
                if (window._trendChart) window._trendChart.destroy();
                const ctx = trendEl.getContext('2d');
                const grad = ctx.createLinearGradient(0, 0, 0, 220);
                grad.addColorStop(0, 'rgba(37,99,235,0.18)');
                grad.addColorStop(1, 'rgba(37,99,235,0)');
                window._trendChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: @json($trendLabels),
                        datasets: [{
                            label: 'Tiket',
                            data: @json($trendDataset),
                            borderColor: '#2563eb',
                            backgroundColor: grad,
                            fill: true, tension: 0.4, borderWidth: 2.5,
                            pointRadius: 0, pointHoverRadius: 5,
                            pointHoverBackgroundColor: '#2563eb',
                            pointHoverBorderColor: '#fff', pointHoverBorderWidth: 2,
                        }]
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
                                callbacks: { label: i => `  ${i.formattedValue} tiket` }
                            }
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
                            }
                        }
                    }
                });
            }

            // ── Status Donut ─────────────────────────────────────────────
            const statusEl = document.getElementById('statusChart');
            if (statusEl) {
                if (window._statusChart) window._statusChart.destroy();
                const data = @json($statusDataset);
                const hasData = data.some(v => v > 0);
                window._statusChart = new Chart(statusEl.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: @json($statusLabels),
                        datasets: [{
                            data: hasData ? data : [1],
                            backgroundColor: hasData
                                ? ['#3b82f6','#f59e0b','#8b5cf6','#10b981']
                                : [dark() ? '#1e293b' : '#e5e7eb'],
                            borderWidth: 0,
                            hoverOffset: hasData ? 6 : 0,
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false, cutout: '74%',
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                enabled: hasData,
                                backgroundColor: tooltipBg(), titleColor: tooltipText(),
                                bodyColor: tooltipMuted(), borderColor: tooltipBorder(),
                                borderWidth: 1, cornerRadius: 10, padding: 12,
                            }
                        }
                    }
                });
            }
        })();
    });
    </script>

</div>
