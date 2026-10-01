@props([
    'options',
    'model' => 'branchIds',
])

@php
    $labels = collect($options)->mapWithKeys(fn (array $option): array => [
        (string) $option['branch']->id => $option['branch']->name,
    ])->all();
@endphp

<div
    class="relative"
    x-data="{
        open: false,
        selected: $wire.entangle(@js($model)),
        labels: @js($labels),
        summary() {
            const names = (this.selected || [])
                .map((id) => this.labels[String(id)])
                .filter(Boolean)

            if (names.length === 0) return 'Pilih Branch Tujuan'
            if (names.length === 1) return names[0]

            return `${names.length} branch dipilih`
        },
    }"
    x-on:click.outside="open = false"
    x-on:keydown.escape.window="open = false"
>
    <button
        type="button"
        x-on:click="open = ! open"
        class="flex w-full items-center justify-between gap-3 rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-left text-sm text-gray-800 transition hover:border-gray-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
        x-bind:aria-expanded="open"
    >
        <span class="truncate" x-text="summary()"></span>
        <x-heroicon-o-chevron-down class="h-4 w-4 shrink-0 text-gray-400 transition" x-bind:class="open && 'rotate-180'" />
    </button>

    <div
        x-cloak
        x-show="open"
        x-transition.origin.top
        class="absolute z-[160] mt-2 max-h-64 w-full overflow-y-auto rounded-xl border border-gray-200 bg-white p-2 dark:border-gray-700 dark:bg-gray-900"
    >
        @forelse($options as $option)
            @php
                $branch = $option['branch'];
                $resolution = $option['resolution'];
                $resolved = $resolution->isResolved();
            @endphp
            <label class="flex items-start gap-3 rounded-lg px-3 py-2.5 {{ $resolved ? 'cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800' : 'cursor-not-allowed opacity-55' }}">
                <input
                    type="checkbox"
                    value="{{ $branch->id }}"
                    wire:model="{{ $model }}"
                    @disabled(! $resolved)
                    class="mt-0.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800"
                >
                <span class="min-w-0">
                    <span class="block truncate text-sm font-semibold text-gray-800 dark:text-gray-100">{{ $branch->name }}</span>
                    <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">
                        @if($resolved)
                            {{ $resolution->mapping->esb_comcode }} · {{ $resolution->mapping->esb_branch_code }}
                        @else
                            {{ $resolution->blockedReason }}
                        @endif
                    </span>
                </span>
            </label>
        @empty
            <p class="px-3 py-4 text-center text-sm text-gray-500">Tidak ada branch yang dapat dipilih.</p>
        @endforelse
    </div>
</div>
