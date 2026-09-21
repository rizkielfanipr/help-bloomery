@php
    $badgeTone = match ($badge['tone'] ?? 'gray') {
        'success' => 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-300',
        default => 'border-gray-200 bg-gray-50 text-gray-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300',
    };
@endphp

<section class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
    <div class="flex flex-col gap-5 p-5 sm:p-6 md:flex-row md:items-center md:justify-between">
        <div class="flex min-w-0 items-start gap-4">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-950/40 dark:text-blue-300">
                <x-dynamic-component :component="$icon" class="h-6 w-6" />
            </div>
            <div class="min-w-0">
                <p class="text-xs font-bold uppercase tracking-wider text-blue-600 dark:text-blue-400">{{ $eyebrow }}</p>
                <h2 class="mt-1 break-words text-2xl font-bold text-gray-950 dark:text-white">{{ $title }}</h2>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-gray-500 dark:text-gray-400">{{ $description }}</p>
            </div>
        </div>
        @if(! empty($badge) || ! empty($backUrl))
            <div class="flex shrink-0 flex-col items-start gap-2 self-start md:items-end">
                @if(! empty($badge))
                    <span class="inline-flex items-center rounded-lg border px-3 py-2 text-sm font-semibold {{ $badgeTone }}">{{ $badge['label'] }}</span>
                @endif
                @if(! empty($backUrl))
                    <a href="{{ $backUrl }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-blue-700 hover:underline dark:text-blue-300">
                        <x-heroicon-o-arrow-left class="h-4 w-4" />
                        {{ $backLabel ?? 'Kembali' }}
                    </a>
                @endif
            </div>
        @endif
    </div>
</section>
