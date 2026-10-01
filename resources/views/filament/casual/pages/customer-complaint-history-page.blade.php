@php
    $statusSteps = \App\Enums\CustomerComplaintStatus::cases();
@endphp

<div class="flex flex-col bg-blue-600 dark:bg-blue-900" style="min-height:100dvh">

    {{-- HEADER --}}
    <div class="flex-shrink-0 px-5 pb-8 pt-14">
        <div class="mb-4 flex items-center gap-3">
            <a href="{{ \App\Filament\Casual\Pages\LauncherPage::getUrl() }}"
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
            <span class="text-base font-semibold text-white">Riwayat Komplain</span>
        </div>
        <p class="text-blue-200">{{ auth()->user()->branch?->name ?? 'Tanpa Cabang' }}</p>
        <p class="text-xl font-semibold text-white">{{ now()->locale('id')->isoFormat('dddd, D MMMM Y') }}</p>
    </div>

    {{-- CONTENT --}}
    <div class="flex-1 overflow-y-auto rounded-t-3xl bg-gray-50 pb-28 pt-6 dark:bg-gray-950">

        @php $complaints = $this->complaints(); @endphp

        @if($complaints->isEmpty())
            <div class="flex flex-col items-center justify-center px-5 py-16 text-center">
                <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                    <svg class="h-8 w-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z"/>
                    </svg>
                </div>
                <p class="text-sm font-medium text-slate-600 dark:text-slate-400">Belum ada komplain</p>
                <p class="mt-1 text-xs text-slate-400">Komplain yang Anda kirim akan muncul di sini</p>
                <a href="{{ route('filament.casual.pages.customer-complaint-page') }}"
                   class="mt-4 rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white">
                    Buat Komplain
                </a>
            </div>
        @else
            <div class="flex flex-col gap-3 px-5">
                @foreach($complaints as $complaint)
                    @php
                        $statusColor = match ($complaint->status->getColor()) {
                            'success' => 'bg-emerald-100 text-emerald-700',
                            'warning' => 'bg-amber-100 text-amber-700',
                            'danger' => 'bg-red-100 text-red-700',
                            'info' => 'bg-sky-100 text-sky-700',
                            default => 'bg-gray-100 text-gray-700',
                        };
                        $isExpanded = $expandedId === $complaint->id;
                        $currentIndex = array_search($complaint->status, $statusSteps, true);
                    @endphp

                    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-700">

                        <button wire:click="toggleItem({{ $complaint->id }})" type="button"
                                class="flex w-full items-start gap-3 p-4 text-left">

                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-2">
                                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">
                                        {{ $complaint->complaint_number }}
                                    </p>
                                    <span class="flex-shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $statusColor }}">
                                        {{ $complaint->status->getLabel() }}
                                    </span>
                                </div>
                                <p class="mt-0.5 line-clamp-1 text-xs text-slate-500 dark:text-slate-400">
                                    {{ $complaint->branch?->name }} &middot; {{ $complaint->category->getLabel() }}
                                </p>
                                <p class="mt-0.5 text-xs text-slate-400">
                                    {{ $complaint->occurred_at->locale('id')->isoFormat('D MMM Y') }}
                                </p>
                            </div>

                            <svg class="mt-1 h-4 w-4 flex-shrink-0 text-gray-400 transition-transform {{ $isExpanded ? 'rotate-180' : '' }}"
                                 fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                            </svg>
                        </button>

                        @if($isExpanded)
                            <div class="border-t border-gray-100 dark:border-gray-800">

                                <div class="bg-white px-4 py-3 dark:bg-gray-900">
                                    <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Detail Komplain</p>
                                    <p class="mt-1 whitespace-pre-line text-xs text-slate-700 dark:text-slate-300">{{ $complaint->description }}</p>
                                </div>

                                @if($complaint->order_reference)
                                    <div class="border-t border-gray-100 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-900">
                                        <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">No. Pesanan / Struk</p>
                                        <p class="mt-1 text-xs text-slate-700 dark:text-slate-300">{{ $complaint->order_reference }}</p>
                                    </div>
                                @endif

                                @if(filled($complaint->attachment_paths))
                                    <div class="border-t border-gray-100 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-900">
                                        <p class="mb-2 text-[10px] font-semibold uppercase tracking-wider text-slate-400">Lampiran</p>
                                        <div class="flex flex-wrap gap-2">
                                            @foreach($complaint->attachment_paths as $path)
                                                <a href="{{ route('helpdesk.customer-complaints.attachments.show', ['path' => $path]) }}"
                                                   target="_blank"
                                                   class="rounded-lg border border-gray-200 px-2.5 py-1.5 text-[11px] text-slate-600 dark:border-gray-700 dark:text-slate-300">
                                                    {{ basename($path) }}
                                                </a>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                @if($complaint->resolution)
                                    <div class="border-t border-gray-100 bg-emerald-50 px-4 py-3 dark:border-gray-800 dark:bg-emerald-950/30">
                                        <p class="text-[10px] font-semibold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">Resolution</p>
                                        <p class="mt-1 text-xs text-emerald-700 dark:text-emerald-400">{{ $complaint->resolution }}</p>
                                    </div>
                                @endif

                                <div class="border-t border-gray-100 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-900">
                                    <p class="mb-2 text-[10px] font-semibold uppercase tracking-wider text-slate-400">Progress</p>
                                    <div class="flex items-center gap-0">
                                        @foreach($statusSteps as $i => $step)
                                            @php
                                                $isDone = $currentIndex !== false && $i <= $currentIndex;
                                                $isCurrent = $i === $currentIndex;
                                            @endphp
                                            <div class="flex flex-1 flex-col items-center">
                                                <div class="h-2 w-2 rounded-full {{ $isDone ? 'bg-blue-600' : 'bg-gray-200' }} {{ $isCurrent ? 'ring-2 ring-blue-200 ring-offset-1' : '' }}"></div>
                                                <p class="mt-1 text-center text-[9px] leading-tight {{ $isDone ? 'font-semibold text-blue-700' : 'text-slate-400' }}">{{ $step->getLabel() }}</p>
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

    <x-customer-complaint.bottom-nav active="history" />

</div>
