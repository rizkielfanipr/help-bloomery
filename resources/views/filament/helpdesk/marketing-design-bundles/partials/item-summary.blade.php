{{-- Identity of the item a specification belongs to; shared by the specification list and its modal. --}}
@php
    $designFile = $item->file;
    $framed ??= false;
@endphp
<div @class(['flex min-w-0 items-start gap-3', 'rounded-xl border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-800/60' => $framed])>
    <div @class([
        'flex h-10 w-10 shrink-0 items-center justify-center rounded-xl',
        'bg-amber-50 text-amber-600 dark:bg-amber-950/40 dark:text-amber-300' => $item->isPhysical(),
        'bg-blue-50 text-blue-600 dark:bg-blue-950/40 dark:text-blue-300' => ! $item->isPhysical(),
    ])>
        <x-heroicon-o-cube class="h-5 w-5" />
    </div>
    <div class="min-w-0">
        <p class="break-words font-bold text-gray-900 dark:text-white">{{ $item->title }}</p>
        <div class="mt-1 flex flex-wrap items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
            <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 font-semibold text-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ $item->typeLabel() }}</span>
            @if($designFile)
                <span class="inline-flex min-w-0 items-start gap-1 [overflow-wrap:anywhere]"><x-heroicon-o-paper-clip class="mt-0.5 h-3.5 w-3.5 shrink-0" /><span>{{ $designFile->original_name }} · <span class="whitespace-nowrap">{{ strtoupper($designFile->extension) }} · {{ $designFile->humanSize() }}</span></span></span>
            @else
                <span>Belum ada file desain</span>
            @endif
        </div>
    </div>
</div>
