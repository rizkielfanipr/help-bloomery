@php
    $latest = $this->latestScore;
    $scores = $this->scores;
    $periodLabels = ['daily' => 'Harian', 'weekly' => 'Mingguan', 'monthly' => 'Bulanan'];
@endphp

<div class="flex flex-col bg-blue-600 dark:bg-blue-900" style="min-height:100dvh">

    {{-- Header --}}
    <div class="flex-shrink-0 px-5 pb-8 pt-14">
        <a href="{{ route('filament.casual.pages.launcher-page') }}"
           class="mb-4 inline-flex items-center gap-1.5 text-sm font-medium text-blue-200 hover:text-white">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/>
            </svg>
            Kembali
        </a>
        <h1 class="text-2xl font-extrabold text-white">Nilai Briefing</h1>
        <p class="mt-1 text-sm text-blue-200">Rekap penilaian bulanan kamu</p>
    </div>

    {{-- Content --}}
    <div class="flex-1 overflow-y-auto rounded-t-3xl bg-gray-50 pb-28 pt-5 dark:bg-gray-950">

        <div class="space-y-4 px-5">

            {{-- Latest score card --}}
            @if($latest)
                <div class="rounded-2xl p-5 {{ $latest->isPassing() ? 'bg-emerald-500' : 'bg-red-500' }}">
                    <p class="text-xs font-semibold uppercase tracking-widest text-white/70">Nilai Terbaru</p>
                    <p class="mt-0.5 text-sm font-medium text-white">{{ $latest->monthLabel() }}</p>
                    <div class="mt-3 flex items-end justify-between">
                        <span class="text-5xl font-extrabold text-white">{{ number_format($latest->score, 1) }}<span class="text-2xl">%</span></span>
                        <span class="rounded-full bg-white/20 px-3 py-1 text-sm font-bold text-white">
                            {{ $latest->isPassing() ? '✓ Achieve' : '✗ Tidak Achieve' }}
                        </span>
                    </div>
                    <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-white/20">
                        <div class="h-full rounded-full bg-white" style="width: {{ min($latest->score, 100) }}%"></div>
                    </div>
                    <p class="mt-1 text-[11px] text-white/60">Minimum lulus: 80%</p>
                    <a href="{{ route('casual.exports.briefing-score-pdf', ['year' => $latest->year, 'month' => $latest->month]) }}"
                       target="_blank"
                       class="mt-3 flex items-center gap-1.5 text-xs font-semibold text-white/80 hover:text-white">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0-3-3m3 3 3-3M3 17V7a2 2 0 0 1 2-2h6l2 2h6a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                        </svg>
                        Unduh PDF
                    </a>
                </div>
            @else
                <div class="flex flex-col items-center gap-3 rounded-2xl bg-white px-5 py-12 text-center ring-1 ring-black/5 dark:bg-gray-900 dark:ring-white/10">
                    <div class="flex h-14 w-14 items-center justify-center rounded-full bg-gray-50 dark:bg-gray-800">
                        <svg class="h-7 w-7 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.563.563 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z"/>
                        </svg>
                    </div>
                    <p class="text-sm font-semibold text-gray-500 dark:text-gray-400">Belum ada data nilai</p>
                    <p class="text-xs text-gray-400 dark:text-gray-600">Nilai akan tersedia setelah dihitung oleh admin</p>
                </div>
            @endif

            {{-- History --}}
            @if($scores->count() > 0)
                <div>
                    <p class="mb-3 text-[15px] font-bold text-gray-900 dark:text-white">Riwayat Nilai</p>
                    <div class="space-y-2">
                        @foreach($scores as $score)
                            <div x-data="{ open: false }"
                                 class="overflow-hidden rounded-2xl bg-white ring-1 ring-black/5 dark:bg-gray-900 dark:ring-white/10">

                                <button type="button" @click="open = !open"
                                        class="flex w-full items-center justify-between px-4 py-3.5 text-left">
                                    <div>
                                        <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $score->monthLabel() }}</p>
                                        <p class="text-xs text-gray-400">Dihitung {{ $score->computed_at?->diffForHumans() ?? '-' }}</p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="rounded-full px-2.5 py-0.5 text-xs font-bold {{ $score->isPassing() ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">
                                            {{ number_format($score->score, 1) }}%
                                        </span>
                                        <a href="{{ route('casual.exports.briefing-score-pdf', ['year' => $score->year, 'month' => $score->month]) }}"
                                           target="_blank"
                                           @click.stop
                                           class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-300">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0-3-3m3 3 3-3M3 17V7a2 2 0 0 1 2-2h6l2 2h6a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                                            </svg>
                                        </a>
                                        <svg class="h-4 w-4 text-gray-400 transition-transform" :class="open ? 'rotate-180' : ''"
                                             fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                                        </svg>
                                    </div>
                                </button>

                                <div x-show="open" x-collapse class="border-t border-gray-100 dark:border-white/[0.06]">
                                    <div class="space-y-1.5 px-4 py-3">
                                        @foreach($score->breakdown ?? [] as $item)
                                            <div class="flex items-center justify-between gap-2">
                                                <div class="min-w-0 flex-1">
                                                    <p class="truncate text-xs font-medium text-gray-700 dark:text-gray-300">{{ $item['label'] }}</p>
                                                    <p class="text-[10px] text-gray-400">{{ $periodLabels[$item['period']] ?? $item['period'] }} · {{ $item['approved'] }}/{{ $item['expected'] }} · Bobot {{ $item['weight'] }}%</p>
                                                </div>
                                                <span class="shrink-0 text-xs font-bold {{ $item['score'] > 0 ? 'text-emerald-600' : 'text-red-500' }}">
                                                    {{ number_format($item['score'], 1) }}%
                                                </span>
                                            </div>
                                        @endforeach
                                        <div class="mt-2 flex justify-between border-t border-gray-100 pt-2 dark:border-white/[0.06]">
                                            <span class="text-xs font-semibold text-gray-600 dark:text-gray-300">Total</span>
                                            <span class="text-xs font-bold {{ $score->isPassing() ? 'text-emerald-600' : 'text-red-600' }}">
                                                {{ number_format($score->score, 2) }}% ({{ $score->isPassing() ? 'Achieve' : 'Tidak Achieve' }})
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

        </div>
    </div>

    <x-briefing.bottom-nav active="score" />
</div>
