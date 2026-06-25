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

    // Branches that have NOT submitted for the selected date
    $notSubmitted = collect($details)->filter(fn ($b) => $b['submitted'] < $b['total']);
    $submitted    = collect($details)->filter(fn ($b) => $b['submitted'] >= $b['total'] && $b['submitted'] > 0);
    $totalBranches   = collect($details)->count();
    $submittedCount  = $submitted->count();

    $cellBg = fn (array $day): string => match (true) {
        $day['isSelected']                                                                        => 'bg-violet-50 ring-2 ring-violet-500 dark:bg-violet-900/30',
        $day['isFuture']                                                                          => 'bg-gray-50 dark:bg-gray-800/40',
        ! $day['isScheduled']                                                                     => 'bg-white dark:bg-gray-900',
        $day['stats'] === null                                                                    => 'bg-white dark:bg-gray-900',
        $day['stats']['missing'] === 0 && $day['stats']['approved'] === $day['stats']['total']    => 'bg-emerald-50 ring-1 ring-emerald-200 dark:bg-emerald-900/20 dark:ring-emerald-800',
        $day['stats']['missing'] === 0                                                            => 'bg-amber-50 ring-1 ring-amber-200 dark:bg-amber-900/20 dark:ring-amber-800',
        $day['stats']['submitted'] > 0                                                            => 'bg-orange-50 ring-1 ring-orange-200 dark:bg-orange-900/20 dark:ring-orange-800',
        default                                                                                   => 'bg-red-50 ring-1 ring-red-200 dark:bg-red-900/20 dark:ring-red-800',
    };

    $statusBadge = fn (string $status): array => match ($status) {
        'approved'  => ['label' => 'Disetujui',       'class' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400'],
        'pending'   => ['label' => 'Menunggu Review',  'class' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400'],
        'rejected'  => ['label' => 'Ditolak',          'class' => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400'],
        'submitted' => ['label' => 'Submitted',         'class' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-400'],
        default     => ['label' => 'Belum Submit',     'class' => 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400'],
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

        {{-- ── Calendar (2 cols) ─────────────────────── --}}
        <div class="lg:col-span-2">
            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-700">

                {{-- Month nav --}}
                <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3 dark:border-gray-800">
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

                <div class="p-4">

                    {{-- Day name header --}}
                    <div class="mb-2 grid grid-cols-7 text-center">
                        @foreach($dayNames as $dn)
                            <div class="py-1 text-xs font-medium text-gray-400 dark:text-gray-600">{{ $dn }}</div>
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
                                    class="group relative flex min-h-[68px] flex-col rounded-xl p-1.5 transition {{ $bg }}
                                        {{ $day['isFuture'] || ! $day['isScheduled'] ? 'cursor-default' : 'hover:opacity-80 cursor-pointer' }}"
                                    {{ $day['isFuture'] ? 'disabled' : '' }}>

                                {{-- Day number --}}
                                <span class="flex h-6 w-6 items-center justify-center rounded-full text-xs font-semibold
                                    {{ $day['isToday']
                                        ? 'bg-violet-600 text-white'
                                        : ($day['isSelected'] ? 'text-violet-600 dark:text-violet-400' : 'text-gray-700 dark:text-gray-300') }}">
                                    {{ $day['day'] }}
                                </span>

                            </button>
                        @endforeach
                    </div>

                    {{-- Legend --}}
                    <div class="mt-4 flex flex-wrap gap-x-4 gap-y-1.5 border-t border-gray-100 pt-3 dark:border-gray-800">
                        <div class="flex items-center gap-1.5">
                            <div class="h-3 w-3 rounded bg-emerald-50 ring-1 ring-emerald-200"></div>
                            <span class="text-[11px] text-gray-500">Semua disetujui</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <div class="h-3 w-3 rounded bg-amber-50 ring-1 ring-amber-200"></div>
                            <span class="text-[11px] text-gray-500">Semua submit</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <div class="h-3 w-3 rounded bg-orange-50 ring-1 ring-orange-200"></div>
                            <span class="text-[11px] text-gray-500">Sebagian submit</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <div class="h-3 w-3 rounded bg-red-50 ring-1 ring-red-200"></div>
                            <span class="text-[11px] text-gray-500">Tidak ada</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Side Panel (1 col) ─────────────────────── --}}
        <div class="flex flex-col gap-3">

            {{-- Selected date header --}}
            <div class="rounded-xl bg-violet-600 px-4 py-3 text-white shadow-sm">
                @if($selectedDate)
                    @php
                        $totalStaffAll  = collect($details)->sum('total');
                        $submittedStaff = collect($details)->sum('submitted');
                        $missingStaff   = $totalStaffAll - $submittedStaff;
                    @endphp
                    <p class="text-xs font-medium text-violet-200">{{ $periods[$viewPeriod] }}</p>
                    <p class="mt-0.5 text-sm font-bold">
                        {{ \Carbon\Carbon::parse($selectedDate)->locale('id')->isoFormat('dddd, D MMM Y') }}
                    </p>
                    @if($totalStaffAll > 0)
                        <div class="mt-2 flex items-center gap-2">
                            <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-white/20">
                                <div class="h-full rounded-full bg-white transition-all duration-500"
                                     style="width: {{ round(($submittedStaff / $totalStaffAll) * 100) }}%"></div>
                            </div>
                            <span class="text-xs font-semibold text-white">{{ $submittedStaff }}/{{ $totalStaffAll }} staff</span>
                        </div>
                        <div class="mt-1.5 flex gap-2 text-[11px]">
                            <span class="text-violet-200">{{ $submittedCount }}/{{ $totalBranches }} cabang submit</span>
                            @if($missingStaff > 0)
                                <span class="text-red-300">· {{ $missingStaff }} staff belum</span>
                            @endif
                        </div>
                    @endif
                @else
                    <p class="text-xs font-medium text-violet-200">Kalender Briefing</p>
                    <p class="mt-0.5 text-sm font-bold">Pilih tanggal</p>
                @endif
            </div>

            @if($selectedDate && count($details) > 0)
                @php
                    $belumList = collect($details)->filter(fn ($b) => $b['submitted'] < $b['total'])->values();
                    $sudahList = collect($details)->filter(fn ($b) => $b['submitted'] >= $b['total'] && $b['submitted'] > 0)->values();
                @endphp

                {{-- Tab panel --}}
                <div x-data="{ tab: '{{ $belumList->isNotEmpty() ? 'belum' : 'sudah' }}' }"
                     class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-700">

                    {{-- Tab header --}}
                    <div class="flex border-b border-gray-100 dark:border-gray-800">
                        <button @click="tab = 'belum'"
                                class="relative flex flex-1 items-center justify-center gap-1.5 px-3 py-3 text-xs font-semibold transition"
                                :class="tab === 'belum'
                                    ? 'text-red-600 dark:text-red-400'
                                    : 'text-gray-400 hover:text-gray-600 dark:hover:text-gray-300'">
                            <div class="h-1.5 w-1.5 animate-pulse rounded-full bg-red-500"
                                 x-show="tab !== 'belum'"></div>
                            Belum Submit
                            <span class="rounded-full px-1.5 py-0.5 text-[10px] font-bold leading-none
                                {{ $belumList->isNotEmpty() ? 'bg-red-100 text-red-600 dark:bg-red-900/40 dark:text-red-400' : 'bg-gray-100 text-gray-400 dark:bg-gray-800' }}">
                                {{ $belumList->count() }}
                            </span>
                            <div x-show="tab === 'belum'"
                                 class="absolute bottom-0 left-0 right-0 h-0.5 bg-red-500"></div>
                        </button>
                        <button @click="tab = 'sudah'"
                                class="relative flex flex-1 items-center justify-center gap-1.5 px-3 py-3 text-xs font-semibold transition"
                                :class="tab === 'sudah'
                                    ? 'text-emerald-600 dark:text-emerald-400'
                                    : 'text-gray-400 hover:text-gray-600 dark:hover:text-gray-300'">
                            Sudah Submit
                            <span class="rounded-full px-1.5 py-0.5 text-[10px] font-bold leading-none
                                {{ $sudahList->isNotEmpty() ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400' : 'bg-gray-100 text-gray-400 dark:bg-gray-800' }}">
                                {{ $sudahList->count() }}
                            </span>
                            <div x-show="tab === 'sudah'"
                                 class="absolute bottom-0 left-0 right-0 h-0.5 bg-emerald-500"></div>
                        </button>
                    </div>

                    {{-- Tab: Belum Submit --}}
                    <div x-show="tab === 'belum'" style="max-height:520px;overflow-y:auto">
                        @if($belumList->isEmpty())
                            <div class="px-4 py-8 text-center">
                                <p class="text-sm text-gray-400">Semua cabang sudah submit</p>
                            </div>
                        @else
                            @foreach($belumList as $branch)
                                <div class="flex items-center gap-3 border-b border-gray-100 px-4 py-3 last:border-0 dark:border-gray-800">
                                    <div class="h-2 w-2 flex-shrink-0 animate-pulse rounded-full bg-red-500"></div>
                                    <p class="min-w-0 flex-1 truncate text-xs font-semibold text-gray-800 dark:text-gray-200">{{ $branch['branch'] }}</p>
                                </div>
                            @endforeach
                        @endif
                    </div>

                    {{-- Tab: Sudah Submit --}}
                    <div x-show="tab === 'sudah'" style="max-height:520px;overflow-y:auto">
                        @if($sudahList->isEmpty())
                            <div class="px-4 py-8 text-center">
                                <p class="text-sm text-gray-400">Belum ada cabang yang lengkap</p>
                            </div>
                        @else
                            @foreach($sudahList as $branch)
                                <div class="border-b border-gray-100 last:border-0 dark:border-gray-800">
                                    {{-- Branch header --}}
                                    <div class="flex items-center gap-2 bg-emerald-50 px-4 py-2 dark:bg-emerald-900/10">
                                        <div class="h-2 w-2 flex-shrink-0 rounded-full bg-emerald-500"></div>
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-xs font-bold text-gray-800 dark:text-gray-200">{{ $branch['branch'] }}</p>
                                            @if($branch['firstSubmit'])
                                                <p class="text-[10px] text-gray-400">
                                                    {{ $branch['firstSubmit'] }}{{ $branch['lastSubmit'] !== $branch['firstSubmit'] ? ' – ' . $branch['lastSubmit'] : '' }}
                                                </p>
                                            @endif
                                        </div>
                                        @if($branch['approved'] === $branch['total'])
                                            <span class="flex-shrink-0 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-400">
                                                Disetujui
                                            </span>
                                        @else
                                            <span class="flex-shrink-0 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-700 dark:bg-amber-900/50 dark:text-amber-400">
                                                Menunggu
                                            </span>
                                        @endif
                                    </div>
                                    {{-- User list --}}
                                    <div class="space-y-0.5 px-3 py-2">
                                        @foreach($branch['users'] as $u)
                                            @php $badge = $statusBadge($u['status']); @endphp
                                            <div class="flex items-center justify-between gap-2 rounded-lg bg-gray-50 px-2 py-1.5 dark:bg-gray-800/50">
                                                <div class="flex min-w-0 items-center gap-1.5">
                                                    <div class="h-1.5 w-1.5 flex-shrink-0 rounded-full
                                                        {{ $u['status'] === 'approved' ? 'bg-emerald-500' : ($u['status'] === 'rejected' ? 'bg-red-500' : 'bg-amber-400') }}"></div>
                                                    <span class="truncate text-[11px] text-gray-700 dark:text-gray-300">{{ $u['name'] }}</span>
                                                </div>
                                                <div class="flex flex-shrink-0 items-center gap-1">
                                                    @if($u['submittedAt'])
                                                        <span class="tabular-nums text-[10px] text-gray-400">{{ $u['submittedAt'] }}</span>
                                                    @endif
                                                    <span class="rounded-full px-1.5 py-0.5 text-[9px] font-medium {{ $badge['class'] }}">
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

                </div>{{-- end tab panel --}}

            @elseif($selectedDate)
                <div class="rounded-xl bg-white px-4 py-8 text-center shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-700">
                    @php $d = \Carbon\Carbon::parse($selectedDate); @endphp
                    <svg class="mx-auto mb-3 h-10 w-10 text-gray-200 dark:text-gray-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5"/>
                    </svg>
                    <p class="text-sm text-gray-400">
                        @if($d->isFuture())
                            Tanggal belum tiba
                        @elseif($viewPeriod === 'weekly' && ! $d->isMonday())
                            Bukan hari jadwal (Senin)
                        @elseif($viewPeriod === 'monthly' && $d->day !== 1)
                            Bukan tanggal jadwal (tgl 1)
                        @else
                            Tidak ada data staff
                        @endif
                    </p>
                </div>
            @else
                <div class="rounded-xl bg-white px-4 py-8 text-center shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-700">
                    <svg class="mx-auto mb-3 h-10 w-10 text-gray-200 dark:text-gray-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5"/>
                    </svg>
                    <p class="text-sm text-gray-400">Pilih tanggal di kalender</p>
                </div>
            @endif

        </div>{{-- end side panel --}}
    </div>
</div>
</x-filament-panels::page>
