@php
    $records   = $this->records;
    $dayNames  = ['Min','Sen','Sel','Rab','Kam','Jum','Sab'];
    $monthNames= ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agt','Sep','Okt','Nov','Des'];
@endphp

<div class="flex flex-col bg-blue-600 dark:bg-blue-900"
     style="min-height:100dvh">

    {{-- ════════════════════════════════════════════
         BLUE HEADER
    ════════════════════════════════════════════ --}}
    <div class="flex-shrink-0 px-5 pb-8 pt-14">
        <div class="flex items-center gap-3">
            <a href="{{ \App\Filament\Casual\Pages\ClockPage::getUrl() }}"
               class="flex h-9 w-9 items-center justify-center rounded-full bg-white/20 text-white transition active:bg-white/30">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
                </svg>
            </a>
            <h1 class="text-xl font-bold text-white">Riwayat Absensi</h1>
        </div>
    </div>

    {{-- ════════════════════════════════════════════
         WHITE CONTENT CARD
    ════════════════════════════════════════════ --}}
    <div class="flex-1 overflow-y-auto rounded-t-3xl bg-gray-50 pb-28 pt-5 dark:bg-gray-950">

        @if($records->isEmpty())
            {{-- Empty state --}}
            <div class="flex flex-col items-center justify-center gap-4 px-8 py-24 text-center">
                <div class="flex h-20 w-20 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                    <svg class="h-10 w-10 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>
                    </svg>
                </div>
                <div>
                    <p class="font-semibold text-gray-700 dark:text-gray-300">Belum Ada Absensi</p>
                    <p class="mt-1 text-sm text-gray-400">Riwayat absensi kamu akan muncul di sini.</p>
                </div>
            </div>
        @else
            {{-- Group by month --}}
            @foreach($records->groupBy(fn ($r) => $r->date->format('Y-m')) as $monthKey => $monthRecords)
                @php
                    $firstDate = $monthRecords->first()->date;
                    $monthLabel = $monthNames[$firstDate->month - 1] . ' ' . $firstDate->year;
                @endphp

                {{-- Month header --}}
                <div class="mb-2 mt-5 px-5 first:mt-0">
                    <p class="text-xs font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500">
                        {{ $monthLabel }}
                    </p>
                </div>

                {{-- Records for this month --}}
                <div class="flex flex-col gap-3 px-5">
                    @foreach($monthRecords as $record)
                        @php
                            $dateStr  = $dayNames[$record->date->dayOfWeek] . ', ' . $record->date->day . ' ' . $monthNames[$record->date->month - 1] . ' ' . $record->date->year;
                            $hasClockedIn  = $record->clock_in_at  !== null;
                            $hasClockedOut = $record->clock_out_at !== null;

                            // Working hours
                            $workHours = null;
                            if ($hasClockedIn && $hasClockedOut) {
                                $secs = $record->clock_in_at->diffInSeconds($record->clock_out_at);
                                $workHours = sprintf('%02d:%02d', intdiv($secs, 3600), intdiv($secs % 3600, 60));
                            }
                        @endphp

                        <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-black/5 dark:bg-gray-900 dark:ring-white/10">

                            {{-- Card header: date + shift + work hours --}}
                            <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3 dark:border-gray-800">
                                <div class="flex items-center gap-2">
                                    <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full
                                        @if($hasClockedOut) bg-green-100 dark:bg-green-900/30
                                        @elseif($hasClockedIn) bg-blue-100 dark:bg-blue-900/30
                                        @else bg-gray-100 dark:bg-gray-800 @endif">
                                        @if($hasClockedOut)
                                            <svg class="h-4 w-4 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                                            </svg>
                                        @elseif($hasClockedIn)
                                            <span class="h-2 w-2 animate-pulse rounded-full bg-blue-500"></span>
                                        @else
                                            <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>
                                            </svg>
                                        @endif
                                    </div>
                                    <div>
                                        <p class="font-bold text-gray-900 dark:text-white text-sm">{{ $dateStr }}</p>
                                        @if($record->shift)
                                            <p class="text-xs text-gray-400">{{ $record->shift->name }}</p>
                                        @endif
                                    </div>
                                </div>
                                @if($workHours)
                                    <div class="text-right">
                                        <p class="text-xs text-gray-400">Durasi</p>
                                        <p class="font-mono text-sm font-bold text-gray-700 dark:text-gray-300">{{ $workHours }}</p>
                                    </div>
                                @endif
                            </div>

                            {{-- Clock In row --}}
                            <div class="flex items-center justify-between px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl
                                        @if($hasClockedIn) bg-blue-50 dark:bg-blue-900/20 @else bg-gray-50 dark:bg-gray-800 @endif">
                                        <svg class="h-4 w-4 @if($hasClockedIn) text-blue-500 @else text-gray-300 @endif"
                                             fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12.75"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-400">Masuk</p>
                                        <p class="font-mono font-bold text-gray-900 dark:text-white">
                                            {{ $hasClockedIn ? $record->clock_in_at->format('H:i') : '--:--' }}
                                        </p>
                                    </div>
                                </div>

                                @if($hasClockedIn)
                                    @if($record->is_late)
                                        <span class="rounded-full bg-red-100 px-3 py-1 text-xs font-semibold text-red-600 dark:bg-red-900/30 dark:text-red-400">
                                            Terlambat {{ $record->late_minutes }}m
                                        </span>
                                    @else
                                        <span class="rounded-full bg-green-100 px-3 py-1 text-xs font-semibold text-green-700 dark:bg-green-900/30 dark:text-green-400">
                                            Tepat Waktu
                                        </span>
                                    @endif
                                @else
                                    <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-400 dark:bg-gray-800">
                                        Absen
                                    </span>
                                @endif
                            </div>

                            {{-- Clock Out row --}}
                            <div class="flex items-center justify-between border-t border-gray-50 px-4 py-3 dark:border-gray-800/50">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl
                                        @if($hasClockedOut) bg-orange-50 dark:bg-orange-900/20 @else bg-gray-50 dark:bg-gray-800 @endif">
                                        <svg class="h-4 w-4 @if($hasClockedOut) text-orange-500 @else text-gray-300 @endif"
                                             fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9V5.25A2.25 2.25 0 0 1 10.5 3h6a2.25 2.25 0 0 1 2.25 2.25v13.5A2.25 2.25 0 0 1 16.5 21h-6a2.25 2.25 0 0 1-2.25-2.25V15m-3 0-3-3m0 0 3-3m-3 3H15"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-400">Keluar</p>
                                        <p class="font-mono font-bold text-gray-900 dark:text-white">
                                            {{ $hasClockedOut ? $record->clock_out_at->format('H:i') : '--:--' }}
                                        </p>
                                    </div>
                                </div>

                                @if($hasClockedOut)
                                    @if($record->is_early_out)
                                        <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">
                                            Lebih Awal {{ $record->early_out_minutes }}m
                                        </span>
                                    @else
                                        <span class="rounded-full bg-green-100 px-3 py-1 text-xs font-semibold text-green-700 dark:bg-green-900/30 dark:text-green-400">
                                            Tepat Waktu
                                        </span>
                                    @endif
                                @elseif($hasClockedIn)
                                    <span class="rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-600 dark:bg-blue-900/30 dark:text-blue-400">
                                        Belum Keluar
                                    </span>
                                @else
                                    <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-400 dark:bg-gray-800">
                                        —
                                    </span>
                                @endif
                            </div>

                        </div>
                    @endforeach
                </div>
            @endforeach

            <p class="mt-8 text-center text-xs text-gray-300 dark:text-gray-700">Menampilkan {{ $records->count() }} catatan</p>
        @endif

    </div>

    {{-- ════════════════════════════════════════════
         FIXED BOTTOM NAVIGATION
    ════════════════════════════════════════════ --}}
    <div class="fixed inset-x-0 bottom-0 z-30 flex border-t border-gray-100 bg-white/95 backdrop-blur-sm dark:border-gray-800 dark:bg-gray-900/95">

        {{-- Home --}}
        <a href="{{ \App\Filament\Casual\Pages\ClockPage::getUrl() }}"
           class="flex flex-1 flex-col items-center gap-1 py-3">
            <svg class="h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"/>
            </svg>
            <span class="text-xs text-gray-400">Beranda</span>
        </a>

        {{-- Attendance (active) --}}
        <a href="{{ \App\Filament\Casual\Pages\AttendancePage::getUrl() }}"
           class="flex flex-1 flex-col items-center gap-1 py-3">
            <svg class="h-6 w-6 text-blue-600" fill="currentColor" viewBox="0 0 24 24">
                <path fill-rule="evenodd" d="M6.75 2.25A.75.75 0 0 1 7.5 3v1.5h9V3A.75.75 0 0 1 18 3v1.5h.75a3 3 0 0 1 3 3v11.25a3 3 0 0 1-3 3H5.25a3 3 0 0 1-3-3V7.5a3 3 0 0 1 3-3H6V3a.75.75 0 0 1 .75-.75Zm13.5 9a1.5 1.5 0 0 0-1.5-1.5H5.25a1.5 1.5 0 0 0-1.5 1.5v7.5a1.5 1.5 0 0 0 1.5 1.5h13.5a1.5 1.5 0 0 0 1.5-1.5v-7.5Z" clip-rule="evenodd"/>
            </svg>
            <span class="text-xs font-semibold text-blue-600">Absensi</span>
        </a>

        {{-- Reports --}}
        <a href="{{ \App\Filament\Casual\Pages\ReportPage::getUrl() }}"
           class="flex flex-1 flex-col items-center gap-1 py-3">
            <svg class="h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/>
            </svg>
            <span class="text-xs text-gray-400">Laporan</span>
        </a>

        {{-- Profile --}}
        <a href="{{ \App\Filament\Casual\Pages\ProfilePage::getUrl() }}"
           class="flex flex-1 flex-col items-center gap-1 py-3">
            <svg class="h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
            </svg>
            <span class="text-xs text-gray-400">Profil</span>
        </a>

    </div>

</div>
