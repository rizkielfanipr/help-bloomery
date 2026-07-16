@php
    $calendar = $this->calendarData;
    $dayNames = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
@endphp

<div class="flex flex-col bg-blue-600 dark:bg-blue-900" style="min-height:100dvh">

    {{-- HEADER --}}
    <div class="flex-shrink-0 px-5 pb-6 pt-14">
        <div class="mb-4 flex items-center gap-3">
            <a href="{{ route('filament.casual.pages.stock-card-page') }}"
               wire:navigate
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
            <span class="text-base font-semibold text-white">Kalender Stock Card</span>
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

    {{-- WHITE CONTENT CARD --}}
    <div class="flex-1 overflow-y-auto rounded-t-3xl bg-gray-50 pb-28 pt-5 dark:bg-gray-950">
        <div class="px-4">

            {{-- Summary cards --}}
            @if($calendar['pastDaysCount'] > 0)
                <div class="mb-4 grid grid-cols-2 gap-3">
                    <div class="rounded-xl bg-white px-3 py-3 ring-1 ring-black/5 dark:bg-gray-900">
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">Submit</p>
                        <p class="mt-1 text-xl font-bold text-blue-600">{{ $calendar['submittedCount'] }}</p>
                        <p class="text-[11px] text-gray-400">dari {{ $calendar['pastDaysCount'] }} hari</p>
                    </div>
                    <div class="rounded-xl bg-white px-3 py-3 ring-1 ring-black/5 dark:bg-gray-900">
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">Belum Submit</p>
                        <p class="mt-1 text-xl font-bold {{ $calendar['missedCount'] > 0 ? 'text-red-500' : 'text-gray-300' }}">
                            {{ $calendar['missedCount'] }}
                        </p>
                        <p class="text-[11px] text-gray-400">hari terlewat</p>
                    </div>
                </div>
            @endif

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
                            : ($day['isFuture']
                                ? 'bg-white/60 dark:bg-gray-900/60'
                                : ($day['isSubmitted'] ? 'bg-blue-50 ring-blue-200 dark:bg-blue-900/20 dark:ring-blue-800' : 'bg-white dark:bg-gray-900'));
                        $dayTextColor = $day['isToday']
                            ? 'text-white font-bold'
                            : ($day['isFuture'] ? 'text-gray-300 dark:text-gray-600' : 'text-gray-700 dark:text-gray-300');
                    @endphp

                    <a href="{{ route('filament.casual.pages.stock-card-entry-page') }}?date={{ $day['date'] }}"
                       wire:navigate
                       class="flex aspect-square flex-col items-center justify-center rounded-xl ring-1 ring-black/5 {{ $cellBg }} {{ $day['isFuture'] ? 'pointer-events-none' : '' }}">
                        <span class="text-xs leading-none {{ $dayTextColor }}">{{ $day['day'] }}</span>

                        @if(! $day['isFuture'])
                            <div class="mt-1 h-1.5 w-1.5 rounded-full
                                {{ $day['isToday'] ? 'bg-white/70' : ($day['isSubmitted'] ? 'bg-blue-500' : 'bg-red-300') }}">
                            </div>
                        @endif
                    </a>
                @endforeach
            </div>

            {{-- Legend --}}
            <div class="mt-5 space-y-2">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Keterangan</p>
                <div class="flex flex-col gap-1.5">
                    <div class="flex items-center gap-2">
                        <div class="h-2 w-2 flex-shrink-0 rounded-full bg-blue-500"></div>
                        <span class="text-xs text-gray-500">Stock card sudah disubmit</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="h-2 w-2 flex-shrink-0 rounded-full bg-red-300"></div>
                        <span class="text-xs text-gray-500">Belum ada stock card</span>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <x-stock-card.bottom-nav active="calendar" />
</div>
