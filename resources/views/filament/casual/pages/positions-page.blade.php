@php
    $openings          = $this->availableOpenings;
    $myRegistrationMap = $this->myRegistrationMap;   // [opening_id => registration_id]
    $myRegistrations   = $this->myRegistrations;
    $myRegisteredDates = $this->myRegisteredDates;   // ['Y-m-d', ...]
    $user              = auth()->user();
    $firstName         = explode(' ', $user->name)[0];
@endphp

<div class="flex flex-col bg-blue-600 dark:bg-blue-900" style="min-height:100dvh">

    {{-- ── HEADER ─────────────────────────────────────────── --}}
    <div class="flex-shrink-0 px-5 pb-10 pt-14">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-blue-100">Selamat Datang, {{ $firstName }}</p>
                <h1 class="mt-0.5 text-2xl font-bold text-white">Lowongan Tersedia</h1>
                <p class="mt-1 text-sm text-blue-200">Segera daftar, ketersediaan slot terbatas.</p>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/20">
                <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 0 0 .75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 0 0-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0 1 12 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 0 1-.673-.38m0 0A2.18 2.18 0 0 1 3 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 0 1 3.413-.387m7.5 0V5.25A2.25 2.25 0 0 0 13.5 3h-3a2.25 2.25 0 0 0-2.25 2.25v.894m7.5 0a48.667 48.667 0 0 0-7.5 0"/>
                </svg>
            </div>
        </div>
    </div>

    {{-- ── WHITE CONTENT CARD ──────────────────────────────── --}}
    <div class="flex-1 overflow-y-auto rounded-t-3xl bg-gray-50 pb-28 pt-6 dark:bg-gray-950">

        {{-- ── JADWAL SAYA ──────────────────────────────────── --}}
        @if($myRegistrations->isNotEmpty())
            <div class="mb-2 px-5">
                <p class="text-xs font-semibold uppercase tracking-widest text-gray-400">
                    Jadwal Saya ({{ $myRegistrations->count() }})
                </p>
            </div>

            <div class="space-y-2 px-4 mb-5">
                @foreach($myRegistrations as $reg)
                    @php
                        $regOpening  = $reg->opening;
                        $regPosition = $regOpening->casualPosition;
                        $regShift    = $regOpening->casualShift;
                        $regLeader   = $regOpening->postedBy;
                        $hasPhone    = ! empty($regLeader?->phone);
                        $regDate     = $regOpening->work_date->locale('id')->isoFormat('dddd, D MMM Y');
                        $regTime     = $regShift
                            ? substr($regShift->start_time, 0, 5).'–'.substr($regShift->end_time, 0, 5)
                            : '--';
                        $canCancel   = $this->canCancelRegistration($reg);
                    @endphp
                    <div class="overflow-hidden rounded-2xl bg-white ring-1 ring-black/5 dark:bg-gray-900 dark:ring-white/10">

                        {{-- Header --}}
                        <div class="flex items-center gap-3 border-b border-gray-100 px-4 py-3 dark:border-gray-800">
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-teal-50 dark:bg-teal-900/20">
                                <svg class="h-5 w-5 text-teal-600 dark:text-teal-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                                </svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-teal-600 dark:text-teal-400">Terdaftar</p>
                                <p class="truncate font-bold text-gray-900 dark:text-white">{{ $regPosition->name }}</p>
                            </div>
                            <p class="shrink-0 text-sm font-bold text-green-600 dark:text-green-400">
                                Rp {{ number_format($regPosition->fee_per_day, 0, ',', '.') }}
                            </p>
                        </div>

                        {{-- Detail --}}
                        <div class="space-y-1.5 px-4 py-3">
                            <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                                <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>
                                </svg>
                                <span>{{ $regDate }}</span>
                            </div>
                            <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                                <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                                </svg>
                                <span>{{ $regTime }}</span>
                                @if($regShift)<span class="text-gray-400">({{ $regShift->name }})</span>@endif
                            </div>
                            <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                                <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z"/>
                                </svg>
                                <span>{{ $regOpening->location }}</span>
                            </div>
                        </div>

                        {{-- Actions --}}
                        <div class="space-y-2 border-t border-gray-100 px-4 py-3 dark:border-gray-800">
                            @if($canCancel)
                                <button
                                    wire:click="cancelRegistration({{ $reg->id }})"
                                    wire:confirm="Batalkan pendaftaran untuk {{ $regPosition->name }} pada {{ $regOpening->work_date->translatedFormat('d M Y') }}?"
                                    wire:loading.attr="disabled"
                                    wire:target="cancelRegistration({{ $reg->id }})"
                                    class="flex w-full items-center justify-center gap-2 rounded-xl border border-red-200 bg-red-50 py-2.5 text-sm font-semibold text-red-600 transition active:bg-red-100 disabled:opacity-50 dark:border-red-800/40 dark:bg-red-900/20 dark:text-red-400"
                                >
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                                    </svg>
                                    <span wire:loading.remove wire:target="cancelRegistration({{ $reg->id }})">Batalkan Pendaftaran</span>
                                    <span wire:loading wire:target="cancelRegistration({{ $reg->id }})">Memproses...</span>
                                </button>
                            @else
                                <div class="flex items-center gap-2 rounded-xl bg-gray-50 px-4 py-2.5 dark:bg-gray-800">
                                    <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/>
                                    </svg>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Pembatalan tidak dapat dilakukan kurang dari 24 jam sebelum shift. Hubungi HR Admin untuk bantuan.</p>
                                </div>
                            @endif

                            @if($hasPhone)
                                <a href="{{ $this->getWhatsappUrl($reg->id) }}"
                                   target="_blank"
                                   class="flex w-full items-center justify-center gap-2 rounded-xl bg-green-500 py-2.5 text-sm font-semibold text-white transition active:scale-95 active:bg-green-600">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z"/>
                                    </svg>
                                    Hubungi via WhatsApp
                                </a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- ── LOWONGAN TERSEDIA ─────────────────────────────── --}}
        @if($openings->isEmpty())
            <div class="flex flex-col items-center justify-center gap-4 px-8 py-24 text-center">
                <div class="flex h-20 w-20 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                    <svg class="h-10 w-10 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24"
                         stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 0 0 .75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 0 0-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0 1 12 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 0 1-.673-.38m0 0A2.18 2.18 0 0 1 3 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 0 1 3.413-.387m7.5 0V5.25A2.25 2.25 0 0 0 13.5 3h-3a2.25 2.25 0 0 0-2.25 2.25v.894m7.5 0a48.667 48.667 0 0 0-7.5 0"/>
                    </svg>
                </div>
                <div>
                    <p class="font-semibold text-gray-700 dark:text-gray-300">Belum Ada Lowongan</p>
                    <p class="mt-1 text-sm text-gray-400">Silakan hubungi HR Admin untuk informasi lowongan yang tersedia.</p>
                </div>
            </div>

        @else
            <div class="space-y-1 px-4">
                <p class="mb-3 px-1 text-xs font-semibold uppercase tracking-widest text-gray-400">
                    {{ $openings->count() }} lowongan tersedia
                </p>

                @foreach($openings as $opening)
                    @php
                        $remaining       = $opening->total_slots - $opening->registrations_count;
                        $isFull          = $remaining <= 0;
                        $isRegistered    = isset($myRegistrationMap[$opening->id]);
                        $dateConflict    = ! $isRegistered && in_array($opening->work_date->toDateString(), $myRegisteredDates);
                        $canRegister     = ! $isRegistered && ! $isFull && ! $dateConflict;
                        $position     = $opening->casualPosition;
                        $shift        = $opening->casualShift;
                        $dateLabel    = $opening->work_date->locale('id')->isoFormat('ddd, D MMM Y');
                        $shiftLabel   = $shift ? substr($shift->start_time, 0, 5).'–'.substr($shift->end_time, 0, 5) : '--';
                    @endphp

                    <div class="mb-3 overflow-hidden rounded-2xl bg-white shadow-sm dark:bg-gray-900
                                {{ ($isFull || $dateConflict) ? 'opacity-60' : '' }}">

                        {{-- Card top: position + fee --}}
                        <div class="flex items-start justify-between px-4 pt-4">
                            <div class="min-w-0 flex-1">
                                <span class="inline-block rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-semibold text-blue-600 dark:bg-blue-900/40 dark:text-blue-300">
                                    {{ $dateLabel }}
                                </span>
                                <h3 class="mt-1.5 truncate text-base font-bold text-gray-900 dark:text-white">
                                    {{ $position->name }}
                                </h3>
                            </div>
                            <div class="ml-3 shrink-0 text-right">
                                <p class="text-base font-bold text-green-600 dark:text-green-400">
                                    Rp {{ number_format($position->fee_per_day, 0, ',', '.') }}
                                </p>
                                <p class="text-xs text-gray-400">/hari</p>
                            </div>
                        </div>

                        {{-- Details row --}}
                        <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1.5 px-4">
                            <div class="flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400">
                                <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z"/>
                                </svg>
                                <span>{{ $opening->location }}</span>
                            </div>
                            <div class="flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400">
                                <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                                </svg>
                                <span>{{ $shiftLabel }}</span>
                                @if($shift)<span class="text-gray-400">({{ $shift->name }})</span>@endif
                            </div>
                        </div>

                        @if($opening->description)
                            <p class="mt-2 px-4 text-xs italic text-gray-400 dark:text-gray-500">
                                {{ $opening->description }}
                            </p>
                        @endif

                        {{-- Footer: slot + action --}}
                        <div class="mt-3 flex items-center justify-between border-t border-gray-100 px-4 py-3 dark:border-gray-800">

                            {{-- Slot badge --}}
                            <div class="flex items-center gap-1.5">
                                <svg class="h-4 w-4 {{ $isFull ? 'text-red-400' : 'text-green-500' }}"
                                     fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/>
                                </svg>
                                @if($isFull)
                                    <span class="text-xs font-semibold text-red-500">Slot penuh</span>
                                @else
                                    <span class="text-xs font-semibold text-green-600 dark:text-green-400">
                                        {{ $remaining }} slot tersisa
                                    </span>
                                    <span class="text-xs text-gray-400">(dari {{ $opening->total_slots }})</span>
                                @endif
                            </div>

                            {{-- Register button --}}
                            @if($isFull)
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-red-100 px-3 py-1.5 text-xs font-semibold text-red-600 dark:bg-red-900/30 dark:text-red-400">
                                    <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636"/>
                                    </svg>
                                    Slot Penuh
                                </span>
                            @elseif($isRegistered)
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-teal-50 px-3 py-1.5 text-xs font-semibold text-teal-600 dark:bg-teal-900/30 dark:text-teal-400">
                                    <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                                    </svg>
                                    Terdaftar
                                </span>
                            @elseif($dateConflict)
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-3 py-1.5 text-xs font-semibold text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">
                                    <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>
                                    </svg>
                                    Tanggal Terisi
                                </span>
                            @else
                                <button
                                    wire:click="registerOpening({{ $opening->id }})"
                                    wire:loading.attr="disabled"
                                    wire:loading.class="opacity-50 cursor-not-allowed"
                                    wire:target="registerOpening({{ $opening->id }})"
                                    class="rounded-full bg-blue-600 px-5 py-1.5 text-xs font-bold text-white shadow-sm
                                           transition active:scale-95 active:bg-blue-700
                                           disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    <span wire:loading.remove wire:target="registerOpening({{ $opening->id }})">Daftar</span>
                                    <span wire:loading wire:target="registerOpening({{ $opening->id }})" class="flex items-center gap-1.5">
                                        <svg class="h-3 w-3 animate-spin" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                        </svg>
                                        Memproses...
                                    </span>
                                </button>
                            @endif

                        </div>
                    </div>
                @endforeach
            </div>
        @endif

    </div>{{-- end white card --}}

    <x-casual.bottom-nav active="positions" />

</div>
