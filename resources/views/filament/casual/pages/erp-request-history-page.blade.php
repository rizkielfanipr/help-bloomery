@php
    $statusColors = [
        'draft'       => 'bg-gray-100 text-gray-600',
        'submitted'   => 'bg-blue-100 text-blue-700',
        'in_review'   => 'bg-amber-100 text-amber-700',
        'approved'    => 'bg-green-100 text-green-700',
        'in_progress' => 'bg-blue-100 text-blue-700',
        'completed'   => 'bg-green-100 text-green-700',
        'rejected'    => 'bg-red-100 text-red-700',
    ];
@endphp

<div class="flex flex-col bg-blue-600 dark:bg-blue-900" style="min-height:100dvh">

    {{-- HEADER --}}
    <div class="flex-shrink-0 px-5 pb-8 pt-14">
        <div class="mb-4 flex items-center gap-3">
            <a href="{{ route('filament.casual.pages.launcher-page') }}"
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
            <span class="text-base font-semibold text-white">Riwayat Request ERP</span>
        </div>
        <p class="text-blue-200">{{ auth()->user()->branch?->name ?? 'Tanpa Cabang' }}</p>
        <p class="text-xl font-semibold text-white">{{ now()->locale('id')->isoFormat('dddd, D MMMM Y') }}</p>
    </div>

    {{-- CONTENT --}}
    <div class="flex-1 overflow-y-auto rounded-t-3xl bg-gray-50 pb-28 pt-6 dark:bg-gray-950">

        @php $requests = $this->requests(); @endphp

        @if($requests->isEmpty())
            <div class="flex flex-col items-center justify-center px-5 py-16 text-center">
                <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                    <svg class="h-8 w-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 14.25h13.5m-13.5 0a3 3 0 0 1-3-3m3 3a3 3 0 1 0 0 6h13.5a3 3 0 1 0 0-6m-16.5-3a3 3 0 0 1 3-3h13.5a3 3 0 0 1 3 3m-19.5 0a4.5 4.5 0 0 1 .9-2.7L5.737 5.1a3.375 3.375 0 0 1 2.7-1.35h7.126c1.062 0 2.062.5 2.7 1.35l2.587 3.45a4.5 4.5 0 0 1 .9 2.7m0 0a3 3 0 0 1-3 3m0 3h.008v.008h-.008v-.008Zm0-6h.008v.008h-.008v-.008Zm-3 6h.008v.008h-.008v-.008Zm0-6h.008v.008h-.008v-.008Z"/>
                    </svg>
                </div>
                <p class="text-sm font-medium text-slate-600 dark:text-slate-400">Belum ada permintaan ERP</p>
                <p class="mt-1 text-xs text-slate-400">Permintaan ERP Anda akan muncul di sini</p>
                <a href="{{ route('filament.casual.pages.erp-request-page') }}"
                   class="mt-4 rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white">
                    Buat Permintaan
                </a>
            </div>
        @else
            <div class="flex flex-col gap-3 px-5">
                @foreach($requests as $request)
                    @php
                        $statusValue = $request->status instanceof \App\Enums\RequestStatus
                            ? $request->status->value : $request->status;
                        $statusLabel = $request->status instanceof \App\Enums\RequestStatus
                            ? $request->status->getLabel() : $statusValue;
                        $statusColor = $statusColors[$statusValue] ?? 'bg-gray-100 text-gray-600';
                        $isExpanded  = $expandedId === $request->id;
                    @endphp

                    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-700">

                        <button wire:click="toggleItem({{ $request->id }})" type="button"
                                class="flex w-full items-start gap-3 p-4 text-left">

                            <div class="mt-0.5 flex-shrink-0">
                                @if($statusValue === 'completed')
                                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-green-100">
                                        <svg class="h-4 w-4 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                                        </svg>
                                    </div>
                                @elseif($statusValue === 'rejected')
                                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-red-100">
                                        <svg class="h-4 w-4 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                                        </svg>
                                    </div>
                                @elseif($statusValue === 'in_progress')
                                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-100">
                                        <svg class="h-4 w-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l5.654-4.654m5.657-4.656-.005-.005a1.023 1.023 0 0 0-.361-.214l-3.074-.925a1.023 1.023 0 0 1-.36-.214L9.25 7.5m6.174 1.667L9.25 7.5"/>
                                        </svg>
                                    </div>
                                @elseif($statusValue === 'in_review')
                                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-amber-100">
                                        <svg class="h-4 w-4 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                                        </svg>
                                    </div>
                                @else
                                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-100">
                                        <svg class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z"/>
                                        </svg>
                                    </div>
                                @endif
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-2">
                                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">
                                        {{ $request->module?->name ?? 'Modul ERP' }}
                                    </p>
                                    <span class="flex-shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $statusColor }}">
                                        {{ $statusLabel }}
                                    </span>
                                </div>
                                <p class="mt-0.5 line-clamp-1 text-xs text-slate-500 dark:text-slate-400">
                                    {{ $request->keterangan }}
                                </p>
                                <p class="mt-0.5 text-xs text-slate-400">
                                    {{ $request->created_at->locale('id')->isoFormat('D MMM Y, HH:mm') }}
                                </p>
                            </div>

                            <svg class="h-4 w-4 flex-shrink-0 text-gray-400 transition-transform {{ $isExpanded ? 'rotate-180' : '' }}"
                                 fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                            </svg>
                        </button>

                        @if($isExpanded)
                            <div class="border-t border-gray-100 dark:border-gray-800">

                                <div class="bg-white px-4 py-3 dark:bg-gray-900">
                                    <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Keterangan</p>
                                    <p class="mt-1 text-xs text-slate-700 dark:text-slate-300">{{ $request->keterangan }}</p>
                                </div>

                                @if($request->attachments && count($request->attachments) > 0)
                                    <div class="border-t border-gray-100 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-900">
                                        <p class="mb-2 text-[10px] font-semibold uppercase tracking-wider text-slate-400">Lampiran</p>
                                        <div class="grid grid-cols-3 gap-2">
                                            @foreach($request->attachments as $path)
                                                <a href="{{ \Illuminate\Support\Facades\Storage::disk('b2')->temporaryUrl($path, now()->addHour()) }}"
                                                   target="_blank"
                                                   class="aspect-square overflow-hidden rounded-lg ring-1 ring-gray-200">
                                                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('b2')->temporaryUrl($path, now()->addHour()) }}"
                                                         class="h-full w-full object-cover">
                                                </a>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                <div class="border-t border-gray-100 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-900">
                                    <p class="mb-2 text-[10px] font-semibold uppercase tracking-wider text-slate-400">Progress</p>
                                    @php
                                        $steps = [
                                            ['value' => 'submitted',   'label' => 'Diajukan'],
                                            ['value' => 'in_review',   'label' => 'Ditinjau'],
                                            ['value' => 'in_progress', 'label' => 'Dikerjakan'],
                                            ['value' => 'completed',   'label' => 'Selesai'],
                                        ];
                                        $stepValues   = array_column($steps, 'value');
                                        $currentIndex = array_search($statusValue, $stepValues);
                                        if ($statusValue === 'rejected') { $currentIndex = 0; }
                                    @endphp
                                    <div class="flex items-center gap-0">
                                        @foreach($steps as $i => $step)
                                            @php
                                                $isDone    = $currentIndex !== false && $i <= $currentIndex;
                                                $isCurrent = $i === $currentIndex;
                                            @endphp
                                            <div class="flex flex-1 flex-col items-center">
                                                <div class="h-2 w-2 rounded-full {{ $isDone ? 'bg-blue-600' : 'bg-gray-200' }} {{ $isCurrent ? 'ring-2 ring-blue-200 ring-offset-1' : '' }}"></div>
                                                <p class="mt-1 text-center text-[9px] leading-tight {{ $isDone ? 'font-semibold text-blue-700' : 'text-slate-400' }}">{{ $step['label'] }}</p>
                                            </div>
                                            @if(!$loop->last)
                                                <div class="mb-3 h-0.5 flex-1 {{ $currentIndex !== false && $i < $currentIndex ? 'bg-blue-600' : 'bg-gray-200' }}"></div>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>

                            </div>
                        @endif

                    </div>
                @endforeach
            </div>
        @endif

    </div>

    <x-erp-request.bottom-nav active="history" />

</div>
