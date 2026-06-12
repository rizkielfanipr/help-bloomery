<div class="flex flex-col bg-blue-600 dark:bg-blue-900" style="min-height:100dvh">

    {{-- ── Brand ── --}}
    <div class="flex flex-1 flex-col items-center justify-center gap-3 px-8 pb-10 pt-16">
        <div class="flex h-20 w-20 items-center justify-center rounded-full bg-white">
            <img src="/images/bloomery-icon.png" alt="Bloomery" class="h-12 w-12 rounded-xl object-cover">
        </div>
        <div class="mt-1 text-center">
            <p class="text-base font-semibold text-white">Casual Staff</p>
            <p class="text-xs font-medium text-white/50">Attendance System</p>
        </div>
    </div>

    {{-- ── Form card ── --}}
    <div class="rounded-t-3xl bg-white px-6 pb-12 pt-7 dark:bg-gray-900">

        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Masuk</h2>

        <form wire:submit="authenticate" class="mt-5 flex flex-col gap-4">
            {{ $this->form }}

            @if(filament()->hasPasswordReset())
                <div class="-mt-1 flex justify-end">
                    <a href="{{ filament()->getRequestPasswordResetUrl() }}"
                       class="text-xs text-gray-400 dark:text-gray-500">
                        Lupa kata sandi?
                    </a>
                </div>
            @endif

            <button type="submit"
                    wire:loading.attr="disabled"
                    class="mt-1 flex w-full items-center justify-center gap-2 rounded-2xl bg-blue-600 py-4 text-sm font-semibold text-white transition active:scale-[.98] disabled:opacity-60">
                <svg wire:loading wire:target="authenticate"
                     class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                Masuk
            </button>
        </form>

        @if(filament()->hasRegistration())
            <p class="mt-6 text-center text-sm text-gray-400 dark:text-gray-500">
                Belum punya akun?
                <a href="{{ filament()->getRegistrationUrl() }}"
                   class="font-medium text-blue-600 dark:text-blue-400">
                    Daftar
                </a>
            </p>
        @endif

    </div>

</div>
