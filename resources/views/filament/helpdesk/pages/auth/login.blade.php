<div class="flex min-h-screen bg-slate-50 dark:bg-[#020617]">

    {{-- ── Left panel: branding ── --}}
    <div class="relative hidden w-[420px] shrink-0 overflow-hidden bg-gradient-to-br from-blue-600 via-blue-700 to-indigo-800 lg:flex lg:flex-col">

        {{-- Decorative circles --}}
        <div class="pointer-events-none absolute -right-20 -top-20 h-72 w-72 rounded-full bg-white/5"></div>
        <div class="pointer-events-none absolute -bottom-16 -left-16 h-64 w-64 rounded-full bg-indigo-500/20"></div>
        <div class="pointer-events-none absolute right-10 top-1/3 h-32 w-32 rounded-full bg-white/5"></div>

        {{-- Brand --}}
        <div class="relative flex flex-1 flex-col items-center justify-center px-10 text-center">
            <div class="flex h-24 w-24 items-center justify-center rounded-3xl bg-white/10 ring-1 ring-white/20 backdrop-blur-sm">
                <img src="/images/bloomery-icon.png" alt="Bloomery" class="h-14 w-14 rounded-2xl object-cover">
            </div>
            <h1 class="mt-6 text-3xl font-bold text-white">Bloomery</h1>
            <p class="mt-2 text-base font-medium text-blue-200">Helpdesk Portal</p>

            <div class="mt-10 w-full space-y-3">
                @foreach ([
                    ['icon' => 'M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l5.654-4.654m5.657-4.656-.005-.005a1.023 1.023 0 0 0-.361-.214l-3.074-.925a1.023 1.023 0 0 1-.36-.214L9.25 7.5m6.174 1.667L9.25 7.5m4.424 7.672 2.496-3.03', 'label' => 'Service & ERP Requests'],
                    ['icon' => 'M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z', 'label' => 'Purchase & Design'],
                    ['icon' => 'M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z', 'label' => 'Casual Staff Management'],
                ] as $feature)
                    <div class="flex items-center gap-3 rounded-2xl bg-white/10 px-4 py-3 text-left ring-1 ring-white/10">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-white/10">
                            <svg class="h-4 w-4 text-blue-200" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $feature['icon'] }}"/>
                            </svg>
                        </div>
                        <span class="text-sm font-medium text-blue-100">{{ $feature['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Footer --}}
        <div class="relative px-10 pb-8 text-center">
            <p class="text-xs text-blue-300/60">© {{ date('Y') }} Bloomery. All rights reserved.</p>
        </div>
    </div>

    {{-- ── Right panel: form ── --}}
    <div class="flex flex-1 flex-col items-center justify-center px-6 py-12 sm:px-10">

        {{-- Mobile logo (hidden on lg) --}}
        <div class="mb-8 flex flex-col items-center lg:hidden">
            <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-blue-600 shadow-lg">
                <img src="/images/bloomery-icon.png" alt="Bloomery" class="h-10 w-10 rounded-xl object-cover">
            </div>
            <p class="mt-3 text-lg font-bold text-slate-800 dark:text-white">Bloomery Helpdesk</p>
        </div>

        <div class="w-full max-w-sm">

            <div class="mb-7">
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Selamat datang</h2>
                <p class="mt-1.5 text-sm text-slate-500 dark:text-slate-400">Masuk ke Helpdesk Portal untuk melanjutkan.</p>
            </div>

            <form wire:submit="authenticate" class="space-y-5">
                {{ $this->form }}

                @if (filament()->hasPasswordReset())
                    <div class="-mt-2 flex justify-end">
                        <a href="{{ filament()->getRequestPasswordResetUrl() }}"
                           class="text-xs text-slate-400 hover:text-blue-600 dark:hover:text-blue-400 transition-colors">
                            Lupa kata sandi?
                        </a>
                    </div>
                @endif

                <button type="submit"
                        wire:loading.attr="disabled"
                        class="flex w-full items-center justify-center gap-2 rounded-xl bg-blue-600 py-3.5 text-sm font-semibold text-white shadow-sm transition-all hover:bg-blue-700 active:scale-[.98] disabled:opacity-60">
                    <svg wire:loading wire:target="authenticate"
                         class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    Masuk
                </button>
            </form>

        </div>
    </div>

</div>
