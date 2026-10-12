<x-filament-panels::page>
    @php
        $inputClass = 'w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white';
        $monthNames = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];
        $isCurrentMonth = (int) $year === now()->year && (int) $month === now()->month;
        ['scored' => $scored, 'unscored' => $unscored] = $this->ranking();
        $summary = $this->summary();
        $canRecompute = $this->canRecompute();
        $recalculableCount = $this->recalculableCount();
        $recalculateLimit = \App\Actions\Briefing\RecomputeBriefingScoresAction::MAX_SCOPE;
        $periodRate = fn ($score, string $period) => $score?->breakdown['periods'][$period]['applicable'] ?? false ? number_format($score->breakdown['periods'][$period]['rate'], 1) : '—';
    @endphp
    <div class="space-y-6">
        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <div class="flex flex-col gap-5 p-5 sm:p-6 md:flex-row md:items-center md:justify-between">
                <div class="flex min-w-0 items-start gap-4">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-950/40 dark:text-blue-300">
                        <x-heroicon-o-star class="h-6 w-6" />
                    </div>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider text-blue-600 dark:text-blue-400">Human Resources · Performance</p>
                        <h2 class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">Nilai Daily Briefing</h2>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-gray-500 dark:text-gray-400">Peringkat branch berdasarkan nilai akhir Daily Briefing bulan yang dipilih.</p>
                    </div>
                </div>
                <div class="flex shrink-0 flex-col items-start gap-2 self-start md:items-end">
                    <div class="flex items-center gap-2 rounded-lg border border-blue-100 bg-blue-50 px-3 py-2 text-sm font-semibold text-blue-700 dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-300">
                        <x-heroicon-o-calendar-days class="h-4 w-4" />
                        {{ $monthNames[(int) $month] }} {{ $year }}
                        @if($isCurrentMonth)
                            <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-700 dark:bg-amber-900/50 dark:text-amber-300">Sementara</span>
                        @endif
                    </div>
                    @if($canRecompute)
                        @php
                            $recalculateScope = $branchId ? ($this->branchOptions()->firstWhere('id', $branchId)?->name ?? 'branch terpilih') : 'semua branch pada filter ini';
                            $canRecalculateNow = $recalculableCount > 0 && $recalculableCount <= $recalculateLimit;
                            $confirmText = "Hitung ulang nilai {$recalculableCount} branch ({$monthNames[(int) $month]} {$year}, {$recalculateScope})? Nilai lama tetap tersimpan sampai perhitungan baru berhasil. Proses berjalan bertahap, biarkan halaman ini tetap terbuka.";
                        @endphp
                        <div
                            class="flex flex-col items-start gap-2 md:items-end"
                            x-on:recompute-briefing-score.window="start()"
                            x-data="{
                                running: false,
                                start() { if (confirm(@js($confirmText))) { this.run(); } },
                                done() { return $wire.recalculationTotal - $wire.recalculationQueue.length; },
                                async run() {
                                    if (this.running) return;
                                    this.running = true;
                                    try {
                                        await $wire.startRecalculation();
                                        while ($wire.recalculationQueue.length > 0) {
                                            await $wire.processRecalculationBatch();
                                        }
                                    } finally {
                                        this.running = false;
                                    }
                                },
                            }"
                        >
                            <button type="button" x-on:click="start()" x-bind:disabled="running || {{ $canRecalculateNow ? 'false' : 'true' }}"
                                class="inline-flex items-center gap-2 rounded-lg border border-blue-200 bg-white px-3 py-2 text-sm font-semibold text-blue-700 transition hover:bg-blue-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-blue-900 dark:bg-gray-900 dark:text-blue-300 dark:hover:bg-blue-950/40">
                                <x-heroicon-o-arrow-path class="h-4 w-4" x-bind:class="running ? 'animate-spin' : ''" />
                                <span x-show="! running">Hitung Ulang</span>
                                <span x-show="running" x-cloak x-text="$wire.recalculationTotal > 0 ? `Menghitung ulang ${done()} / ${$wire.recalculationTotal}` : 'Menghitung ulang...'"></span>
                            </button>
                            <div x-show="running && $wire.recalculationTotal > 0" x-cloak class="h-1.5 w-full max-w-xs overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700" role="progressbar" aria-label="Progres hitung ulang nilai briefing">
                                <div class="h-1.5 rounded-full bg-blue-600 transition-all" x-bind:style="`width: ${$wire.recalculationTotal > 0 ? Math.round(done() / $wire.recalculationTotal * 100) : 0}%`"></div>
                            </div>
                            <p class="max-w-xs text-xs leading-5 text-gray-500 dark:text-gray-400 md:text-right">
                                @if($recalculableCount === 0)
                                    Tidak ada branch peserta pada filter ini.
                                @elseif($recalculableCount > $recalculateLimit)
                                    {{ $recalculableCount }} branch pada filter ini, maksimal {{ $recalculateLimit }} per proses.
                                @else
                                    {{ $recalculableCount }} branch pada filter ini, dihitung bertahap.
                                @endif
                            </p>
                        </div>
                    @endif
                </div>
            </div>
            <div class="flex items-start gap-2 border-t border-gray-200 bg-gray-50/60 px-5 py-3 text-xs leading-5 text-gray-500 dark:border-gray-700 dark:bg-gray-800/30 dark:text-gray-400 sm:px-6"><x-heroicon-o-information-circle class="mt-0.5 h-4 w-4 shrink-0 text-blue-500" /><span>Peringkat mengikuti nilai akhir Daily Briefing berdasarkan occurrence task Approved dan bobot periode pada bulan yang dipilih.</span></div>
        </section>

        <section class="grid gap-4 rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900 sm:grid-cols-2 sm:p-5 lg:grid-cols-4">
            <div><label for="score-year" class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Tahun</label><select id="score-year" wire:model.live="year" class="{{ $inputClass }}">@foreach(range(now()->year, now()->year - 3) as $y)<option value="{{ $y }}">{{ $y }}</option>@endforeach</select></div>
            <div><label for="score-month" class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Bulan</label><select id="score-month" wire:model.live="month" class="{{ $inputClass }}">@foreach($monthNames as $num => $label)<option value="{{ $num }}">{{ $label }}</option>@endforeach</select></div>
            <div><label for="score-branch" class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Branch</label><select id="score-branch" wire:model.live="branchId" class="{{ $inputClass }}"><option value="">Semua Branch</option>@foreach($this->branchOptions() as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select></div>
            <div><label for="score-search" class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Cari Nama Branch</label><div class="flex gap-2"><input id="score-search" type="text" wire:model.live.debounce.400ms="search" placeholder="Cari..." class="{{ $inputClass }}"><button type="button" wire:click="resetFilters" class="shrink-0 rounded-lg border border-gray-300 px-3 py-2.5 text-sm font-semibold text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800">Reset</button></div></div>
        </section>

        <section class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900"><p class="text-xs text-gray-500 dark:text-gray-400">Branch Dinilai</p><p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($summary['count']) }}</p></div>
            <div class="rounded-xl border border-blue-200 bg-blue-50 p-4 dark:border-blue-900 dark:bg-blue-950/30"><p class="text-xs text-blue-700 dark:text-blue-300">Rata-Rata Nilai</p><p class="mt-2 text-2xl font-bold text-blue-900 dark:text-blue-100">{{ number_format($summary['average'], 2) }}</p></div>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-900 dark:bg-emerald-950/30"><p class="text-xs text-emerald-700 dark:text-emerald-300">Achieve</p><p class="mt-2 text-2xl font-bold text-emerald-900 dark:text-emerald-100">{{ number_format($summary['achieve']) }}</p></div>
            <div class="rounded-xl border border-red-200 bg-red-50 p-4 dark:border-red-900 dark:bg-red-950/30"><p class="text-xs text-red-700 dark:text-red-300">Tidak Achieve</p><p class="mt-2 text-2xl font-bold text-red-900 dark:text-red-100">{{ number_format($summary['notAchieve']) }}</p></div>
            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/40"><p class="text-xs text-gray-500 dark:text-gray-400">Belum Dinilai</p><p class="mt-2 text-2xl font-bold text-gray-700 dark:text-gray-200">{{ number_format($summary['unscored']) }}</p></div>
        </section>

        <div class="flex items-center justify-between gap-3">
            <div><h3 class="font-bold text-gray-900 dark:text-white">Peringkat Branch</h3><p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Diurutkan berdasarkan nilai akhir tertinggi, nama branch sebagai tie-breaker.</p></div>
            <span wire:loading wire:target="year,month,branchId,search" role="status" class="shrink-0 text-xs text-blue-600">Memuat…</span>
        </div>
        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[900px] text-sm">
                    <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                        <tr>
                            <th scope="col" class="px-5 py-3 text-left">Peringkat</th>
                            <th scope="col" class="px-5 py-3 text-left">Branch</th>
                            <th scope="col" class="px-5 py-3 text-right">Harian</th>
                            <th scope="col" class="px-5 py-3 text-right">Mingguan</th>
                            <th scope="col" class="px-5 py-3 text-right">Bulanan</th>
                            <th scope="col" class="px-5 py-3 text-right">Nilai Akhir</th>
                            <th scope="col" class="px-5 py-3 text-left">Status</th>
                            <th scope="col" class="px-5 py-3 text-left">Dihitung</th>
                            <th scope="col" class="px-5 py-3 text-right">Detail</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($scored as $index => $row)
                            <tr wire:key="briefing-score-{{ $row->branch->id }}" class="hover:bg-gray-50/70 dark:hover:bg-gray-800/40">
                                <td class="px-5 py-4"><span class="inline-flex min-w-9 items-center justify-center rounded-lg bg-blue-50 px-2 py-1 font-bold text-blue-700 dark:bg-blue-950/40 dark:text-blue-300">{{ $index + 1 }}</span></td>
                                <td class="px-5 py-4"><p class="font-bold text-gray-900 dark:text-white">{{ $row->branch->name }}</p></td>
                                <td class="px-5 py-4 text-right tabular-nums text-gray-600 dark:text-gray-300">{{ $periodRate($row->score, 'daily') }}</td>
                                <td class="px-5 py-4 text-right tabular-nums text-gray-600 dark:text-gray-300">{{ $periodRate($row->score, 'weekly') }}</td>
                                <td class="px-5 py-4 text-right tabular-nums text-gray-600 dark:text-gray-300">{{ $periodRate($row->score, 'monthly') }}</td>
                                <td class="px-5 py-4 text-right font-bold tabular-nums text-blue-700 dark:text-blue-300">{{ number_format($row->score->score, 2) }}</td>
                                <td class="px-5 py-4">
                                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold {{ $row->score->isPassing() ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' : 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300' }}">{{ $row->score->isPassing() ? 'Achieve' : 'Tidak Achieve' }}</span>
                                    @if($row->score->isProvisional())<span class="ml-1 inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">Sementara</span>@endif
                                </td>
                                <td class="px-5 py-4 text-xs text-gray-500 dark:text-gray-400">{{ $row->score->computed_at?->format('d M Y H:i') ?? '-' }}</td>
                                <td class="px-5 py-4 text-right"><button type="button" wire:click="viewDetail({{ $row->branch->id }})" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800" aria-label="Lihat detail nilai {{ $row->branch->name }}"><x-heroicon-o-magnifying-glass class="h-4 w-4" />Detail</button></td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="px-5 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                @if($unscored->isEmpty())
                                    Tidak ada branch peserta Daily Briefing pada filter ini.
                                @else
                                    Belum ada branch yang bernilai pada periode ini. Lihat daftar "Belum Dinilai" di bawah.
                                @endif
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        @if($unscored->isNotEmpty())
            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                <div class="border-b border-gray-200 px-5 py-3 dark:border-gray-700"><h4 class="text-sm font-bold text-gray-900 dark:text-white">Belum Dinilai ({{ $unscored->count() }})</h4><p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Branch peserta yang belum mempunyai nilai pada periode ini — bukan nilai 0.</p></div>
                <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach($unscored as $row)
                        <li class="flex items-center justify-between gap-3 px-5 py-3">
                            <span class="min-w-0 truncate text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $row->branch->name }}</span>
                            @if($canRecompute)<span class="shrink-0 text-xs text-gray-400">Belum Dihitung</span>@endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>

    @if($detailBranchId)
        @php($score = $this->detailScore())
        @php($closeUrl = \App\Filament\Helpdesk\Pages\BriefingScoreWorkspacePage::getUrl(['year' => $year, 'month' => $month, 'branchId' => $branchId, 'search' => $search]))
        <div id="briefing-score-detail" x-data x-trap.inert.noscroll="true" x-on:keydown.escape.window="$wire.closeDetail()" class="fixed inset-0 z-[130] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-label="Detail Nilai Briefing">
            <button type="button" wire:click="closeDetail" class="absolute inset-0 bg-gray-950/60" aria-label="Tutup detail nilai"></button>
            <x-rnd.picker-modal title="Detail Nilai · {{ $score?->branch?->name ?? 'Branch' }}" description="Rincian occurrence, bobot, dan kontribusi per periode.">
                <x-slot:close><button type="button" wire:click="closeDetail" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800" aria-label="Tutup detail nilai"><x-heroicon-o-x-mark class="h-5 w-5" /></button></x-slot:close>
                <div class="max-h-[70vh] space-y-4 overflow-y-auto p-5">
                    @if(! $score)
                        <p class="rounded-xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">Belum ada nilai untuk branch dan periode ini.</p>
                    @else
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full px-3 py-1 text-sm font-bold {{ $score->isPassing() ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' : 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300' }}">{{ number_format($score->score, 2) }} · {{ $score->isPassing() ? 'Achieve' : 'Tidak Achieve' }}</span>
                            @if($score->isProvisional())<span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">Sementara</span>@endif
                            <span class="text-xs text-gray-400">Metode v{{ $score->methodVersion() }} · Dihitung {{ $score->computed_at?->format('d M Y H:i') }}</span>
                        </div>
                        <div class="rounded-xl bg-gray-50 p-3 text-xs leading-5 text-gray-600 dark:bg-gray-800/40 dark:text-gray-300">Periode efektif: {{ $score->breakdown['period_start'] ?? '-' }} s.d. {{ $score->breakdown['period_end'] ?? '-' }} · Passing score: {{ number_format($score->breakdown['passing_score'] ?? 80, 2) }}</div>

                        @foreach(['daily' => 'Harian', 'weekly' => 'Mingguan', 'monthly' => 'Bulanan'] as $key => $label)
                            @php($period = $score->periodBreakdown($key))
                            <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <h5 class="text-sm font-bold text-gray-900 dark:text-white">{{ $label }}</h5>
                                    @if(! $period || ! ($period['applicable'] ?? false))
                                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-500 dark:bg-gray-800 dark:text-gray-400">Tidak Berlaku</span>
                                    @else
                                        <span class="text-sm font-bold text-blue-700 dark:text-blue-300">{{ number_format($period['rate'], 2) }}%</span>
                                    @endif
                                </div>
                                @if($period && ($period['applicable'] ?? false))
                                    <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                                        <div><p class="text-xs text-gray-500 dark:text-gray-400">Approved/Expected</p><p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">{{ $period['approved'] }}/{{ $period['expected'] }}</p></div>
                                        <div><p class="text-xs text-gray-500 dark:text-gray-400">Bobot (Configured)</p><p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">{{ number_format($period['configured_weight'], 2) }}%</p></div>
                                        <div><p class="text-xs text-gray-500 dark:text-gray-400">Bobot (Effective)</p><p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">{{ number_format($period['effective_weight'], 2) }}%</p></div>
                                        <div><p class="text-xs text-gray-500 dark:text-gray-400">Kontribusi</p><p class="mt-1 text-sm font-semibold text-blue-700 dark:text-blue-300">{{ number_format($period['contribution'], 2) }}</p></div>
                                    </div>
                                    @if(! empty($period['tasks']))
                                        <div class="mt-3 overflow-hidden rounded-lg border border-gray-100 dark:border-gray-800">
                                            <table class="w-full text-xs">
                                                <thead class="bg-gray-50 text-gray-500 dark:bg-gray-800/60 dark:text-gray-400"><tr><th class="px-3 py-2 text-left">Task</th><th class="px-3 py-2 text-right">Approved</th><th class="px-3 py-2 text-right">Expected</th><th class="px-3 py-2 text-right">Rate</th></tr></thead>
                                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                                    @foreach($period['tasks'] as $taskRow)
                                                        <tr><td class="px-3 py-2">{{ $taskRow['label'] }}</td><td class="px-3 py-2 text-right tabular-nums">{{ $taskRow['approved'] }}</td><td class="px-3 py-2 text-right tabular-nums">{{ $taskRow['expected'] }}</td><td class="px-3 py-2 text-right tabular-nums">{{ number_format($taskRow['rate'], 1) }}%</td></tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @endif
                                @else
                                    <p class="mt-2 text-xs leading-5 text-gray-500 dark:text-gray-400">Tidak ada task scoreable yang berlaku pada periode ini. Bobotnya didistribusikan ke periode lain.</p>
                                @endif
                            </div>
                        @endforeach
                    @endif
                </div>
                <div class="flex justify-end border-t border-gray-200 px-5 py-4 dark:border-gray-700"><button type="button" wire:click="closeDetail" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Tutup</button></div>
            </x-rnd.picker-modal>
        </div>
    @endif
</x-filament-panels::page>
