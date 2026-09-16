@props(['active' => 'form'])

<div class="fixed bottom-0 left-1/2 -translate-x-1/2 w-full max-w-[430px] z-30 flex border-t border-gray-100 bg-white/95 backdrop-blur-sm dark:border-gray-800 dark:bg-gray-900/95">

    {{-- Audit --}}
    <a href="{{ route('filament.casual.pages.quality-control-audits') }}"
       class="flex flex-1 flex-col items-center gap-1 py-3">
        @if($active === 'form')
            <svg class="h-6 w-6 text-blue-600" fill="currentColor" viewBox="0 0 24 24">
                <path fill-rule="evenodd" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25Zm3.03 6.53-4.5 4.5a.75.75 0 0 1-1.06 0l-2.25-2.25a.75.75 0 1 1 1.06-1.06L10 11.69l3.97-3.97a.75.75 0 1 1 1.06 1.06Z" clip-rule="evenodd"/>
            </svg>
            <span class="text-xs font-semibold text-blue-600">Audit</span>
        @else
            <svg class="h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
            </svg>
            <span class="text-xs text-gray-400">Audit</span>
        @endif
    </a>

    {{-- Item Journal --}}
    @can('view quality control item journals')
        <a href="{{ \App\Filament\Casual\Pages\ItemJournalPage::getUrl(panel: 'casual') }}"
           class="flex flex-1 flex-col items-center gap-1 py-3">
            @if($active === 'item-journal')
                <x-heroicon-s-arrows-right-left class="h-6 w-6 text-blue-600" />
                <span class="text-xs font-semibold text-blue-600">Item Journal</span>
            @else
                <x-heroicon-o-arrows-right-left class="h-6 w-6 text-gray-400" />
                <span class="text-xs text-gray-400">Item Journal</span>
            @endif
        </a>
    @endcan

</div>
