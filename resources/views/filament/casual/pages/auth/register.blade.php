<div class="flex min-h-screen flex-col bg-blue-600 dark:bg-blue-800">

    {{-- ── TOP SECTION — greeting ── --}}
    <div class="flex flex-col items-center justify-center px-8 pb-10 pt-16 text-center">
        @if(filament()->hasLogin())
            <div class="mb-6 self-start">
                <a href="{{ filament()->getLoginUrl() }}"
                   class="flex h-9 w-9 items-center justify-center rounded-full bg-white/20 text-white backdrop-blur-sm transition hover:bg-white/30">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
                    </svg>
                </a>
            </div>
        @endif
        <h1 class="text-4xl font-bold text-white">Halo!</h1>
        <p class="mt-3 text-lg font-medium text-blue-100">Bergabung Bersama Kami</p>
    </div>

    {{-- ── BOTTOM SECTION — form card ── --}}
    <div class="rounded-t-3xl bg-white px-6 pb-10 pt-8 shadow-2xl dark:bg-gray-900">

        <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Buat Akun</h2>
        <p class="mt-1.5 text-sm text-gray-400">Daftar sebagai casual staff Help Bloomery</p>

        {{-- Token info --}}
        <div class="my-5 flex items-start gap-2.5 rounded-xl bg-blue-50 px-4 py-3 dark:bg-blue-900/20">
            <svg class="mt-0.5 h-4 w-4 flex-shrink-0 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z"/>
            </svg>
            <p class="text-sm text-blue-700 dark:text-blue-300">
                Token registrasi diberikan oleh <strong>HR Admin</strong>. Hubungi admin jika belum memiliki token.
            </p>
        </div>

        <form wire:submit="register" class="flex flex-col gap-4">
            {{ $this->form }}

            <button type="submit"
                    wire:loading.attr="disabled"
                    class="mt-1 flex w-full items-center justify-center gap-2 rounded-2xl bg-blue-600 py-4 text-base font-bold text-white shadow-lg shadow-blue-600/30 transition active:scale-[.98] disabled:opacity-60">
                <svg wire:loading wire:target="register"
                     class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                Daftar Sekarang
            </button>
        </form>

        @if(filament()->hasLogin())
            <div class="mt-6 text-center">
                <a href="{{ filament()->getLoginUrl() }}"
                   class="text-sm font-semibold text-blue-600 dark:text-blue-400">
                    Sudah punya akun? Masuk
                </a>
            </div>
        @endif

    </div>

</div>
