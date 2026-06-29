<div
    class="flex min-h-screen items-center justify-center px-4"
    style="
        background-color: #1c2480;
        background-image:
            linear-gradient(rgba(255,255,255,0.04) 1px, transparent 1px),
            linear-gradient(90deg, rgba(255,255,255,0.04) 1px, transparent 1px);
        background-size: 32px 32px;
    "
>
    <div class="w-full max-w-sm rounded-2xl bg-white p-8 shadow-2xl">

        {{-- Logo --}}
        <div class="mb-7 flex justify-center">
            <img
                src="{{ asset('images/bloomery-icon.png') }}"
                alt="Bloomery"
                class="h-16 w-16 object-contain"
            >
        </div>

        {{-- Form --}}
        <form wire:submit="authenticate" class="space-y-5">
            {{ $this->form }}

            <button
                type="submit"
                wire:loading.attr="disabled"
                class="mt-2 flex w-full items-center justify-center gap-2 rounded-xl py-3 text-sm font-semibold text-white transition-all active:scale-[.98] disabled:opacity-60"
                style="background-color: #1c2480;"
            >
                <svg wire:loading wire:target="authenticate" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                <svg wire:loading.remove wire:target="authenticate" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9"/>
                </svg>
                Sign In
            </button>
        </form>

    </div>
</div>
