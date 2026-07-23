@props([
    'code',
    'title',
    'subtitle' => null,
    'status',
    'statusColor' => 'info',
    'metaLabel' => null,
    'metaValue' => null,
])

@php
    $badgeClass = match($statusColor) {
        'success' => 'bg-emerald-400/20 text-emerald-200',
        'danger' => 'bg-red-400/20 text-red-200',
        'warning' => 'bg-amber-400/20 text-amber-200',
        'purple' => 'bg-purple-400/20 text-purple-200',
        default => 'bg-blue-400/20 text-blue-200',
    };
@endphp

<div class="mx-auto w-full max-w-[1600px] space-y-6">
    <header class="overflow-hidden rounded-2xl bg-gradient-to-r from-blue-700 via-blue-600 to-indigo-600 p-6 text-white shadow-xl shadow-blue-900/15">
        <div class="flex flex-col justify-between gap-5 lg:flex-row lg:items-center">
            <div>
                <div class="mb-3 flex flex-wrap items-center gap-2">
                    <span class="rounded-lg bg-white/10 px-3 py-1 font-mono text-sm font-bold ring-1 ring-white/15">{{ $code }}</span>
                    <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $badgeClass }}">{{ $status }}</span>
                </div>
                <h1 class="text-2xl font-bold tracking-tight lg:text-3xl">{{ $title }}</h1>
                @if($subtitle)<p class="mt-1 max-w-3xl text-sm text-slate-300">{{ $subtitle }}</p>@endif
            </div>
            @if($metaLabel || $metaValue)
                <div class="rounded-xl bg-white/5 px-5 py-3 ring-1 ring-white/10 lg:min-w-[240px]">
                    <p class="text-xs text-slate-400">{{ $metaLabel }}</p>
                    <p class="mt-1 font-semibold">{{ $metaValue }}</p>
                </div>
            @endif
        </div>
    </header>

    <div class="grid items-start gap-6 xl:grid-cols-12">
        <main class="space-y-6 xl:col-span-8">
            {{ $main }}
        </main>
        <aside class="space-y-6 xl:sticky xl:top-6 xl:col-span-4">
            {{ $aside }}
        </aside>
    </div>
</div>
