@php
    $openings = $this->getOpenings();
    $user     = auth()->user();
    $firstName = explode(' ', $user->name)[0];
@endphp

<div class="flex flex-col bg-blue-600 dark:bg-blue-900" style="min-height:100dvh">

    {{-- ── HEADER ─────────────────────────────────────────── --}}
    <div class="flex-shrink-0 px-5 pb-10 pt-14">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-blue-100">Halo, {{ $firstName }} 👋</p>
                <h1 class="mt-0.5 text-2xl font-bold text-white">Lowongan Tersedia</h1>
                <p class="mt-1 text-sm text-blue-200">Daftar cepat, slot terbatas!</p>
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
    <div class="flex-1 overflow-y-auto rounded-t-3xl bg-gray-50 pb-10 pt-6 dark:bg-gray-950">

        @if($openings->isEmpty())
            {{-- Empty state --}}
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
                    <p class="mt-1 text-sm text-gray-400">Hubungi HR untuk informasi lowongan selanjutnya.</p>
                </div>
            </div>

        @else
            <div class="space-y-1 px-4">
                <p class="mb-3 px-1 text-xs font-semibold uppercase tracking-widest text-gray-400">
                    {{ $openings->count() }} lowongan terbuka
                </p>

                @foreach($openings as $opening)
                    @php
                        $remaining  = $opening->total_slots - $opening->registrations_count;
                        $isFull     = $remaining <= 0;
                        $position   = $opening->casualPosition;
                        $shift      = $opening->casualShift;
                        $dateLabel  = $opening->work_date->locale('id')->isoFormat('ddd, D MMM Y');
                        $shiftLabel = $shift ? substr($shift->start_time, 0, 5).'–'.substr($shift->end_time, 0, 5) : '--';
                    @endphp

                    <div class="mb-3 overflow-hidden rounded-2xl bg-white shadow-sm dark:bg-gray-900
                                {{ $isFull ? 'opacity-60' : '' }}">

                        {{-- Card top: position + fee --}}
                        <div class="flex items-start justify-between px-4 pt-4">
                            <div class="flex-1 min-w-0">
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

                            {{-- Location --}}
                            <div class="flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400">
                                <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24"
                                     stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z"/>
                                </svg>
                                <span>{{ $opening->location }}</span>
                            </div>

                            {{-- Shift time --}}
                            <div class="flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400">
                                <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24"
                                     stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                                </svg>
                                <span>{{ $shiftLabel }}</span>
                                @if($shift)
                                    <span class="text-gray-400">({{ $shift->name }})</span>
                                @endif
                            </div>

                        </div>

                        @if($opening->description)
                            <p class="mt-2 px-4 text-xs text-gray-400 italic dark:text-gray-500">
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
                                <span class="rounded-full bg-gray-100 px-4 py-1.5 text-xs font-semibold text-gray-400 dark:bg-gray-800">
                                    Penuh
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
                                    <span wire:loading.remove wire:target="registerOpening({{ $opening->id }})">
                                        Daftar Sekarang
                                    </span>
                                    <span wire:loading wire:target="registerOpening({{ $opening->id }})"
                                          class="flex items-center gap-1.5">
                                        <svg class="h-3 w-3 animate-spin" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                        </svg>
                                        Mendaftar...
                                    </span>
                                </button>
                            @endif

                        </div>
                    </div>
                @endforeach
            </div>
        @endif

    </div>{{-- end white card --}}

</div>
