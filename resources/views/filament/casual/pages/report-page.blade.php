@php
    $data            = $this->reportData;
    $user            = $data['user'];
    $month           = $data['month'];
    $records         = $data['records'];
    $workingDays     = $data['workingDays'];
    $presentCount    = $data['presentCount'];
    $absentCount     = $data['absentCount'];
    $lateCount       = $data['lateCount'];
    $earlyOutCount   = $data['earlyOutCount'];
    $onTimeCount     = $data['onTimeCount'];
    $totalSeconds    = $data['totalSeconds'];
    $avgSeconds      = $data['avgSeconds'];
    $attendanceRate  = $data['attendanceRate'];
    $punctualityRate = $data['punctualityRate'];

    $isCurrentMonth  = $month->format('Y-m') === now()->format('Y-m');
    $canGoNext       = $month->copy()->addMonth()->lte(now()->startOfMonth());

    $monthNames = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agt','Sep','Okt','Nov','Des'];
    $dayNames   = ['Min','Sen','Sel','Rab','Kam','Jum','Sab'];

    $fmtHours = function(int $s): string {
        $h = intdiv($s, 3600);
        $m = intdiv($s % 3600, 60);
        return $h > 0 ? "{$h}j {$m}m" : "{$m}m";
    };

    // SVG ring: r=40 → circumference ≈ 251.3
    $r   = 40;
    $circ = round(2 * M_PI * $r, 1);
    $dash = round($circ * $attendanceRate / 100, 1);
@endphp

