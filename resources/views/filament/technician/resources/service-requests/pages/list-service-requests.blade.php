@php $user = auth()->user(); @endphp

<div class="flex flex-col bg-orange-500" style="min-height:100dvh">

    {{-- Header --}}
    <div class="flex-shrink-0 px-5 pb-8 pt-14">

        {{-- Top row --}}
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-1.5">
                <svg class="h-4 w-4 flex-shrink-0 text-orange-200" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M14.5 10a4.5 4.5 0 0 0 4.284-5.882c-.105-.324-.51-.391-.752-.15L15.34 6.66a.454.454 0 0 1-.493.11 3.01 3.01 0 0 1-1.618-1.616.454.454 0 0 1 .11-.494l2.694-2.692c.24-.241.174-.647-.15-.752a4.5 4.5 0 0 0-5.873 4.575c.055.873-.128 1.808-.8 2.368l-7.23 6.024a2.724 2.724 0 1 0 3.837 3.837l6.024-7.23c.56-.672 1.495-.855 2.368-.8.096.007.193.01.291.01ZM5 16a1 1 0 1 1-2 0 1 1 0 0 1 2 0Z" clip-rule="evenodd"/>
                    <path d="M14.5 11.5c.173 0 .345-.007.514-.022l3.754 3.754a2.5 2.5 0 0 1-3.536 3.536l-4.41-4.41 2.172-2.607c.052-.063.147-.138.342-.196.195-.058.363-.055.524-.018.077.018.154.04.232.06a4.5 4.5 0 0 0 .408.103Z"/>
                </svg>
                <span class="text-sm font-medium text-orange-100">Layanan Teknisi</span>
            </div>
            <a href="{{ route('filament.casual.pages.launcher-page') }}"
               class="flex h-9 w-9 items-center justify-center rounded-full bg-white/15 text-white transition active:bg-white/25">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"/>
                </svg>
            </a>
        </div>

        {{-- Greeting --}}
        <div class="mt-5">
            <p class="text-sm font-medium text-orange-200">{{ now()->locale('id')->isoFormat('dddd, D MMMM YYYY') }}</p>
            <h1 class="mt-0.5 text-xl font-semibold text-white">Selamat Datang, {{ $user->name }}</h1>
        </div>
    </div>

    {{-- Content card --}}
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
