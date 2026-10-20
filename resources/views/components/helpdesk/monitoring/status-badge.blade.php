@props(['label', 'color' => 'gray', 'icon' => null])

@php
    $classes = match ($color) {
        'success' => 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300',
        'warning' => 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300',
        'danger' => 'border-red-200 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300',
        'info' => 'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-300',
        default => 'border-gray-200 bg-gray-50 text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300',
    };
@endphp

<span {{ $attributes->class(['inline-flex items-center gap-1 rounded-lg border px-2 py-0.5 text-xs font-semibold', $classes]) }}>
    @if ($icon)
        <x-dynamic-component :component="$icon" class="h-3.5 w-3.5" aria-hidden="true" />
    @endif
    {{ $label }}
</span>
