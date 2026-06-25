@php $user = auth()->user(); @endphp

<div class="flex flex-col bg-orange-500" style="min-height:100dvh">

    {{-- ════════════════════════════════════════════
         HEADER
    ════════════════════════════════════════════ --}}
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
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437 1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008Z"/>
                </svg>
            </div>
            <span class="text-base font-semibold text-white">Logbook Teknisi</span>
        </div>

        <p class="text-orange-200">{{ $user->name }}</p>
        <p class="text-xl font-semibold text-white">{{ now()->locale('id')->isoFormat('dddd, D MMMM Y') }}</p>
    </div>

    {{-- ════════════════════════════════════════════
         WHITE CONTENT CARD
    ════════════════════════════════════════════ --}}
    <div class="flex-1 overflow-y-auto rounded-t-3xl bg-gray-50 pb-28 pt-6 dark:bg-gray-950">

        @php $jobs = $this->serviceRequests; @endphp

        <div class="mx-5 mb-3 flex items-center justify-between">
            <p class="font-semibold text-gray-900 dark:text-white">Pekerjaan Saya</p>
            @if($jobs->isNotEmpty())
                <div class="flex items-center gap-1.5 rounded-lg bg-gray-200/70 px-3 py-1.5 text-xs font-semibold text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                    {{ $jobs->count() }} pekerjaan
                </div>
            @endif
        </div>

        @forelse($jobs as $job)
            @php
                $statusConfig = match($job->status->value) {
                    'submitted'   => ['label' => 'Menunggu',   'bg' => 'bg-amber-100',   'text' => 'text-amber-700',   'dark' => 'dark:bg-amber-900/30 dark:text-amber-400'],
                    'in_progress' => ['label' => 'Dikerjakan', 'bg' => 'bg-blue-100',    'text' => 'text-blue-700',    'dark' => 'dark:bg-blue-900/30 dark:text-blue-400'],
                    'warranty'    => ['label' => 'Garansi',    'bg' => 'bg-purple-100',  'text' => 'text-purple-700',  'dark' => 'dark:bg-purple-900/30 dark:text-purple-400'],
                    'completed'   => ['label' => 'Selesai',    'bg' => 'bg-emerald-100', 'text' => 'text-emerald-700', 'dark' => 'dark:bg-emerald-900/30 dark:text-emerald-400'],
                    default       => ['label' => $job->status->getLabel(), 'bg' => 'bg-gray-100', 'text' => 'text-gray-600', 'dark' => 'dark:bg-gray-800 dark:text-gray-400'],
                };
            @endphp

            <a href="{{ \App\Filament\Technician\Resources\ServiceRequests\ServiceRequestResource::getUrl('view', ['record' => $job]) }}"
               class="mx-5 mb-3 block overflow-hidden rounded-2xl bg-white ring-1 ring-black/5 transition active:scale-[0.98] dark:bg-gray-900 dark:ring-white/10">
                <div class="flex items-center gap-3 px-5 py-4">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-orange-50 dark:bg-orange-900/20">
                        <svg class="h-5 w-5 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437 1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008Z"/>
                        </svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-semibold text-gray-900 dark:text-white">SR-{{ str_pad($job->id, 4, '0', STR_PAD_LEFT) }}</p>
                        <p class="mt-0.5 text-xs text-gray-500">
                            {{ $job->scheduled_date?->format('d M Y') ?? '-' }}
                            @if($job->technician) · {{ $job->technician->name }}@endif
                        </p>
                    </div>
                    <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusConfig['bg'] }} {{ $statusConfig['text'] }} {{ $statusConfig['dark'] }}">
                        {{ $statusConfig['label'] }}
                    </span>
                </div>
            </a>
        @empty
            <div class="mx-5 overflow-hidden rounded-2xl bg-white ring-1 ring-black/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex flex-col items-center gap-4 px-5 py-12 text-center">
                    <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-orange-50 dark:bg-orange-900/20">
                        <svg class="h-8 w-8 text-orange-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437 1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008Z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-900 dark:text-white">Tidak Ada Pekerjaan</p>
                        <p class="mt-1 text-sm text-gray-400">Belum ada pekerjaan yang ditugaskan saat ini.</p>
                    </div>
                </div>
            </div>
        @endforelse

    </div>

    <x-technician.bottom-nav active="jobs" />

</div>
