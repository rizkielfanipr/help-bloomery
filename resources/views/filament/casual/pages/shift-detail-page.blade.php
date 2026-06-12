@php
    $registration = $this->registration;
    $opening      = $registration->opening;
    $shift        = $opening->casualShift;
    $position     = $opening->casualPosition;
    $leader       = $opening->postedBy;
    $user         = auth()->user();

    $workDate     = $opening->work_date;
    $shiftStart   = \Carbon\Carbon::parse($workDate->toDateString().' '.$shift->start_time);
    $hoursUntil   = now()->diffInHours($shiftStart, false);
    $isWithin24h  = $hoursUntil >= 0 && $hoursUntil < 24;
    $hasPhone     = ! empty($leader?->phone);

    $shiftLabel   = substr($shift->start_time, 0, 5).'–'.substr($shift->end_time, 0, 5);
    $dateLabel    = $workDate->locale('id')->isoFormat('dddd, D MMMM Y');
    $shiftStartMs = $shiftStart->timestamp * 1000;
    $unreadCount  = $this->unreadCount;
@endphp

<div class="flex flex-col bg-blue-600 dark:bg-blue-900" style="min-height:100dvh"
     x-data="{ confirmCancel: false }">

    {{-- ════════════════════════════════════════
         BLUE HEADER
    ════════════════════════════════════════ --}}
    <div class="flex-shrink-0 px-5 pb-8 pt-14">

        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-blue-100">{{ $dateLabel }}</p>
                <h1 class="mt-0.5 text-xl font-semibold text-white">Detail Shift Kamu</h1>
            </div>
            <div class="flex items-center gap-2">
                {{-- Dark mode toggle --}}
                <button type="button"
                        class="flex h-9 w-9 items-center justify-center rounded-full bg-white/15 text-white transition active:bg-white/25"
                        @click="const isDark = document.documentElement.classList.contains('dark');
                                window.dispatchEvent(new CustomEvent('theme-changed', { detail: isDark ? 'light' : 'dark' }));">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364-.707.707M6.343 17.657l-.707.707M17.657 17.657l-.707-.707M6.343 6.343l-.707-.707M16 12a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z"/>
                    </svg>
                </button>

                {{-- Notifications --}}
                <a href="{{ \App\Filament\Casual\Pages\NotificationPage::getUrl() }}"
                   class="relative flex h-9 w-9 items-center justify-center rounded-full bg-white/15 text-white transition active:bg-white/25">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"/>
                    </svg>
                    @if($unreadCount > 0)
                        <span class="absolute -right-0.5 -top-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[9px] font-semibold text-white">
                            {{ $unreadCount > 9 ? '9+' : $unreadCount }}
                        </span>
                    @endif
                </a>
            </div>
        </div>
    </div>

    {{-- ════════════════════════════════════════
         WHITE CONTENT CARD
    ════════════════════════════════════════ --}}
    <div class="flex-1 overflow-y-auto rounded-t-3xl bg-gray-50 pb-28 pt-6 dark:bg-gray-950">

        {{-- ── Registration info card ── --}}
        <div class="mx-5 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-black/5 dark:bg-gray-900 dark:ring-white/10">

            {{-- Header row: position + status badge --}}
            <div class="flex items-start justify-between px-5 pt-5">
                <div class="flex-1 min-w-0">
                    <span class="inline-block rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-semibold text-green-700 dark:bg-green-900/30 dark:text-green-400">
                        Terdaftar
                    </span>
                    <h2 class="mt-1.5 text-xl font-bold text-gray-900 dark:text-white">
                        {{ $position->name }}
                    </h2>
                </div>
                @if($position->fee_per_day)
                    <div class="ml-3 shrink-0 text-right">
                        <p class="text-lg font-bold text-green-600 dark:text-green-400">
                            Rp {{ number_format($position->fee_per_day, 0, ',', '.') }}
                        </p>
                        <p class="text-xs text-gray-400">/hari</p>
                    </div>
                @endif
            </div>

            {{-- Divider --}}
            <div class="mx-5 mt-4 border-t border-gray-100 dark:border-gray-800"></div>

            {{-- Detail rows --}}
            <div class="space-y-3 px-5 py-4">

                {{-- Date --}}
                <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-blue-50 dark:bg-blue-900/20">
                        <svg class="h-4 w-4 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-400">Tanggal Kerja</p>
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $dateLabel }}</p>
                    </div>
                </div>

                {{-- Shift time --}}
                <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-blue-50 dark:bg-blue-900/20">
                        <svg class="h-4 w-4 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-400">Jam Kerja</p>
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">
                            {{ $shiftLabel }}
                            <span class="font-normal text-gray-400">({{ $shift->name }})</span>
                        </p>
                    </div>
                </div>

                {{-- Location --}}
                <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-blue-50 dark:bg-blue-900/20">
                        <svg class="h-4 w-4 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-400">Lokasi</p>
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $opening->location }}</p>
                    </div>
                </div>

                @if($leader)
                    {{-- Leader --}}
                    <div class="flex items-center gap-3">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-blue-50 dark:bg-blue-900/20">
                            <svg class="h-4 w-4 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-gray-400">Penanggung Jawab</p>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $leader->name }}</p>
                        </div>
                    </div>
                @endif

            </div>

            @if($opening->description)
                <div class="mx-5 mb-4 rounded-xl bg-gray-50 p-3 dark:bg-gray-800">
                    <p class="text-xs text-gray-500 dark:text-gray-400 italic">{{ $opening->description }}</p>
                </div>
            @endif
        </div>

        {{-- ── Countdown card ── --}}
        <div class="mx-5 mt-4 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-black/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="px-5 py-4 text-center"
                 x-data="{
                     target: {{ $shiftStartMs }},
                     days: 0, hours: 0, minutes: 0, seconds: 0,
                     init() {
                         this.tick();
                         setInterval(() => this.tick(), 1000);
                     },
                     tick() {
                         const diff = this.target - Date.now();
                         if (diff <= 0) {
                             this.days = this.hours = this.minutes = this.seconds = 0;
                             return;
                         }
                         this.days    = Math.floor(diff / 86400000);
                         this.hours   = Math.floor((diff % 86400000) / 3600000);
                         this.minutes = Math.floor((diff % 3600000)  / 60000);
                         this.seconds = Math.floor((diff % 60000)    / 1000);
                     },
                     pad(v) { return String(v).padStart(2, '0'); }
                 }">
                <p class="text-xs font-semibold uppercase tracking-widest text-gray-400">Shift dimulai dalam</p>
                <div class="mt-3 flex items-end justify-center gap-1.5">
                    <template x-if="days > 0">
                        <div class="flex flex-col items-center">
                            <span class="font-mono text-3xl font-bold text-blue-600 dark:text-blue-400" x-text="days"></span>
                            <span class="mt-0.5 text-[10px] font-semibold uppercase tracking-wide text-gray-400">hari</span>
                        </div>
                    </template>
                    <template x-if="days > 0">
                        <span class="mb-4 text-xl font-light text-gray-300 dark:text-gray-600">:</span>
                    </template>
                    <div class="flex flex-col items-center">
                        <span class="font-mono text-3xl font-bold text-blue-600 dark:text-blue-400" x-text="pad(hours)"></span>
                        <span class="mt-0.5 text-[10px] font-semibold uppercase tracking-wide text-gray-400">jam</span>
                    </div>
                    <span class="mb-4 text-xl font-light text-gray-300 dark:text-gray-600">:</span>
                    <div class="flex flex-col items-center">
                        <span class="font-mono text-3xl font-bold text-blue-600 dark:text-blue-400" x-text="pad(minutes)"></span>
                        <span class="mt-0.5 text-[10px] font-semibold uppercase tracking-wide text-gray-400">menit</span>
                    </div>
                    <span class="mb-4 text-xl font-light text-gray-300 dark:text-gray-600">:</span>
                    <div class="flex flex-col items-center">
                        <span class="font-mono text-3xl font-bold text-blue-600 dark:text-blue-400" x-text="pad(seconds)"></span>
                        <span class="mt-0.5 text-[10px] font-semibold uppercase tracking-wide text-gray-400">detik</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Action buttons ── --}}
        <div class="mx-5 mt-4 space-y-3">

            @if($isWithin24h)
                {{-- Within 24h: show WhatsApp button --}}
                <div class="rounded-2xl bg-amber-50 p-4 ring-1 ring-amber-200 dark:bg-amber-900/20 dark:ring-amber-700/50">
                    <div class="flex gap-3">
                        <svg class="h-5 w-5 shrink-0 text-amber-500 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                        </svg>
                        <p class="text-sm text-amber-800 dark:text-amber-300">
                            Kurang dari 24 jam sebelum shift. Pembatalan tidak bisa dilakukan. Hubungi penanggung jawab jika ada kendala.
                        </p>
                    </div>
                </div>

                @if($hasPhone)
                    <a href="{{ $this->getWhatsappUrl() }}"
                       target="_blank"
                       class="flex w-full items-center justify-center gap-2.5 rounded-2xl bg-green-500 py-4 text-sm font-bold text-white shadow-sm transition active:scale-95 active:bg-green-600">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z"/>
                        </svg>
                        Hubungi via WhatsApp
                    </a>
                @else
                    <div class="flex w-full items-center justify-center gap-2 rounded-2xl bg-gray-100 py-4 text-sm font-semibold text-gray-400 dark:bg-gray-800">
                        Nomor WA penanggung jawab tidak tersedia
                    </div>
                @endif

            @else
                {{-- More than 24h: show cancel button --}}
                <button @click="confirmCancel = true"
                        class="flex w-full items-center justify-center gap-2 rounded-2xl border-2 border-red-200 bg-red-50 py-4 text-sm font-semibold text-red-600 transition active:bg-red-100 dark:border-red-800/50 dark:bg-red-900/20 dark:text-red-400 dark:active:bg-red-900/40">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M6 18 18 6M6 6l12 12"/>
                    </svg>
                    Batalkan Slot
                </button>
            @endif

        </div>

    </div>{{-- end white card --}}

    {{-- ════════════════════════════════════════
         CANCEL CONFIRMATION MODAL
    ════════════════════════════════════════ --}}
    <div x-show="confirmCancel"
         x-transition:enter="transition duration-200 ease-out"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition duration-150 ease-in"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 backdrop-blur-sm p-4 pb-8"
         style="display:none"
         @click.self="confirmCancel = false">

        <div x-show="confirmCancel"
             x-transition:enter="transition duration-200 ease-out"
             x-transition:enter-start="opacity-0 translate-y-4"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition duration-150 ease-in"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 translate-y-4"
             class="w-full max-w-sm overflow-hidden rounded-3xl bg-white shadow-xl dark:bg-gray-900">

            <div class="p-6 text-center">
                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30">
                    <svg class="h-7 w-7 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                    </svg>
                </div>
                <h3 class="text-base font-bold text-gray-900 dark:text-white">Batalkan Slot?</h3>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Slot posisi <strong>{{ $position->name }}</strong> pada <strong>{{ $workDate->locale('id')->isoFormat('D MMM Y') }}</strong> akan dibatalkan dan slot tersebut kembali terbuka untuk umum.
                </p>
            </div>

            <div class="flex gap-3 border-t border-gray-100 px-6 py-4 dark:border-gray-800">
                <button @click="confirmCancel = false"
                        class="flex-1 rounded-2xl bg-gray-100 py-3.5 text-sm font-semibold text-gray-700 transition active:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:active:bg-gray-700">
                    Tidak
                </button>
                <button wire:click="cancelSlot"
                        wire:loading.attr="disabled"
                        @click="confirmCancel = false"
                        class="flex flex-1 items-center justify-center gap-2 rounded-2xl bg-red-500 py-3.5 text-sm font-bold text-white shadow-sm transition active:bg-red-600 disabled:opacity-50">
                    <span wire:loading.remove wire:target="cancelSlot">Ya, Batalkan</span>
                    <span wire:loading wire:target="cancelSlot" class="flex items-center gap-1.5">
                        <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        Membatalkan...
                    </span>
                </button>
            </div>

        </div>
    </div>

    {{-- ════════════════════════════════════════
         FIXED BOTTOM NAVIGATION
    ════════════════════════════════════════ --}}
    <div class="fixed inset-x-0 bottom-0 z-30 flex border-t border-gray-100 bg-white/95 backdrop-blur-sm dark:border-gray-800 dark:bg-gray-900/95">

        {{-- Home (active) --}}
        <a href="{{ \App\Filament\Casual\Pages\ClockPage::getUrl() }}"
           class="flex flex-1 flex-col items-center gap-1 py-3">
            <svg class="h-6 w-6 text-blue-600" fill="currentColor" viewBox="0 0 24 24">
                <path d="M11.47 3.841a.75.75 0 0 1 1.06 0l8.69 8.69a.75.75 0 1 0 1.06-1.061l-8.689-8.69a2.25 2.25 0 0 0-3.182 0l-8.69 8.69a.75.75 0 1 0 1.061 1.06l8.69-8.689Z"/>
                <path d="m12 5.432 8.159 8.159c.03.03.06.058.091.086v6.198c0 1.035-.84 1.875-1.875 1.875H15a.75.75 0 0 1-.75-.75v-4.5a.75.75 0 0 0-.75-.75h-3a.75.75 0 0 0-.75.75V21a.75.75 0 0 1-.75.75H5.625a1.875 1.875 0 0 1-1.875-1.875v-6.198a2.29 2.29 0 0 0 .091-.086L12 5.432Z"/>
            </svg>
            <span class="text-xs font-semibold text-blue-600">Beranda</span>
        </a>

        {{-- Attendance --}}
        <a href="{{ \App\Filament\Casual\Pages\AttendancePage::getUrl() }}"
           class="flex flex-1 flex-col items-center gap-1 py-3">
            <svg class="h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>
            </svg>
            <span class="text-xs text-gray-400">Absensi</span>
        </a>

        {{-- Reports --}}
        <a href="{{ \App\Filament\Casual\Pages\ReportPage::getUrl() }}"
           class="flex flex-1 flex-col items-center gap-1 py-3">
            <svg class="h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/>
            </svg>
            <span class="text-xs text-gray-400">Laporan</span>
        </a>

        {{-- Profile --}}
        <a href="{{ \App\Filament\Casual\Pages\ProfilePage::getUrl() }}"
           class="flex flex-1 flex-col items-center gap-1 py-3">
            <svg class="h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
            </svg>
            <span class="text-xs text-gray-400">Profil</span>
        </a>

    </div>

</div>
