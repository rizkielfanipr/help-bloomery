@php
    $calendar = $this->calendarData;
    $dayNames = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];

    $dotColor = fn (?string $status): string => match ($status) {
        'approved'  => 'bg-green-500',
        'supervisor_review' => 'bg-amber-400',
        'submitted' => 'bg-blue-400',
        'rejected'  => 'bg-red-500',
        'missing'   => 'bg-red-300',
        'upcoming'  => 'bg-white/40 ring-1 ring-white/60',
        default     => '',
    };
@endphp

<div class="flex flex-col bg-blue-600 dark:bg-blue-900" style="min-height:100dvh">

    {{-- Header --}}
    <div class="flex-shrink-0 px-5 pb-6 pt-14">
        <div class="mb-4 flex items-center gap-3">
            <a href="{{ route('filament.casual.pages.daily-briefing-page') }}"
               class="flex h-9 w-9 items-center justify-center rounded-full bg-white/20 text-white transition active:bg-white/30">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
                </svg>
            </a>
            <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/20 text-white">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>
                </svg>
            </div>
            <span class="text-base font-semibold text-white">Kalender Briefing</span>
        </div>

        {{-- Month navigation --}}
        <div class="flex items-center justify-between">
            <button wire:click="previousMonth"
                    class="p-1 text-white/70 transition active:text-white">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
                </svg>
            </button>
            <span class="text-lg font-semibold capitalize text-white">{{ $calendar['monthLabel'] }}</span>
            <button wire:click="nextMonth"
                    class="p-1 text-white/70 transition active:text-white">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
                </svg>
            </button>
        </div>
    </div>

    {{-- White card --}}
    <div class="flex-1 overflow-y-auto rounded-t-3xl bg-gray-50 pb-28 pt-5 dark:bg-gray-950">
        <div class="px-4">

            {{-- Reminder cards --}}
            @if($calendar['missedDailyCount'] > 0 || $calendar['missedWeeklyCount'] > 0 || $calendar['monthlyStatus'] === 'missing')
                <div class="mb-4 space-y-2">
                    @if($calendar['missedDailyCount'] > 0)
                        <div class="flex items-center gap-2.5 rounded-xl bg-red-50 px-3 py-2.5 dark:bg-red-950">
                            <div class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full bg-red-100 dark:bg-red-900">
                                <svg class="h-4 w-4 text-red-600 dark:text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-xs font-semibold text-red-700 dark:text-red-400">Harian Belum Submit</p>
                                <p class="text-xs text-red-600 dark:text-red-500">{{ $calendar['missedDailyCount'] }} hari belum disubmit bulan ini</p>
                            </div>
                        </div>
                    @endif
                    @if($calendar['missedWeeklyCount'] > 0)
                        <div class="flex items-center gap-2.5 rounded-xl bg-orange-50 px-3 py-2.5 dark:bg-orange-950">
                            <div class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full bg-orange-100 dark:bg-orange-900">
                                <svg class="h-4 w-4 text-orange-600 dark:text-orange-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-xs font-semibold text-orange-700 dark:text-orange-400">Mingguan Belum Submit</p>
                                <p class="text-xs text-orange-600 dark:text-orange-500">{{ $calendar['missedWeeklyCount'] }} minggu belum disubmit · jadwal setiap Senin</p>
                            </div>
                        </div>
                    @endif
                    @if($calendar['monthlyStatus'] === 'missing')
                        <div class="flex items-center gap-2.5 rounded-xl bg-pink-50 px-3 py-2.5 dark:bg-pink-950">
                            <div class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full bg-pink-100 dark:bg-pink-900">
                                <svg class="h-4 w-4 text-pink-600 dark:text-pink-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-xs font-semibold text-pink-700 dark:text-pink-400">Bulanan Belum Submit</p>
                                <p class="text-xs text-pink-600 dark:text-pink-500">Belum disubmit bulan ini · jadwal tanggal 1</p>
                            </div>
                        </div>
                    @endif
                </div>
            @endif

            {{-- Schedule info --}}
            <div class="mb-4 flex gap-2">
                <div class="flex flex-1 items-center gap-1.5 rounded-xl bg-white px-3 py-2 ring-1 ring-black/5 dark:bg-gray-900">
                    <div class="h-2 w-2 flex-shrink-0 rounded-full bg-blue-400 ring-1 ring-white/60"></div>
                    <div>
                        <p class="text-xs font-medium text-gray-700 dark:text-gray-300">Mingguan</p>
                        <p class="text-xs text-gray-400">Setiap Senin</p>
                    </div>
                </div>
                <div class="flex flex-1 items-center gap-1.5 rounded-xl bg-white px-3 py-2 ring-1 ring-black/5 dark:bg-gray-900">
                    <div class="h-2 w-2 flex-shrink-0 rounded-full bg-blue-400 ring-1 ring-white/60"></div>
                    <div>
                        <p class="text-xs font-medium text-gray-700 dark:text-gray-300">Bulanan</p>
                        <p class="text-xs text-gray-400">Setiap tgl 1</p>
                    </div>
                </div>
            </div>

            {{-- Day name header --}}
            <div class="mb-1 grid grid-cols-7 text-center">
                @foreach($dayNames as $dayName)
                    <div class="py-1 text-xs font-medium text-gray-400">{{ $dayName }}</div>
                @endforeach
            </div>

            {{-- Calendar grid --}}
            <div class="grid grid-cols-7 gap-1">
                @for($i = 0; $i < $calendar['startWeekday']; $i++)
                    <div></div>
                @endfor

                @foreach($calendar['days'] as $day)
                    @php
                        $cellBg = $day['isToday']
                            ? 'bg-blue-600 dark:bg-blue-700'
                            : ($day['isFuture'] ? 'bg-white/60 dark:bg-gray-900/60' : 'bg-white dark:bg-gray-900');
                        $dayTextColor = $day['isToday']
                            ? 'text-white font-bold'
                            : ($day['isFuture'] ? 'text-gray-300 dark:text-gray-600' : 'text-gray-700 dark:text-gray-300');
                        $hasExtra = $day['weeklyStatus'] !== null || $day['monthlyStatus'] !== null;
                    @endphp

                    <div class="flex aspect-square flex-col items-center justify-center rounded-xl ring-1 ring-black/5 {{ $cellBg }} {{ $hasExtra ? 'ring-blue-200 dark:ring-blue-800 ring-2' : '' }}">
                        <span class="text-xs leading-none {{ $dayTextColor }}">{{ $day['day'] }}</span>

                        {{-- Status dots --}}
                        @php
                            $dots = [];
                            if ($day['dailyStatus'] !== null) $dots[] = $day['dailyStatus'];
                            if ($day['weeklyStatus'] !== null) $dots[] = $day['weeklyStatus'];
                            if ($day['monthlyStatus'] !== null) $dots[] = $day['monthlyStatus'];
                        @endphp

                        @if(count($dots))
                            <div class="mt-1 flex items-center gap-0.5">
                                @foreach($dots as $dot)
                                    <div class="h-1.5 w-1.5 rounded-full {{ $dotColor($dot) }}"></div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            {{-- Legend --}}
            <div class="mt-5 space-y-2">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Keterangan titik</p>
                <div class="grid grid-cols-2 gap-x-4 gap-y-1.5">
                    <div class="flex items-center gap-1.5">
                        <div class="h-2 w-2 flex-shrink-0 rounded-full bg-green-500"></div>
                        <span class="text-xs text-gray-500">Approved</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <div class="h-2 w-2 flex-shrink-0 rounded-full bg-amber-400"></div>
                        <span class="text-xs text-gray-500">Menunggu review</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <div class="h-2 w-2 flex-shrink-0 rounded-full bg-blue-400"></div>
                        <span class="text-xs text-gray-500">Tersubmit</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <div class="h-2 w-2 flex-shrink-0 rounded-full bg-red-500"></div>
                        <span class="text-xs text-gray-500">Rejected</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <div class="h-2 w-2 flex-shrink-0 rounded-full bg-red-300"></div>
                        <span class="text-xs text-gray-500">Belum submit</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <div class="h-2 w-2 flex-shrink-0 rounded-full bg-white/40 ring-1 ring-gray-300"></div>
                        <span class="text-xs text-gray-500">Jadwal mendatang</span>
                    </div>
                </div>
                <p class="pt-1 text-xs text-gray-400">Sel dengan border ungu = ada jadwal mingguan/bulanan</p>
            </div>

        </div>
    </div>

    <x-briefing.bottom-nav active="calendar" />
</div>
