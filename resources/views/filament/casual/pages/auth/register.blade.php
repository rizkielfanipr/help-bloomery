<div class="flex flex-col bg-blue-600 dark:bg-blue-900" style="min-height:100dvh">

    {{-- ── Brand ── --}}
    <div class="flex flex-1 flex-col items-center justify-center gap-3 px-8 pb-10 pt-16">
        @if(filament()->hasLogin())
            <div class="mb-1 self-start">
                <a href="{{ filament()->getLoginUrl() }}"
                   class="flex h-9 w-9 items-center justify-center rounded-full bg-white/20 text-white backdrop-blur-sm transition active:bg-white/30">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
                    </svg>
                </a>
            </div>
        @endif
        <div class="flex h-20 w-20 items-center justify-center rounded-full bg-white">
            <img src="/images/bloomery-icon.png" alt="Bloomery" class="h-12 w-12 rounded-xl object-cover">
        </div>
        <div class="mt-1 text-center">
            <p class="text-base font-semibold text-white">Bloomery</p>
            <p class="text-xs font-medium text-white/50">Super App</p>
        </div>
    </div>

    {{-- ── Form card ── --}}
    <div class="rounded-t-3xl bg-white px-6 pb-12 pt-7 dark:bg-gray-900">

        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Daftar</h2>

        <form wire:submit="register" class="mt-4 flex flex-col gap-4">
            {{ $this->form }}

            <button type="submit"
                    wire:loading.attr="disabled"
                    class="mt-1 flex w-full items-center justify-center gap-2 rounded-2xl bg-blue-600 py-4 text-sm font-semibold text-white transition active:scale-[.98] disabled:opacity-60">
                <svg wire:loading wire:target="register"
                     class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                Daftar
            </button>
        </form>

        @if(filament()->hasLogin())
            <p class="mt-6 text-center text-sm text-gray-400 dark:text-gray-500">
                Sudah punya akun?
                <a href="{{ filament()->getLoginUrl() }}"
                   class="font-medium text-blue-600 dark:text-blue-400">
                    Masuk
                </a>
            </p>
        @endif

    </div>

</div>
