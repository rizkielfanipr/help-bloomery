@php
    $records = $this->records();
    $tabs = [
        'all'     => 'Semua',
        'daily'   => 'Harian',
        'weekly'  => 'Mingguan',
        'monthly' => 'Bulanan',
    ];
    $dayNames   = ['Min','Sen','Sel','Rab','Kam','Jum','Sab'];
    $monthNames = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agt','Sep','Okt','Nov','Des'];
    $taskCache  = \App\Models\BriefingTask::cached()->keyBy('key');
@endphp

<div class="flex flex-col bg-blue-600 dark:bg-blue-900" style="min-height:100dvh">

    {{-- Header --}}
    <div class="flex-shrink-0 px-5 pb-5 pt-14">
        <div class="mb-1 flex items-center gap-3">
            <a href="{{ route('filament.casual.pages.daily-briefing-page') }}"
               class="flex h-9 w-9 items-center justify-center rounded-full bg-white/20 text-white transition active:bg-white/30">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
                </svg>
            </a>
            <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/20 text-white">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                </svg>
            </div>
            <span class="text-base font-semibold text-white">Riwayat Briefing</span>
        </div>
    </div>

    {{-- White card --}}
    <div class="flex-1 overflow-y-auto rounded-t-3xl bg-gray-50 pb-28 dark:bg-gray-950">

        {{-- Period tabs --}}
        <div class="flex gap-2 overflow-x-auto px-4 pb-3 pt-4 scrollbar-none">
            @foreach($tabs as $key => $label)
                <button wire:click="setPeriod('{{ $key }}')"
                        class="flex-shrink-0 rounded-full px-4 py-1.5 text-sm font-medium transition
                            {{ $period === $key
                                ? 'bg-blue-600 text-white shadow-sm'
                                : 'bg-white text-gray-500 ring-1 ring-blue-200 dark:bg-gray-800 dark:text-gray-400 dark:ring-blue-800' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        @forelse($records->groupBy(fn ($r) => $r->record_date->format('Y-m')) as $monthKey => $monthRecords)
            @php
                $firstDate  = $monthRecords->first()->record_date;
                $monthLabel = $monthNames[$firstDate->month - 1].' '.$firstDate->year;
            @endphp

            {{-- Month label --}}
            <div class="mb-2 mt-5 px-5 first:mt-2">
                <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500">
                    {{ $monthLabel }}
                </p>
            </div>

            <div class="flex flex-col gap-2 px-4">
                @foreach($monthRecords as $record)
                    @php
                        $items          = $record->items;
                        $completedCount = $items->where('is_completed', true)->count();
                        $totalCount     = $items->count();
                        $hasRejected    = $items->contains(fn ($i) => $i->review_status?->value === 'rejected');
                        $allApproved    = $items->isNotEmpty() && $items->every(fn ($i) => $i->review_status?->value === 'approved');
                        $hasPending     = $items->contains(fn ($i) => $i->review_status?->value === 'pending');

                        $overallBadge = match(true) {
                            $hasRejected => ['label' => 'Ada Ditolak',      'class' => 'bg-red-100 text-red-700'],
                            $allApproved => ['label' => 'Semua Disetujui',  'class' => 'bg-green-100 text-green-700'],
                            $hasPending  => ['label' => 'Menunggu Review',  'class' => 'bg-amber-100 text-amber-700'],
                            $items->isNotEmpty() => ['label' => 'Tersubmit','class' => 'bg-blue-100 text-blue-700'],
                            default      => ['label' => 'Kosong',           'class' => 'bg-gray-100 text-gray-500'],
                        };

                        $periodBadgeClass = $this->periodBadgeClass($record->period->value);
                        $isExpanded       = $expandedRecordId === $record->id;
                    @endphp

                    <div class="overflow-hidden rounded-2xl bg-white ring-1 ring-black/5 dark:bg-gray-900 dark:ring-white/10">

                        {{-- Card row --}}
                        <button wire:click="toggleRecord({{ $record->id }})"
                                class="flex w-full items-center gap-3 px-4 py-3.5 text-left transition active:bg-gray-50 dark:active:bg-gray-800">

                            {{-- Date badge --}}
                            <div class="flex w-11 flex-shrink-0 flex-col items-center rounded-xl bg-blue-50 py-2 dark:bg-blue-900/30">
                                <span class="text-[11px] font-semibold uppercase text-blue-400">
                                    {{ $dayNames[$record->record_date->dayOfWeek] }}
                                </span>
                                <span class="text-base font-bold leading-none text-blue-700 dark:text-blue-300">
                                    {{ $record->record_date->format('d') }}
                                </span>
                            </div>

                            {{-- Middle --}}
                            <div class="min-w-0 flex-1">
                                <span class="mb-1 inline-block rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $periodBadgeClass }}">
                                    {{ $this->periodLabel($record->period->value) }}
                                </span>
                                <div class="flex items-center gap-1.5">
                                    <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">
                                        {{ $completedCount }}/{{ $totalCount }} tugas
                                    </span>
                                    <span class="text-xs text-gray-300">·</span>
                                    <span class="text-xs text-gray-400">
                                        @if($record->submitted_at)
                                            {{ $record->submitted_at->locale('id')->diffForHumans() }}
                                        @else
                                            Belum submit
                                        @endif
                                    </span>
                                </div>
                            </div>

                            {{-- Overall status badge --}}
                            <span class="flex-shrink-0 rounded-full px-2 py-0.5 text-xs font-medium {{ $overallBadge['class'] }}">
                                {{ $overallBadge['label'] }}
                            </span>

                            {{-- Chevron --}}
                            <svg class="h-4 w-4 flex-shrink-0 text-gray-300 transition-transform {{ $isExpanded ? 'rotate-90' : '' }}"
                                 fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
                            </svg>
                        </button>

                        {{-- Expanded items --}}
                        @if($isExpanded)
                            @php
                                $regularItems  = $items->filter(fn ($i) => ($taskCache[$i->task_key]?->group ?? null) === null);
                                $groupedItems  = $items->filter(fn ($i) => ($taskCache[$i->task_key]?->group ?? null) !== null)
                                    ->groupBy(fn ($i) => $taskCache[$i->task_key]->group);
                            @endphp

                            <div class="border-t border-gray-100 dark:border-gray-800">

                                {{-- Regular items --}}
                                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                                    @foreach($regularItems as $item)
                                        @php
                                            $reviewClass = $this->reviewBadgeClass($item->review_status);
                                            $reviewLabel = $this->reviewBadgeLabel($item->review_status);
                                            $photoCount  = count($item->photo_paths ?? []);
                                        @endphp

                                        <div class="px-4 py-3">
                                            <div class="flex items-start justify-between gap-2">
                                                <div class="min-w-0 flex-1">
                                                    <p class="text-sm font-medium text-gray-800 dark:text-gray-200">
                                                        {{ $taskCache[$item->task_key]?->label ?? $item->task_key }}
                                                    </p>
                                                    @if($item->notes)
                                                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $item->notes }}</p>
                                                    @endif
                                                    @if($item->rejection_reason)
                                                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">
                                                            Alasan tolak: {{ $item->rejection_reason }}
                                                        </p>
                                                    @endif
                                                    <div class="mt-1.5 flex items-center gap-2">
                                                        <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $reviewClass }}">
                                                            {{ $reviewLabel }}
                                                        </span>
                                                        @if($photoCount > 0)
                                                            <span class="flex items-center gap-0.5 text-xs text-gray-400">
                                                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/>
                                                                </svg>
                                                                {{ $photoCount }} foto
                                                            </span>
                                                        @endif
                                                        @if($item->completed_at)
                                                            <span class="text-xs text-gray-400">{{ $item->completed_at->format('H:i') }}</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>

                                            @if($photoCount > 0)
                                                <div class="mt-2 flex gap-1.5 overflow-x-auto pb-1">
                                                    @foreach($item->photo_paths as $path)
                                                        <a href="{{ \Illuminate\Support\Facades\Storage::disk('b2')->temporaryUrl($path, now()->addHour()) }}"
                                                           target="_blank"
                                                           class="flex-shrink-0">
                                                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('b2')->temporaryUrl($path, now()->addHour()) }}"
                                                                 alt="Foto"
                                                                 class="h-16 w-16 rounded-lg object-cover">
                                                        </a>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>

                                {{-- Grouped task sections --}}
                                @foreach($groupedItems as $group => $groupItems)
                                    @php
                                        $groupLabel = $taskCache[$groupItems->first()->task_key]?->group_label ?? $group;
                                        $cDone  = $groupItems->where('is_completed', true)->count();
                                        $cTotal = $groupItems->count();
                                        $openVar = 'openGroup_'.$loop->index;
                                    @endphp

                                    <div x-data="{ {{ $openVar }}: false }"
                                         class="border-t border-gray-100 dark:border-gray-800">

                                        {{-- Parent row --}}
                                        <button type="button"
                                                @click="{{ $openVar }} = !{{ $openVar }}"
                                                class="flex w-full items-center gap-3 px-4 py-3 text-left transition active:bg-gray-50 dark:active:bg-gray-800">
                                            <div class="flex-shrink-0">
                                                @if($cDone === $cTotal)
                                                    <div class="flex h-6 w-6 items-center justify-center rounded-full bg-green-500">
                                                        <svg class="h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                                                        </svg>
                                                    </div>
                                                @elseif($cDone > 0)
                                                    <div class="flex h-6 w-6 items-center justify-center rounded-full bg-amber-400">
                                                        <svg class="h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                                                        </svg>
                                                    </div>
                                                @else
                                                    <div class="h-6 w-6 rounded-full border-2 border-gray-200 dark:border-gray-600"></div>
                                                @endif
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <p class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ $groupLabel }}</p>
                                                <span class="rounded bg-amber-100 px-1.5 py-0.5 text-xs text-amber-700">{{ $cDone }}/{{ $cTotal }} poin selesai</span>
                                            </div>
                                            <svg class="h-4 w-4 flex-shrink-0 text-gray-300 transition-transform duration-200"
                                                 :class="{{ $openVar }} ? 'rotate-90' : ''"
                                                 fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
                                            </svg>
                                        </button>

                                        {{-- Sub-items --}}
                                        <div x-show="{{ $openVar }}"
                                             x-transition:enter="transition ease-out duration-200"
                                             x-transition:enter-start="opacity-0"
                                             x-transition:enter-end="opacity-100"
                                             x-transition:leave="transition ease-in duration-150"
                                             x-transition:leave-start="opacity-100"
                                             x-transition:leave-end="opacity-0"
                                             style="display:none"
                                             class="divide-y divide-gray-50 border-t border-gray-100 bg-gray-50/50 dark:divide-gray-800/50 dark:border-gray-800 dark:bg-gray-900/50">
                                            @foreach($groupItems as $item)
                                                @php
                                                    $reviewClass = $this->reviewBadgeClass($item->review_status);
                                                    $reviewLabel = $this->reviewBadgeLabel($item->review_status);
                                                    $photoCount  = count($item->photo_paths ?? []);
                                                @endphp

                                                <div class="py-3 pl-10 pr-4">
                                                    <div class="flex items-start gap-2">
                                                        <div class="min-w-0 flex-1">
                                                            <p class="text-xs font-medium text-gray-700 dark:text-gray-300">
                                                                {{ $taskCache[$item->task_key]?->label ?? $item->task_key }}
                                                            </p>
                                                            @if($item->notes)
                                                                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $item->notes }}</p>
                                                            @endif
                                                            @if($item->rejection_reason)
                                                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">
                                                                    Alasan tolak: {{ $item->rejection_reason }}
                                                                </p>
                                                            @endif
                                                            <div class="mt-1 flex items-center gap-2">
                                                                <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $reviewClass }}">
                                                                    {{ $reviewLabel }}
                                                                </span>
                                                                @if($photoCount > 0)
                                                                    <span class="flex items-center gap-0.5 text-xs text-gray-400">
                                                                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/>
                                                                        </svg>
                                                                        {{ $photoCount }} foto
                                                                    </span>
                                                                @endif
                                                                @if($item->completed_at)
                                                                    <span class="text-xs text-gray-400">{{ $item->completed_at->format('H:i') }}</span>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    </div>

                                                    @if($photoCount > 0)
                                                        <div class="mt-2 flex gap-1.5 overflow-x-auto pb-1">
                                                            @foreach($item->photo_paths as $path)
                                                                <a href="{{ \Illuminate\Support\Facades\Storage::disk('b2')->temporaryUrl($path, now()->addHour()) }}"
                                                                   target="_blank"
                                                                   class="flex-shrink-0">
                                                                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('b2')->temporaryUrl($path, now()->addHour()) }}"
                                                                         alt="Foto"
                                                                         class="h-14 w-14 rounded-lg object-cover">
                                                                </a>
                                                            @endforeach
                                                        </div>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach

                            </div>
                        @endif

                    </div>
                @endforeach
            </div>

        @empty
            <div class="flex flex-col items-center justify-center py-16 text-center">
                <div class="mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100 dark:bg-gray-800">
                    <svg class="h-7 w-7 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                    </svg>
                </div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Belum ada riwayat</p>
                <p class="mt-0.5 text-xs text-gray-400">Submit briefing untuk melihat riwayat di sini</p>
            </div>
        @endforelse

        {{-- Pagination --}}
        @if($records->hasPages())
            <div class="flex items-center justify-center gap-3 py-4">
                @if($records->onFirstPage())
                    <span class="rounded-full bg-gray-100 px-4 py-2 text-sm text-gray-400 dark:bg-gray-800">Sebelumnya</span>
                @else
                    <button wire:click="previousPage"
                            class="rounded-full bg-white px-4 py-2 text-sm font-medium text-gray-600 ring-1 ring-black/10 transition active:bg-gray-50 dark:bg-gray-800 dark:text-gray-300">
                        Sebelumnya
                    </button>
                @endif

                <span class="text-xs text-gray-400">{{ $records->currentPage() }} / {{ $records->lastPage() }}</span>

                @if($records->hasMorePages())
                    <button wire:click="nextPage"
                            class="rounded-full bg-white px-4 py-2 text-sm font-medium text-gray-600 ring-1 ring-black/10 transition active:bg-gray-50 dark:bg-gray-800 dark:text-gray-300">
                        Berikutnya
                    </button>
                @else
                    <span class="rounded-full bg-gray-100 px-4 py-2 text-sm text-gray-400 dark:bg-gray-800">Berikutnya</span>
                @endif
            </div>
        @endif

    </div>

    <x-briefing.bottom-nav active="history" />
</div>
