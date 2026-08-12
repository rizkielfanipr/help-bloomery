@php
    $card = $this->getDailyStatus();
    $status = $card?->status;
    $isDone = $status !== null && $status !== \App\Enums\StockCardStatus::Draft;
    $date = $reportDate ?: now()->toDateString();
@endphp

<div class="flex flex-col bg-blue-600 dark:bg-blue-900" style="min-height:100dvh">

    {{-- HEADER --}}
    <div class="flex-shrink-0 px-5 pb-8 pt-14">
        <div class="mb-4 flex items-center gap-3">
            <a href="{{ route('filament.casual.pages.launcher-page') }}"
               wire:navigate
               class="flex h-9 w-9 items-center justify-center rounded-full bg-white/20 text-white transition active:bg-white/30">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
                </svg>
            </a>
            <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/20 text-white">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 2.625c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125m16.5 5.625c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125"/>
                </svg>
            </div>
            <span class="text-base font-semibold text-white">Stock Card</span>
        </div>

        <p class="text-blue-200">{{ auth()->user()->branch?->name ?? 'Tanpa Cabang' }}</p>
        <p class="text-xl font-semibold text-white">{{ now()->locale('id')->isoFormat('dddd, D MMMM Y') }}</p>
    </div>

    {{-- WHITE CONTENT CARD --}}
    <div class="flex-1 overflow-y-auto rounded-t-3xl bg-gray-50 pb-28 pt-6 dark:bg-gray-950">
        <div class="flex flex-col gap-4 px-5">

            {{-- Tanggal --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
                <label class="mb-1.5 block text-xs font-semibold text-slate-600 dark:text-slate-400">Tanggal</label>
                <input type="date" wire:model.live="reportDate"
                       class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm text-slate-700 focus:border-blue-400 focus:outline-none focus:ring-0 dark:border-gray-700 dark:bg-gray-800 dark:text-slate-200">
            </div>

            {{-- Laporan Harian button --}}
            <a href="{{ route('filament.casual.pages.stock-card-entry-page') }}?date={{ $date }}"
               wire:navigate
               class="relative flex items-center gap-4 rounded-2xl border bg-white p-5 transition active:scale-95 dark:bg-gray-900
                      {{ $isDone ? 'border-green-200 dark:border-green-800' : 'border-gray-200 dark:border-gray-700' }}">

                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl
                            {{ $isDone ? 'bg-green-100 dark:bg-green-900/40' : 'bg-blue-100 dark:bg-blue-900/40' }}">
                    @if($isDone)
                        <svg class="h-6 w-6 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                        </svg>
                    @else
                        <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 2.625c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125m16.5 5.625c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125"/>
                        </svg>
                    @endif
                </div>

                <div class="flex-1">
                    <p class="font-semibold {{ $isDone ? 'text-green-700 dark:text-green-400' : 'text-slate-700 dark:text-slate-200' }}">
                        Stock Card Harian
                    </p>
                    <p class="text-xs {{ $isDone ? 'text-green-600 dark:text-green-500' : 'text-slate-400' }}">
                        @if($isDone)
                            {{ $status->getLabel() }}
                        @else
                            Belum diisi
                        @endif
                    </p>
                </div>

                <svg class="h-5 w-5 {{ $isDone ? 'text-green-400' : 'text-gray-300' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
                </svg>
            </a>

        </div>
    </div>

    <x-stock-card.bottom-nav active="card" />

</div>
