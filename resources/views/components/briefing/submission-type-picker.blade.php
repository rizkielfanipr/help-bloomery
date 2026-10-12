@props(['model', 'selected'])

@php
    $colorClasses = [
        'camera_only' => 'border-sky-300 bg-sky-50 text-sky-700 ring-1 ring-sky-300 dark:border-sky-500/40 dark:bg-sky-500/10 dark:text-sky-300',
        'photo' => 'border-indigo-300 bg-indigo-50 text-indigo-700 ring-1 ring-indigo-300 dark:border-indigo-500/40 dark:bg-indigo-500/10 dark:text-indigo-300',
        'photo_and_text' => 'border-amber-300 bg-amber-50 text-amber-700 ring-1 ring-amber-300 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-300',
        'text_only' => 'border-emerald-300 bg-emerald-50 text-emerald-700 ring-1 ring-emerald-300 dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-300',
        'checkbox' => 'border-gray-300 bg-gray-50 text-gray-700 ring-1 ring-gray-300 dark:border-gray-600 dark:bg-white/5 dark:text-gray-300',
    ];

    $iconColorClasses = [
        'camera_only' => 'text-sky-500 dark:text-sky-400',
        'photo' => 'text-indigo-500 dark:text-indigo-400',
        'photo_and_text' => 'text-amber-500 dark:text-amber-400',
        'text_only' => 'text-emerald-500 dark:text-emerald-400',
        'checkbox' => 'text-gray-400 dark:text-gray-500',
    ];

    // "checkbox" is legacy and only shown when a task already uses it, so it
    // never shows up as a selectable option for newly created points.
    $types = collect(\App\Enums\BriefingSubmissionType::cases())
        ->reject(fn (\App\Enums\BriefingSubmissionType $type) => $type === \App\Enums\BriefingSubmissionType::Checkbox && $selected !== 'checkbox')
        ->values();
@endphp

<div class="grid grid-cols-2 gap-2">
    @foreach($types as $type)
        <button type="button" wire:click="$set('{{ $model }}', '{{ $type->value }}')"
            class="flex items-center gap-2 rounded-lg border px-3 py-2.5 text-left text-sm font-medium transition-colors {{ $selected === $type->value ? $colorClasses[$type->value] : 'border-gray-200 bg-white text-gray-600 hover:border-gray-300 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-white/5' }}">
            <x-dynamic-component :component="$type->getIcon()" class="h-4 w-4 shrink-0 {{ $selected === $type->value ? '' : $iconColorClasses[$type->value] }}" />
            {{ $type->getLabel() }}
        </button>
    @endforeach
</div>
