@php $user = auth()->user(); @endphp

<div class="flex flex-col bg-blue-600" style="min-height:100dvh">

    {{-- ════════════════════════════════════════════
         HEADER
    ════════════════════════════════════════════ --}}
    <div class="flex-shrink-0 px-5 pb-8 pt-14">
        <div class="mb-4 flex items-center gap-3">
            <a href="{{ \App\Filament\Casual\Resources\ServiceRequests\ServiceRequestResource::getUrl('index') }}"
               class="flex h-9 w-9 items-center justify-center rounded-full bg-white/20 text-white transition active:bg-white/30">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
                </svg>
            </a>
            <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/20 text-white">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/>
                </svg>
            </div>
            <span class="text-base font-semibold text-white">Riwayat Pekerjaan</span>
        </div>

        <p class="text-blue-200">{{ $user->name }}</p>
        <p class="text-xl font-semibold text-white">{{ now()->locale('id')->isoFormat('dddd, D MMMM Y') }}</p>
    </div>

    {{-- ════════════════════════════════════════════
         WHITE CONTENT CARD
    ════════════════════════════════════════════ --}}
    <div class="flex-1 overflow-y-auto rounded-t-3xl bg-gray-50 pb-28 pt-6 dark:bg-gray-950">

        @php $jobs = $this->completedJobs; @endphp

        <div class="mx-5 mb-3 flex items-center justify-between">
            <p class="font-semibold text-gray-900 dark:text-white">Selesai Dikerjakan</p>
            @if($jobs->isNotEmpty())
                <div class="flex items-center gap-1.5 rounded-lg bg-gray-200/70 px-3 py-1.5 text-xs font-semibold text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                    {{ $jobs->count() }} pekerjaan
                </div>
            @endif
        </div>

        @forelse($jobs as $job)
            @php
                $statusConfig = match($job->status->value) {
                    'warranty'  => ['label' => 'Warranty',  'bg' => 'bg-purple-100',  'text' => 'text-purple-700',  'dark' => 'dark:bg-purple-900/30 dark:text-purple-400'],
                    'completed' => ['label' => 'Completed', 'bg' => 'bg-emerald-100', 'text' => 'text-emerald-700', 'dark' => 'dark:bg-emerald-900/30 dark:text-emerald-400'],
                    default     => ['label' => $job->status->getLabel(), 'bg' => 'bg-gray-100', 'text' => 'text-gray-600', 'dark' => 'dark:bg-gray-800 dark:text-gray-400'],
                };
                $lastRepair = $job->repairs->sortByDesc('completed_at')->first();
            @endphp

            <a href="{{ \App\Filament\Casual\Resources\ServiceRequests\ServiceRequestResource::getUrl('view', ['record' => $job]) }}"
               class="mx-5 mb-3 block overflow-hidden rounded-2xl bg-white ring-1 ring-black/5 transition active:scale-[0.98] dark:bg-gray-900 dark:ring-white/10">
                <div class="flex items-center gap-3 px-5 py-4">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl
                        {{ $job->status->value === 'warranty' ? 'bg-purple-50 dark:bg-purple-900/20' : 'bg-emerald-50 dark:bg-emerald-900/20' }}">
                        @if($job->status->value === 'warranty')
                            <svg class="h-5 w-5 text-purple-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z"/>
                            </svg>
                        @else
                            <svg class="h-5 w-5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                            </svg>
                        @endif
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-semibold text-gray-900 dark:text-white">SR-{{ str_pad($job->id, 4, '0', STR_PAD_LEFT) }}</p>
                        <p class="mt-0.5 text-xs text-gray-500">
                            {{ $job->scheduled_date?->format('d M Y') ?? '-' }}
                            @if($lastRepair?->completed_at)
                                · Selesai {{ $lastRepair->completed_at->format('d M Y') }}
                            @endif
                        </p>
                        @if($job->status->value === 'warranty' && $job->warranty_expires_at)
                            <p class="mt-0.5 text-xs font-medium text-purple-600 dark:text-purple-400">
                                Garansi hingga {{ $job->warranty_expires_at->format('d M Y') }}
                            </p>
                        @endif
                    </div>
                    <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusConfig['bg'] }} {{ $statusConfig['text'] }} {{ $statusConfig['dark'] }}">
                        {{ $statusConfig['label'] }}
                    </span>
                </div>
            </a>
        @empty
            <div class="mx-5 overflow-hidden rounded-2xl bg-white ring-1 ring-black/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex flex-col items-center gap-4 px-5 py-12 text-center">
                    <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-blue-50 dark:bg-orange-900/20">
                        <svg class="h-8 w-8 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-900 dark:text-white">Belum Ada Riwayat</p>
                        <p class="mt-1 text-sm text-gray-400">Pekerjaan yang selesai akan muncul di sini.</p>
                    </div>
                </div>
            </div>
        @endforelse

    </div>

    <x-technician.bottom-nav active="history" />

</div>
