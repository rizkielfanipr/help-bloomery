@props([
    'title' => 'Informasi Pengisian',
    'icon' => false,
    'headingClass' => 'mb-1 block text-[11px] font-bold uppercase tracking-wide',
])

<div {{ $attributes }}>
    @if($icon)
        <div class="flex items-start gap-2">
            <x-heroicon-o-information-circle class="mt-0.5 h-4 w-4 shrink-0 text-blue-500" />
            <div class="min-w-0 text-xs leading-relaxed text-blue-700 dark:text-blue-300">
                <p class="{{ $headingClass }}">{{ $title }}</p>
                {{ $slot }}
            </div>
        </div>
    @else
        <span class="{{ $headingClass }}">{{ $title }}</span>
        {{ $slot }}
    @endif
</div>
