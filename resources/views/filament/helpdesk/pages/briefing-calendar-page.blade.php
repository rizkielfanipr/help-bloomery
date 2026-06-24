<x-filament-panels::page>
@php
    $calendar = $this->calendarData;
    $details  = $this->selectedDetails;
    $dayNames = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];

    $periods = [
        'daily'   => 'Harian',
        'weekly'  => 'Mingguan',
        'monthly' => 'Bulanan',
    ];

    $cellBg = fn (array $day): string => match (true) {
        $day['isSelected']                                                                        => 'bg-violet-100 ring-2 ring-violet-500 dark:bg-violet-900/40',
        $day['isFuture']                                                                          => 'bg-gray-50 dark:bg-gray-800/50',
        ! $day['isScheduled']                                                                     => 'bg-white dark:bg-gray-900',
        $day['stats'] === null                                                                    => 'bg-white dark:bg-gray-900',
        $day['stats']['missing'] === 0 && $day['stats']['approved'] === $day['stats']['total']    => 'bg-green-50 ring-green-200 dark:bg-green-900/20',
        $day['stats']['missing'] === 0                                                            => 'bg-amber-50 ring-amber-200 dark:bg-amber-900/20',
        $day['stats']['submitted'] > 0                                                            => 'bg-orange-50 ring-orange-200 dark:bg-orange-900/20',
        default                                                                                   => 'bg-red-50 ring-red-200 dark:bg-red-900/20',
    };

    $statusBadge = fn (string $status): array => match ($status) {
        'approved'  => ['label' => 'Disetujui',      'class' => 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400'],
        'pending'   => ['label' => 'Menunggu Review', 'class' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400'],
        'rejected'  => ['label' => 'Ditolak',         'class' => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400'],
        'submitted' => ['label' => 'Tersubmit',       'class' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-400'],
        default     => ['label' => 'Belum Submit',    'class' => 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400'],
    };
@endphp

<div class="space-y-4">

    {{-- Period tabs --}}
    <div class="flex gap-2">
        @foreach($periods as $key => $label)
            <button wire:click="setViewPeriod('{{ $key }}')"
                    class="rounded-full px-4 py-1.5 text-sm font-medium transition
                        {{ $viewPeriod === $key
                            ? 'bg-violet-600 text-white shadow-sm'
                            : 'bg-white text-gray-500 ring-1 ring-gray-200 hover:ring-gray-300 dark:bg-gray-800 dark:text-gray-400 dark:ring-gray-700' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">

        {{-- ── Calendar ─────────────────────────────── --}}
        <div class="lg:col-span-2">
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-700">

                {{-- Month navigation --}}
                <div class="mb-4 flex items-center justify-between">
                    <button wire:click="previousMonth"
                            class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
                        </svg>
                    </button>
                    <span class="text-sm font-semibold capitalize text-gray-800 dark:text-gray-100">
                        {{ $calendar['monthLabel'] }}
                    </span>
                    <button wire:click="nextMonth"
                            class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
                        </svg>
                    </button>
                </div>

                {{-- Day name header --}}
                <div class="mb-1 grid grid-cols-7 text-center">
                    @foreach($dayNames as $dn)
                        <div class="py-1 text-xs font-medium text-gray-400 dark:text-gray-500">{{ $dn }}</div>
                    @endforeach
                </div>

                {{-- Calendar grid --}}
                <div class="grid grid-cols-7 gap-1">
                    @for($i = 0; $i < $calendar['startWeekday']; $i++)
                        <div></div>
                    @endfor

                    @foreach($calendar['days'] as $day)
                        @php $bg = $cellBg($day); $stats = $day['stats']; @endphp

                        <button wire:click="selectDate('{{ $day['date'] }}')"
                                class="relative flex flex-col items-center rounded-xl p-1.5 ring-1 transition {{ $bg }}
                                    {{ $day['isFuture'] ? 'cursor-default opacity-40' : 'hover:opacity-80' }}"
                                {{ $day['isFuture'] ? 'disabled' : '' }}>

                            {{-- Day number --}}
                            <span class="flex h-6 w-6 items-center justify-center rounded-full text-xs font-semibold
                                {{ $day['isToday'] ? 'bg-violet-600 text-white' : 'text-gray-700 dark:text-gray-300' }}">
                                {{ $day['day'] }}
                            </span>

                            {{-- Submitted/total fraction --}}
                            @if($stats !== null)
                                <span class="mt-0.5 text-[10px] font-medium leading-none
                                    {{ $stats['missing'] === 0 ? 'text-green-600 dark:text-green-400' : 'text-red-500 dark:text-red-400' }}">
                                    {{ $stats['submitted'] }}/{{ $stats['total'] }}
                                </span>
                            @endif
                        </button>
                    @endforeach
                </div>

                {{-- Legend --}}
                <div class="mt-4 flex flex-wrap gap-x-4 gap-y-1.5 border-t border-gray-100 pt-3 dark:border-gray-800">
                    <div class="flex items-center gap-1.5">
                        <div class="h-3 w-3 rounded bg-green-50 ring-1 ring-green-200"></div>
                        <span class="text-xs text-gray-500">Semua disetujui</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <div class="h-3 w-3 rounded bg-amber-50 ring-1 ring-amber-200"></div>
                        <span class="text-xs text-gray-500">Semua submit, belum semua disetujui</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <div class="h-3 w-3 rounded bg-orange-50 ring-1 ring-orange-200"></div>
                        <span class="text-xs text-gray-500">Sebagian submit</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <div class="h-3 w-3 rounded bg-red-50 ring-1 ring-red-200"></div>
                        <span class="text-xs text-gray-500">Tidak ada yang submit</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Detail Panel ──────────────────────────── --}}
        <div>
            <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-700">

                {{-- Header --}}
                <div class="border-b border-gray-100 px-4 py-3 dark:border-gray-800">
                    @if($selectedDate)
                        <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">
                            {{ \Carbon\Carbon::parse($selectedDate)->locale('id')->isoFormat('dddd, D MMMM Y') }}
                        </p>
                        <p class="mt-0.5 text-xs text-gray-400">{{ $periods[$viewPeriod] }}</p>
                    @else
                        <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">Detail Hari</p>
                        <p class="mt-0.5 text-xs text-gray-400">Pilih tanggal untuk melihat detail</p>
                    @endif
                </div>

                {{-- Content --}}
                <div class="divide-y divide-gray-100 dark:divide-gray-800" style="max-height:520px;overflow-y:auto">

                    @if($selectedDate && empty($details))
                        <div class="px-4 py-8 text-center">
                            @php $d = \Carbon\Carbon::parse($selectedDate); @endphp
                            <p class="text-sm text-gray-400">
                                @if($d->isFuture())
                                    Tanggal belum tiba
                                @elseif($viewPeriod === 'weekly' && ! $d->isMonday())
                                    Bukan hari Senin (jadwal mingguan)
                                @elseif($viewPeriod === 'monthly' && $d->day !== 1)
                                    Bukan tanggal 1 (jadwal bulanan)
                                @else
                                    Tidak ada data staff
                                @endif
                            </p>
                        </div>
                    @elseif(empty($details))
                        <div class="px-4 py-8 text-center">
                            <svg class="mx-auto mb-2 h-8 w-8 text-gray-200 dark:text-gray-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>
                            </svg>
                            <p class="text-sm text-gray-400">Pilih tanggal di kalender</p>
                        </div>
                    @else
                        @foreach($details as $branchData)
                            @php $missing = $branchData['total'] - $branchData['submitted']; @endphp
                            <div class="px-4 py-3">

                                {{-- Branch header --}}
                                <div class="mb-2 flex items-center justify-between gap-2">
                                    <p class="min-w-0 truncate text-xs font-semibold text-gray-700 dark:text-gray-300">
                                        {{ $branchData['branch'] }}
                                    </p>
                                    <span class="flex-shrink-0 rounded-full px-2 py-0.5 text-[10px] font-medium
                                        {{ $missing === 0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600' }}">
                                        {{ $branchData['submitted'] }}/{{ $branchData['total'] }} submit
                                    </span>
                                </div>

                                {{-- User list --}}
                                <div class="space-y-1">
                                    @foreach($branchData['users'] as $user)
                                        @php $badge = $statusBadge($user['status']); @endphp
                                        <div class="flex items-center justify-between gap-2 rounded-lg bg-gray-50 px-2.5 py-1.5 dark:bg-gray-800">
                                            <span class="min-w-0 truncate text-xs text-gray-700 dark:text-gray-300">
                                                {{ $user['name'] }}
                                            </span>
                                            <div class="flex flex-shrink-0 items-center gap-1.5">
                                                @if($user['submittedAt'])
                                                    <span class="text-[10px] text-gray-400">{{ $user['submittedAt'] }}</span>
                                                @endif
                                                <span class="rounded-full px-1.5 py-0.5 text-[10px] font-medium {{ $badge['class'] }}">
                                                    {{ $badge['label'] }}
                                                </span>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    @endif

                </div>
            </div>
        </div>

    </div>
</div>
</x-filament-panels::page>
