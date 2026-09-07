@props(['title'])

<header class="flex-shrink-0 px-5 pb-8 pt-14">
    <div class="mb-4 flex items-center gap-3">
        <a href="{{ \App\Filament\Casual\Pages\LauncherPage::getUrl() }}" aria-label="Kembali" class="flex h-9 w-9 items-center justify-center rounded-full bg-white/20 text-white transition active:bg-white/30">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/></svg>
        </a>
        <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/20 text-white">{{ $icon }}</div>
        <h1 class="text-base font-semibold text-white">{{ $title }}</h1>
    </div>
    <p class="text-blue-200">{{ auth()->user()->name }}</p>
    <p class="text-xl font-semibold text-white">{{ now()->locale('id')->isoFormat('dddd, D MMMM Y') }}</p>
</header>
