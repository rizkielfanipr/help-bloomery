@props([
    'title',
    'description' => null,
    'maxWidth' => '5xl',
])

@php
    $widthClass = match ($maxWidth) {
        '7xl' => 'max-w-7xl',
        '6xl' => 'max-w-6xl',
        default => 'max-w-5xl',
    };
@endphp

<div {{ $attributes->class([
    'relative flex max-h-[90vh] w-full flex-col overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900',
    $widthClass,
]) }}>
    <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-5 py-4 dark:border-gray-700">
        <div class="min-w-0">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ $title }}</h3>
            @if(filled($description))
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $description }}</p>
            @endif
        </div>

        {{ $close }}
    </div>

    {{ $slot }}
</div>