<div class="flex flex-col bg-blue-600 dark:bg-blue-900" style="min-height:100dvh">

    {{-- ════════════════════════════════════════════
         BLUE HEADER
    ════════════════════════════════════════════ --}}
    <div class="flex-shrink-0 px-5 pb-8 pt-14">

        {{-- Top row --}}
        <div class="mb-5 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="{{ \App\Filament\Casual\Pages\ClockPage::getUrl() }}"
                   class="flex h-9 w-9 items-center justify-center rounded-full bg-white/20 text-white transition active:bg-white/30">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
                    </svg>
                </a>
                <h1 class="text-xl font-semibold text-white">Laporan Kinerja</h1>
            </div>

            {{-- Download PDF --}}
            <button wire:click="downloadPdf"
                    wire:loading.attr="disabled"
                    class="flex items-center gap-1.5 rounded-full bg-white/20 px-4 py-2 text-sm font-semibold text-white backdrop-blur-sm transition active:bg-white/30 disabled:opacity-60">
                <span wire:loading.remove wire:target="downloadPdf">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                    </svg>
                </span>
                <span wire:loading wire:target="downloadPdf">
                    <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                </span>
                <span wire:loading.remove wire:target="downloadPdf">PDF</span>
                <span wire:loading wire:target="downloadPdf">...</span>
            </button>
        </div>

        {{-- Month navigator --}}
        <div class="flex items-center justify-center gap-5">
            <button wire:click="previousMonth"
                    class="flex h-9 w-9 items-center justify-center rounded-full bg-white/20 text-white transition active:bg-white/30">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
                </svg>
            </button>
            <div class="text-center">
                <p class="text-xl font-semibold text-white">
                    {{ $monthNames[$month->month - 1] }} {{ $month->year }}
                </p>
                <p class="text-xs text-blue-200">{{ $workingDays }} hari kerja</p>
            </div>
            <button wire:click="nextMonth"
                    @class(['flex h-9 w-9 items-center justify-center rounded-full text-white transition',
                            'bg-white/20 active:bg-white/30' => $canGoNext,
                            'opacity-30 cursor-not-allowed' => !$canGoNext])
                    @disabled(!$canGoNext)>
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
                </svg>
            </button>
        </div>
    </div>

    {{-- ════════════════════════════════════════════
         WHITE CONTENT CARD
    ════════════════════════════════════════════ --}}
    <div class="flex-1 overflow-y-auto rounded-t-3xl bg-gray-50 pb-28 dark:bg-gray-950">

        {{-- ── Attendance Rate Ring ── --}}
        <div class="flex flex-col items-center px-5 pb-6 pt-8">
            <div class="relative flex items-center justify-center">
                <svg width="120" height="120" viewBox="0 0 100 100" class="-rotate-90">
                    {{-- track --}}
                    <circle cx="50" cy="50" r="{{ $r }}" fill="none"
                            stroke="#e5e7eb" stroke-width="10"/>
                    {{-- progress --}}
                    <circle cx="50" cy="50" r="{{ $r }}" fill="none"
                            @class(['transition-all duration-700',
                                    $attendanceRate >= 80 ? 'stroke-green-500' : ($attendanceRate >= 60 ? 'stroke-amber-500' : 'stroke-red-500')])
                            stroke-width="10"
                            stroke-linecap="round"
                            stroke-dasharray="{{ $dash }} {{ $circ }}"
                            stroke-dashoffset="0"/>
                </svg>
                <div class="absolute flex flex-col items-center">
                    <span @class(['text-3xl font-semibold',
                                  $attendanceRate >= 80 ? 'text-green-600' : ($attendanceRate >= 60 ? 'text-amber-500' : 'text-red-500')])>
                        {{ $attendanceRate }}%
                    </span>
                </div>
            </div>
            <p class="mt-2 text-sm font-semibold text-gray-700 dark:text-gray-300">Tingkat Kehadiran</p>
            @if($isCurrentMonth)
                <p class="mt-0.5 text-xs text-gray-400">s.d. hari ini</p>
            @endif
        </div>

        {{-- ── Summary Stats ── --}}
        <div class="px-5">
            <div class="grid grid-cols-3 gap-3">
                {{-- Hadir --}}
                <div class="rounded-2xl bg-white p-4 text-center dark:bg-gray-900">
                    <div class="mb-1 flex justify-center">
                        <div class="flex h-9 w-9 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/40">
                            <svg class="h-5 w-5 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                            </svg>
                        </div>
                    </div>
                    <p class="text-2xl font-semibold text-gray-800 dark:text-white">{{ $presentCount }}</p>
                    <p class="text-xs text-gray-400">Hadir</p>
                </div>

                {{-- Absen --}}
                <div class="rounded-2xl bg-white p-4 text-center dark:bg-gray-900">
                    <div class="mb-1 flex justify-center">
                        <div class="flex h-9 w-9 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/40">
                            <svg class="h-5 w-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                            </svg>
                        </div>
                    </div>
                    <p class="text-2xl font-semibold text-gray-800 dark:text-white">{{ $absentCount }}</p>
                    <p class="text-xs text-gray-400">Absen</p>
                </div>

                {{-- Terlambat --}}
                <div class="rounded-2xl bg-white p-4 text-center dark:bg-gray-900">
                    <div class="mb-1 flex justify-center">
                        <div class="flex h-9 w-9 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900/40">
                            <svg class="h-5 w-5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                            </svg>
                        </div>
                    </div>
                    <p class="text-2xl font-semibold text-gray-800 dark:text-white">{{ $lateCount }}</p>
                    <p class="text-xs text-gray-400">Terlambat</p>
                </div>
            </div>

            {{-- Secondary stats --}}
            <div class="mt-3 grid grid-cols-2 gap-3">
                {{-- Tepat Waktu --}}
                <div class="rounded-2xl bg-white p-4 dark:bg-gray-900">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-blue-100 dark:bg-blue-900/40">
                            <svg class="h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-lg font-semibold leading-none text-gray-800 dark:text-white">{{ $punctualityRate }}%</p>
                            <p class="text-xs text-gray-400">Tepat Waktu</p>
                        </div>
                    </div>
                </div>

                {{-- Keluar Awal --}}
                <div class="rounded-2xl bg-white p-4 dark:bg-gray-900">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-orange-100 dark:bg-orange-900/40">
                            <svg class="h-5 w-5 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12.75"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-lg font-semibold leading-none text-gray-800 dark:text-white">{{ $earlyOutCount }}</p>
                            <p class="text-xs text-gray-400">Keluar Awal</p>
                        </div>
                    </div>
                </div>

                {{-- Total Jam Kerja --}}
                <div class="rounded-2xl bg-white p-4 dark:bg-gray-900">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-purple-100 dark:bg-purple-900/40">
                            <svg class="h-5 w-5 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-lg font-semibold leading-none text-gray-800 dark:text-white">{{ $fmtHours($totalSeconds) }}</p>
                            <p class="text-xs text-gray-400">Total Jam Kerja</p>
                        </div>
                    </div>
                </div>

                {{-- Rata-rata Jam --}}
                <div class="rounded-2xl bg-white p-4 dark:bg-gray-900">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-teal-100 dark:bg-teal-900/40">
                            <svg class="h-5 w-5 text-teal-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-lg font-semibold leading-none text-gray-800 dark:text-white">{{ $fmtHours($avgSeconds) }}</p>
                            <p class="text-xs text-gray-400">Rata-rata/Hari</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Records List ── --}}
        <div class="mt-5 px-5">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Rincian Harian</h2>
                <span class="text-xs text-gray-400">{{ $presentCount }} catatan</span>
            </div>

            @if($records->isEmpty())
                <div class="flex flex-col items-center gap-3 py-10 text-center">
                    <svg class="h-14 w-14 text-gray-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/>
                    </svg>
                    <p class="text-sm font-medium text-gray-400">Tidak ada data untuk bulan ini</p>
                </div>
            @else
                <div class="space-y-2.5">
                    @foreach($records as $rec)
                        @php
                            $duration = $rec->clock_in_at && $rec->clock_out_at
                                ? $fmtHours((int) $rec->clock_in_at->diffInSeconds($rec->clock_out_at))
                                : null;
                        @endphp
                        <div class="flex items-center gap-3 rounded-2xl bg-white px-4 py-3 dark:bg-gray-900">

                            {{-- Date badge --}}
                            <div class="flex w-11 flex-shrink-0 flex-col items-center rounded-xl bg-blue-50 py-2 dark:bg-blue-900/30">
                                <span class="text-[11px] font-semibold uppercase text-blue-400">
                                    {{ $dayNames[$rec->date->dayOfWeek] }}
                                </span>
                                <span class="text-base font-semibold leading-none text-blue-700 dark:text-blue-300">
                                    {{ $rec->date->format('d') }}
                                </span>
                            </div>

                            {{-- Times --}}
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-semibold text-green-600">
                                        {{ $rec->clock_in_at?->format('H:i') ?? '--:--' }}
                                    </span>
                                    <svg class="h-3 w-3 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/>
                                    </svg>
                                    <span class="text-xs font-semibold text-orange-500">
                                        {{ $rec->clock_out_at?->format('H:i') ?? '--:--' }}
                                    </span>
                                    @if($duration)
                                        <span class="text-xs text-gray-400">· {{ $duration }}</span>
                                    @endif
                                </div>
                                <div class="mt-0.5 flex flex-wrap gap-1">
                                    @if($rec->is_late)
                                        <span class="inline-flex items-center gap-0.5 rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-semibold text-red-600 dark:bg-red-900/40">
                                            Telat {{ $rec->late_minutes }}m
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-0.5 rounded-full bg-green-100 px-2 py-0.5 text-[10px] font-semibold text-green-600 dark:bg-green-900/40">
                                            Tepat Waktu
                                        </span>
                                    @endif
                                    @if($rec->is_early_out)
                                        <span class="inline-flex items-center gap-0.5 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-600 dark:bg-amber-900/40">
                                            Awal {{ $rec->early_out_minutes }}m
                                        </span>
                                    @endif
                                </div>
                            </div>

                            {{-- Status dot --}}
                            <div @class(['h-2.5 w-2.5 flex-shrink-0 rounded-full',
                                         'bg-green-400' => !$rec->is_late && !$rec->is_early_out,
                                         'bg-amber-400' => $rec->is_early_out && !$rec->is_late,
                                         'bg-red-400'   => $rec->is_late])>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

    </div>

    {{-- ════════════════════════════════════════════
         BOTTOM NAVIGATION
    ════════════════════════════════════════════ --}}
    <x-casual.bottom-nav active="report" />

</div>
