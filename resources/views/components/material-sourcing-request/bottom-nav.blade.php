@props(['active' => 'form'])

<nav aria-label="Navigasi Request Sourcing" class="fixed bottom-0 left-1/2 z-30 flex w-full max-w-[430px] -translate-x-1/2 border-t border-gray-100 bg-white/95 backdrop-blur-sm dark:border-gray-800 dark:bg-gray-900/95">
    <a href="{{ \App\Filament\Casual\Pages\MaterialSourcingRequestPage::getUrl(panel: 'casual') }}" @if($active === 'form') aria-current="page" @endif
       class="flex flex-1 flex-col items-center gap-1 py-3">
        @if($active === 'form')
            <x-heroicon-s-plus-circle class="h-6 w-6 text-blue-600" />
            <span class="text-xs font-semibold text-blue-600">Buat Request</span>
        @else
            <x-heroicon-o-plus-circle class="h-6 w-6 text-gray-400" />
            <span class="text-xs text-gray-400">Buat Request</span>
        @endif
    </a>
    <a href="{{ \App\Filament\Casual\Pages\MaterialSourcingRequestHistoryPage::getUrl(panel: 'casual') }}" @if($active === 'history') aria-current="page" @endif
       class="flex flex-1 flex-col items-center gap-1 py-3">
        @if($active === 'history')
            <x-heroicon-s-clock class="h-6 w-6 text-blue-600" />
            <span class="text-xs font-semibold text-blue-600">Riwayat</span>
        @else
            <x-heroicon-o-clock class="h-6 w-6 text-gray-400" />
            <span class="text-xs text-gray-400">Riwayat</span>
        @endif
    </a>
</nav>
