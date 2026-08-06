@props(['active' => 'form'])

<div class="fixed bottom-0 left-1/2 -translate-x-1/2 w-full max-w-[430px] z-30 flex border-t border-gray-100 bg-white/95 backdrop-blur-sm dark:border-gray-800 dark:bg-gray-900/95">

    {{-- Pengajuan --}}
    <a href="{{ route('filament.casual.pages.content-request-page') }}"
       class="flex flex-1 flex-col items-center gap-1 py-3">
        @if($active === 'form')
            <svg class="h-6 w-6 text-blue-600" fill="currentColor" viewBox="0 0 24 24">
                <path fill-rule="evenodd" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25ZM12.75 9a.75.75 0 0 0-1.5 0v2.25H9a.75.75 0 0 0 0 1.5h2.25V15a.75.75 0 0 0 1.5 0v-2.25H15a.75.75 0 0 0 0-1.5h-2.25V9Z" clip-rule="evenodd"/>
            </svg>
            <span class="text-xs font-semibold text-blue-600">Pengajuan</span>
        @else
            <svg class="h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v6m3-3H9m12 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
            </svg>
            <span class="text-xs text-gray-400">Pengajuan</span>
        @endif
    </a>

    {{-- Riwayat --}}
    <a href="{{ route('filament.casual.pages.content-request-history-page') }}"
       class="flex flex-1 flex-col items-center gap-1 py-3">
        @if($active === 'history')
            <svg class="h-6 w-6 text-blue-600" fill="currentColor" viewBox="0 0 24 24">
                <path fill-rule="evenodd" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25ZM12.75 6a.75.75 0 0 0-1.5 0v6c0 .414.336.75.75.75h4.5a.75.75 0 0 0 0-1.5h-3.75V6Z" clip-rule="evenodd"/>
            </svg>
            <span class="text-xs font-semibold text-blue-600">Riwayat</span>
        @else
            <svg class="h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
            </svg>
            <span class="text-xs text-gray-400">Riwayat</span>
        @endif
    </a>

</div>
