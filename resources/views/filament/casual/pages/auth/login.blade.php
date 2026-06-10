<div class="flex min-h-screen flex-col bg-blue-600 dark:bg-blue-800">

    {{-- ── TOP SECTION — greeting ── --}}
    <div class="flex flex-1 flex-col items-center justify-center px-8 pb-10 pt-20 text-center">
        <h1 class="text-5xl font-bold text-white">Halo!</h1>
        <p class="mt-3 text-lg font-medium text-blue-100">Selamat Datang Kembali!</p>
    </div>

    {{-- ── BOTTOM SECTION — form card ── --}}
    <div class="rounded-t-3xl bg-white px-6 pb-10 pt-8 shadow-2xl dark:bg-gray-900">

        <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Masuk Akun</h2>
        <p class="mb-6 mt-1.5 text-sm text-gray-400">Masukkan akun Anda untuk melanjutkan</p>

        <form wire:submit="authenticate" class="flex flex-col gap-4">
            {{ $this->form }}

            {{-- Forgot password --}}
            @if(filament()->hasPasswordReset())
                <div class="-mt-1 flex justify-end">
                    <a href="{{ filament()->getRequestPasswordResetUrl() }}"
                       class="text-sm font-medium text-blue-600 dark:text-blue-400">
                        Lupa kata sandi?
                    </a>
                </div>
            @endif

            {{-- Submit button --}}
            <button type="submit"
                    wire:loading.attr="disabled"
                    class="mt-1 flex w-full items-center justify-center gap-2 rounded-2xl bg-blue-600 py-4 text-base font-bold text-white shadow-lg shadow-blue-600/30 transition active:scale-[.98] disabled:opacity-60">
                <svg wire:loading wire:target="authenticate"
                     class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                Masuk Akun
            </button>
        </form>

        {{-- Register link --}}
        @if(filament()->hasRegistration())
            <div class="mt-6 text-center">
                <a href="{{ filament()->getRegistrationUrl() }}"
                   class="text-sm font-semibold text-blue-600 dark:text-blue-400">
                    Buat Akun Baru
                </a>
            </div>
        @endif

    </div>

</div>
