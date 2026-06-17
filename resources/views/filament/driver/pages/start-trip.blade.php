<div class="flex flex-col bg-emerald-600" style="min-height:100dvh">

    {{-- Header --}}
    <div class="relative overflow-hidden px-5 pb-8 pt-14">
        <div class="pointer-events-none absolute -right-8 -top-8 h-36 w-36 rounded-full bg-white/10"></div>

        <div class="flex items-center gap-4">
            <a href="{{ \App\Filament\Driver\Pages\TripDashboard::getUrl() }}"
               class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white/15 text-white transition active:bg-white/25">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
                </svg>
            </a>
            <div>
                <h1 class="text-xl font-bold text-white">Mulai Perjalanan</h1>
                <p class="text-xs text-emerald-200">Isi detail perjalanan baru</p>
            </div>
        </div>
    </div>

    {{-- Content card --}}
    <div class="-mt-5 flex-1 overflow-y-auto rounded-t-3xl bg-gray-50 px-4 pb-10 pt-6 dark:bg-gray-950">
        <form wire:submit="startTrip">
            {{ $this->form }}

            <div class="mt-6 flex gap-3">
                <a href="{{ \App\Filament\Driver\Pages\TripDashboard::getUrl() }}"
                   class="flex flex-1 items-center justify-center gap-2 rounded-2xl border-2 border-gray-200 py-4 text-sm font-semibold text-gray-500 transition active:bg-gray-100 dark:border-gray-700 dark:text-gray-400">
                    Batal
                </a>
                <button type="submit"
                        wire:loading.attr="disabled"
                        class="flex flex-1 items-center justify-center gap-2 rounded-2xl bg-emerald-600 py-4 text-sm font-semibold text-white transition active:scale-95 active:bg-emerald-700 disabled:opacity-50">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z"/>
                    </svg>
                    <span wire:loading.remove>Mulai Perjalanan</span>
                    <span wire:loading>Memproses...</span>
                </button>
            </div>
        </form>
    </div>

</div>
