@php
    $records    = $this->records;
    $dayNames   = ['Min','Sen','Sel','Rab','Kam','Jum','Sab'];
    $monthNames = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agt','Sep','Okt','Nov','Des'];
@endphp

<div class="flex flex-col bg-blue-600 dark:bg-blue-900"
     style="min-height:100dvh"
     x-data="{ selected: null }"
     @keydown.escape.window="selected = null">

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
            <h1 class="text-xl font-semibold text-white">Riwayat Absensi</h1>
        </div>
    </div>

    {{-- ════════════════════════════════════════════
         WHITE CONTENT CARD
    ════════════════════════════════════════════ --}}
    <div class="flex-1 overflow-y-auto rounded-t-3xl bg-gray-50 pb-28 pt-5 dark:bg-gray-950">

        @if($records->isEmpty())
            <div class="flex flex-col items-center justify-center gap-4 px-8 py-24 text-center">
                <div class="flex h-20 w-20 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                    <svg class="h-10 w-10 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>
                    </svg>
                </div>
                <div>
                    <p class="font-semibold text-gray-700 dark:text-gray-300">Belum Ada Absensi</p>
                    <p class="mt-1 text-sm text-gray-400">Belum ada catatan absensi untuk ditampilkan.</p>
                </div>
            </div>

        @else
            @foreach($records->groupBy(fn ($r) => $r->date->format('Y-m')) as $monthKey => $monthRecords)
                @php
                    $firstDate  = $monthRecords->first()->date;
                    $monthLabel = $monthNames[$firstDate->month - 1].' '.$firstDate->year;
                @endphp

                <div class="mb-2 mt-5 px-5 first:mt-0">
                    <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500">
                        {{ $monthLabel }}
                    </p>
                </div>

                <div class="flex flex-col gap-2 px-5">
                    @foreach($monthRecords as $record)
                        @php
                            $hasClockedIn  = $record->clock_in_at  !== null;
                            $hasClockedOut = $record->clock_out_at !== null;

                            $secs     = ($hasClockedIn && $hasClockedOut)
                                ? $record->clock_in_at->diffInSeconds($record->clock_out_at)
                                : null;
                            $duration = $secs
                                ? ($secs >= 3600
                                    ? intdiv($secs, 3600).'j '.intdiv($secs % 3600, 60).'m'
                                    : intdiv($secs % 3600, 60).'m')
                                : null;

                            $inTime  = $hasClockedIn  ? $record->clock_in_at->format('H:i')  : '--:--';
                            $outTime = $hasClockedOut ? $record->clock_out_at->format('H:i') : '--:--';

                            $inColor  = ! $hasClockedIn  ? 'text-gray-300 dark:text-gray-600'
                                : ($record->is_late      ? 'text-red-500' : 'text-green-600 dark:text-green-400');
                            $outColor = ! $hasClockedOut ? 'text-gray-300 dark:text-gray-600'
                                : ($record->is_early_out ? 'text-amber-500' : 'text-green-600 dark:text-green-400');

                            $otReq = $record->overtimeRequest;
                            $detail = [
                                'record_id'                => $record->id,
                                'date'                     => $dayNames[$record->date->dayOfWeek].', '.$record->date->day.' '.$monthNames[$record->date->month - 1].' '.$record->date->year,
                                'shift'                    => $record->shift?->name,
                                'clock_in'                 => $hasClockedIn  ? $record->clock_in_at->format('H:i')  : null,
                                'clock_out'                => $hasClockedOut ? $record->clock_out_at->format('H:i') : null,
                                'clock_in_photo'           => $record->clock_in_photo  ? \Illuminate\Support\Facades\Storage::disk('b2')->temporaryUrl($record->clock_in_photo, now()->addHour())  : null,
                                'clock_out_photo'          => $record->clock_out_photo ? \Illuminate\Support\Facades\Storage::disk('b2')->temporaryUrl($record->clock_out_photo, now()->addHour()) : null,
                                'clock_in_lat'             => $record->clock_in_lat,
                                'clock_in_lng'             => $record->clock_in_lng,
                                'clock_out_lat'            => $record->clock_out_lat,
                                'clock_out_lng'            => $record->clock_out_lng,
                                'is_late'                  => $record->is_late,
                                'late_minutes'             => $record->late_minutes,
                                'is_early_out'             => $record->is_early_out,
                                'early_out_minutes'        => $record->early_out_minutes,
                                'duration'                 => $duration,
                                'notes'                    => $record->notes,
                                'has_clocked_out'          => $hasClockedOut,
                                'overtime_request_id' => $otReq?->id,
                                'overtime_duration'   => $otReq?->approved_hours
                                    ? (function ($h) {
                                        $m = (int) round($h * 60);
                                        $hours = intdiv($m, 60); $mins = $m % 60;
                                        return $hours > 0 ? "{$hours}j {$mins}m" : "{$mins}m";
                                    })($otReq->approved_hours)
                                    : null,
                                'overtime_reason' => $otReq?->reason,
                                'overtime_fee'    => $otReq?->overtime_fee
                                    ? 'Rp '.number_format((float) $otReq->overtime_fee, 0, ',', '.')
                                    : null,
                            ];
                        @endphp

                        <button type="button"
                                @click="selected = {{ Js::from($detail) }}"
                                class="flex w-full items-center gap-3 rounded-2xl bg-white px-4 py-3.5 text-left ring-1 ring-black/5 transition active:bg-gray-50 dark:bg-gray-900 dark:ring-white/10 dark:active:bg-gray-800">

                            {{-- Date badge --}}
                            <div class="flex w-11 flex-shrink-0 flex-col items-center rounded-xl bg-blue-50 py-2 dark:bg-blue-900/30">
                                <span class="text-[11px] font-semibold uppercase text-blue-400">
                                    {{ $dayNames[$record->date->dayOfWeek] }}
                                </span>
                                <span class="text-base font-bold leading-none text-blue-700 dark:text-blue-300">
                                    {{ $record->date->format('d') }}
                                </span>
                            </div>

                            {{-- Times --}}
                            <div class="min-w-0 flex-1">
                                @if($record->shift)
                                    <span class="mb-1.5 inline-block rounded-full bg-blue-50 px-2 py-0.5 text-[10px] font-semibold text-blue-600 dark:bg-blue-900/30 dark:text-blue-300">
                                        Shift: {{ $record->shift->name }}
                                    </span>
                                @endif
                                <div class="flex items-end gap-3">
                                    <div>
                                        <p class="text-[10px] font-medium text-gray-400">Clock In</p>
                                        <span class="font-mono text-sm font-semibold {{ $inColor }}">{{ $inTime }}</span>
                                    </div>
                                    <svg class="mb-0.5 h-3 w-3 flex-shrink-0 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/>
                                    </svg>
                                    <div>
                                        <p class="text-[10px] font-medium text-gray-400">Clock Out</p>
                                        <span class="font-mono text-sm font-semibold {{ $outColor }}">{{ $outTime }}</span>
                                    </div>
                                    @if($duration)
                                        <span class="mb-0.5 text-xs text-gray-400">· {{ $duration }}</span>
                                    @endif
                                </div>
                            </div>

                            {{-- Chevron --}}
                            <svg class="h-4 w-4 flex-shrink-0 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
                            </svg>
                        </button>
                    @endforeach
                </div>
            @endforeach

            <p class="mt-8 text-center text-xs text-gray-300 dark:text-gray-700">Menampilkan {{ $records->count() }} catatan</p>
        @endif

    </div>

    {{-- ════════════════════════════════════════════
         DETAIL BOTTOM SHEET
    ════════════════════════════════════════════ --}}

    {{-- Backdrop --}}
    <div x-show="selected !== null"
         x-transition:enter="transition duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-40 bg-black/60"
         style="display:none"
         @click="selected = null">
    </div>

    {{-- Sheet --}}
    <div x-show="selected !== null"
         x-transition:enter="transition duration-300 ease-out"
         x-transition:enter-start="translate-y-full"
         x-transition:enter-end="translate-y-0"
         x-transition:leave="transition duration-200 ease-in"
         x-transition:leave-start="translate-y-0"
         x-transition:leave-end="translate-y-full"
         class="fixed bottom-0 left-1/2 -translate-x-1/2 w-full max-w-[430px] z-50 overflow-hidden rounded-t-3xl bg-white dark:bg-gray-900"
         style="max-height:88vh; display:none"
         @click.stop>

        {{-- Drag handle --}}
        <div class="flex justify-center pb-2 pt-3">
            <div class="h-1 w-10 rounded-full bg-gray-200 dark:bg-gray-700"></div>
        </div>

        <div class="overflow-y-auto pb-10" style="max-height:calc(88vh - 28px)">

            {{-- Sheet header --}}
            <div class="flex items-center justify-between px-5 pb-4 pt-2">
                <div>
                    <p class="font-semibold text-gray-900 dark:text-white" x-text="selected?.date"></p>
                    <div class="mt-1.5">
                        <template x-if="selected?.shift">
                            <span class="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-semibold text-blue-700 dark:bg-blue-900/40 dark:text-blue-300"
                                  x-text="'Shift: ' + selected.shift"></span>
                        </template>
                        <template x-if="!selected?.shift">
                            <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-500 dark:bg-gray-800 dark:text-gray-400">Tanpa Shift</span>
                        </template>
                    </div>
                </div>
                <button @click="selected = null"
                        class="flex h-9 w-9 items-center justify-center rounded-full bg-gray-100 text-gray-500 transition active:bg-gray-200 dark:bg-gray-800 dark:text-gray-400">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Photos --}}
            <div class="grid grid-cols-2 gap-3 px-5">

                {{-- Clock-in --}}
                <div>
                    <p class="mb-1.5 text-[11px] font-semibold uppercase tracking-wide text-gray-400">Clock In</p>
                    <template x-if="selected?.clock_in_photo">
                        <a :href="selected.clock_in_photo" target="_blank"
                           class="block overflow-hidden rounded-2xl ring-1 ring-black/5 dark:ring-white/10">
                            <img :src="selected.clock_in_photo"
                                 class="aspect-square w-full object-cover" alt="Foto Masuk">
                        </a>
                    </template>
                    <template x-if="!selected?.clock_in_photo">
                        <div class="flex aspect-square items-center justify-center rounded-2xl bg-gray-100 dark:bg-gray-800">
                            <svg class="h-8 w-8 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z"/>
                            </svg>
                        </div>
                    </template>
                    <div class="mt-1.5">
                        <span class="font-mono text-sm font-bold"
                              :class="selected?.is_late ? 'text-red-500' : 'text-green-600 dark:text-green-400'"
                              x-text="selected?.clock_in ?? '--:--'"></span>
                        <template x-if="selected?.is_late">
                            <p class="text-[11px] text-red-400"
                               x-text="'Terlambat ' + selected.late_minutes + ' menit'"></p>
                        </template>
                    </div>
                </div>

                {{-- Clock-out --}}
                <div>
                    <p class="mb-1.5 text-[11px] font-semibold uppercase tracking-wide text-gray-400">Clock Out</p>
                    <template x-if="selected?.clock_out_photo">
                        <a :href="selected.clock_out_photo" target="_blank"
                           class="block overflow-hidden rounded-2xl ring-1 ring-black/5 dark:ring-white/10">
                            <img :src="selected.clock_out_photo"
                                 class="aspect-square w-full object-cover" alt="Foto Keluar">
                        </a>
                    </template>
                    <template x-if="!selected?.clock_out_photo">
                        <div class="flex aspect-square items-center justify-center rounded-2xl bg-gray-100 dark:bg-gray-800">
                            <svg class="h-8 w-8 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z"/>
                            </svg>
                        </div>
                    </template>
                    <div class="mt-1.5">
                        <span class="font-mono text-sm font-bold"
                              :class="selected?.is_early_out ? 'text-amber-500' : (selected?.clock_out ? 'text-green-600 dark:text-green-400' : 'text-gray-300 dark:text-gray-600')"
                              x-text="selected?.clock_out ?? '--:--'"></span>
                        <template x-if="selected?.is_early_out">
                            <p class="text-[11px] text-amber-400"
                               x-text="'Lebih awal ' + selected.early_out_minutes + ' menit'"></p>
                        </template>
                    </div>
                </div>
            </div>

            {{-- Location --}}
            <div class="mx-5 mt-4 overflow-hidden rounded-2xl bg-gray-50 dark:bg-gray-800">

                {{-- Masuk --}}
                <div class="flex items-center justify-between px-4 py-3">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-green-100 dark:bg-green-900/30">
                            <svg class="h-4 w-4 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-[11px] text-gray-400">Lokasi Clock In</p>
                            <template x-if="selected?.clock_in_lat">
                                <p class="font-mono text-xs font-medium text-gray-700 dark:text-gray-300"
                                   x-text="Number(selected.clock_in_lat).toFixed(5) + ', ' + Number(selected.clock_in_lng).toFixed(5)"></p>
                            </template>
                            <template x-if="!selected?.clock_in_lat">
                                <p class="text-xs text-gray-400">Tidak tersedia</p>
                            </template>
                        </div>
                    </div>
                    <template x-if="selected?.clock_in_lat">
                        <a :href="'https://maps.google.com/?q=' + selected.clock_in_lat + ',' + selected.clock_in_lng"
                           target="_blank"
                           class="flex items-center gap-1 rounded-lg bg-white px-2.5 py-1.5 text-xs font-semibold text-blue-600 ring-1 ring-black/5 transition active:bg-gray-50 dark:bg-gray-700 dark:text-blue-400 dark:ring-white/10">
                            Maps
                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 19.5 15-15m0 0H8.25m11.25 0v11.25"/>
                            </svg>
                        </a>
                    </template>
                </div>

                <div class="mx-4 h-px bg-gray-200 dark:bg-gray-700"></div>

                {{-- Keluar --}}
                <div class="flex items-center justify-between px-4 py-3">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-orange-100 dark:bg-orange-900/30">
                            <svg class="h-4 w-4 text-orange-500 dark:text-orange-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-[11px] text-gray-400">Lokasi Clock Out</p>
                            <template x-if="selected?.clock_out_lat">
                                <p class="font-mono text-xs font-medium text-gray-700 dark:text-gray-300"
                                   x-text="Number(selected.clock_out_lat).toFixed(5) + ', ' + Number(selected.clock_out_lng).toFixed(5)"></p>
                            </template>
                            <template x-if="!selected?.clock_out_lat">
                                <p class="text-xs text-gray-400">Tidak tersedia</p>
                            </template>
                        </div>
                    </div>
                    <template x-if="selected?.clock_out_lat">
                        <a :href="'https://maps.google.com/?q=' + selected.clock_out_lat + ',' + selected.clock_out_lng"
                           target="_blank"
                           class="flex items-center gap-1 rounded-lg bg-white px-2.5 py-1.5 text-xs font-semibold text-blue-600 ring-1 ring-black/5 transition active:bg-gray-50 dark:bg-gray-700 dark:text-blue-400 dark:ring-white/10">
                            Maps
                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 19.5 15-15m0 0H8.25m11.25 0v11.25"/>
                            </svg>
                        </a>
                    </template>
                </div>
            </div>

            {{-- Lembur info — shown when a record exists --}}
            <template x-if="selected?.overtime_request_id">
                <div class="mx-5 mt-4 overflow-hidden rounded-2xl bg-gray-50 dark:bg-gray-800">

                    <div class="flex items-center gap-2.5 px-4 py-3">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-indigo-100 dark:bg-indigo-900/30">
                            <svg class="h-4 w-4 text-indigo-500 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                            </svg>
                        </div>
                        <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">Lembur</span>
                    </div>

                    <div class="space-y-1 border-t border-gray-200 px-4 py-3 dark:border-gray-700">
                        <template x-if="selected?.overtime_duration">
                            <p class="text-[11px] text-gray-400">Durasi: <span class="font-semibold text-gray-700 dark:text-gray-200" x-text="selected.overtime_duration"></span></p>
                        </template>
                        <template x-if="selected?.overtime_fee">
                            <p class="text-[11px] text-gray-400">Fee: <span class="font-semibold text-indigo-600 dark:text-indigo-400" x-text="selected.overtime_fee"></span></p>
                        </template>
                        <template x-if="selected?.overtime_reason">
                            <p class="text-[11px] text-gray-400">Alasan: <span class="text-gray-600 dark:text-gray-300" x-text="selected.overtime_reason"></span></p>
                        </template>
                    </div>

                    <div class="border-t border-gray-200 px-4 py-3 dark:border-gray-700">
                        <button type="button"
                                @click="if (confirm('Batalkan catatan lembur ini?')) $wire.cancelOvertimeRequest(selected.overtime_request_id).then(() => { selected = null })"
                                class="flex w-full items-center justify-center gap-2 rounded-xl border border-red-200 py-2.5 text-sm font-semibold text-red-500 transition active:bg-red-50 dark:border-red-800 dark:text-red-400 dark:active:bg-red-900/20">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                            </svg>
                            Batalkan Lembur
                        </button>
                    </div>
                </div>
            </template>

            {{-- Duration + notes --}}
            <div class="mx-5 mt-3 flex gap-3">
                <template x-if="selected?.duration">
                    <div class="flex flex-1 items-center gap-2.5 rounded-2xl bg-gray-50 px-4 py-3 dark:bg-gray-800">
                        <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                        </svg>
                        <div>
                            <p class="text-[11px] text-gray-400">Durasi Kerja</p>
                            <p class="font-mono text-sm font-semibold text-gray-800 dark:text-white"
                               x-text="selected.duration"></p>
                        </div>
                    </div>
                </template>
                <template x-if="selected?.notes">
                    <div class="flex flex-1 items-start gap-2.5 rounded-2xl bg-gray-50 px-4 py-3 dark:bg-gray-800">
                        <svg class="mt-0.5 h-4 w-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 0 1 .865-.501 48.172 48.172 0 0 0 3.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z"/>
                        </svg>
                        <div>
                            <p class="text-[11px] text-gray-400">Keterangan</p>
                            <p class="text-xs text-gray-700 dark:text-gray-300" x-text="selected.notes"></p>
                        </div>
                    </div>
                </template>
            </div>

        </div>
    </div>

    <x-casual.bottom-nav active="attendance" />

</div>
