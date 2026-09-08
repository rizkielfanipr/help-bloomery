@php
    $counts = $this->counts();
    $branches = $this->getBranches();
@endphp

<div class="min-h-dvh bg-gray-50 dark:bg-gray-950 pb-16">
    <header class="bg-blue-600 px-5 pb-8 pt-10 text-white shadow-sm">
        <div class="mx-auto max-w-2xl">
            <a href="{{ \App\Filament\Casual\Pages\LauncherPage::getUrl() }}" class="mb-3 inline-flex items-center gap-2 text-sm text-blue-100 hover:text-white transition">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
                </svg>
                Beranda
            </a>
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">SOP Store</h1>
                    <p class="mt-0.5 text-xs text-blue-100">Dokumen standar operasional untuk branch kamu.</p>
                </div>
                <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white/10 text-white">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25A8.966 8.966 0 0 1 18 3.75c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                    </svg>
                </div>
            </div>

            {{-- Summary Stats --}}
            <div class="mt-4 grid grid-cols-3 gap-2">
                <div class="rounded-xl bg-white/10 p-2.5 backdrop-blur-xs">
                    <p class="text-[11px] text-blue-100">Total SOP</p>
                    <p class="mt-0.5 text-lg font-bold text-white">{{ $counts['all'] }}</p>
                </div>
                <div class="rounded-xl bg-white/10 p-2.5 backdrop-blur-xs">
                    <p class="text-[11px] text-blue-100">Perlu Dibaca</p>
                    <p class="mt-0.5 text-lg font-bold text-amber-200">{{ $counts['unacknowledged'] }}</p>
                </div>
                <div class="rounded-xl bg-white/10 p-2.5 backdrop-blur-xs">
                    <p class="text-[11px] text-blue-100">Selesai</p>
                    <p class="mt-0.5 text-lg font-bold text-emerald-200">{{ $counts['acknowledged'] }}</p>
                </div>
            </div>
        </div>
    </header>

    <main class="mx-auto -mt-3 max-w-2xl space-y-3 px-4">
        {{-- Search and Branch Filter --}}
        <div class="rounded-2xl border border-gray-200/80 bg-white p-3 shadow-xs dark:border-gray-800 dark:bg-gray-900 space-y-2.5">
            <div class="relative">
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Cari judul, kode, atau kategori SOP..."
                    class="w-full rounded-xl border border-gray-200 bg-gray-50 py-2.5 pl-9 pr-3 text-sm text-gray-800 placeholder-gray-400 focus:border-blue-500 focus:bg-white focus:outline-hidden dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                />
                <svg class="absolute left-3 top-3 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                </svg>
            </div>

            @if($branches->count() > 1)
                <div class="flex items-center gap-2">
                    <span class="text-xs font-medium text-gray-500 shrink-0">Branch:</span>
                    <select
                        wire:model.live="selectedBranchId"
                        class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-xs font-semibold text-gray-700 focus:border-blue-500 focus:bg-white focus:outline-hidden dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                    >
                        <option value="">Semua Branch ({{ $branches->count() }})</option>
                        @foreach($branches as $b)
                            <option value="{{ $b->id }}">{{ $b->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            {{-- Tabs --}}
            <div class="flex gap-1.5 overflow-x-auto pt-1 no-scrollbar text-xs font-semibold">
                <button
                    wire:click="setFilter('all')"
                    class="rounded-xl px-3 py-1.5 transition shrink-0 {{ $this->filter === 'all' ? 'bg-blue-600 text-white shadow-xs' : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300' }}"
                >
                    Semua ({{ $counts['all'] }})
                </button>
                <button
                    wire:click="setFilter('unread')"
                    class="rounded-xl px-3 py-1.5 transition shrink-0 {{ $this->filter === 'unread' ? 'bg-blue-600 text-white shadow-xs' : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300' }}"
                >
                    Belum Dibuka ({{ $counts['unread'] }})
                </button>
                <button
                    wire:click="setFilter('unacknowledged')"
                    class="rounded-xl px-3 py-1.5 transition shrink-0 {{ $this->filter === 'unacknowledged' ? 'bg-blue-600 text-white shadow-xs' : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300' }}"
                >
                    Perlu Konfirmasi ({{ $counts['unacknowledged'] }})
                </button>
                <button
                    wire:click="setFilter('acknowledged')"
                    class="rounded-xl px-3 py-1.5 transition shrink-0 {{ $this->filter === 'acknowledged' ? 'bg-blue-600 text-white shadow-xs' : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300' }}"
                >
                    Sudah Dipahami ({{ $counts['acknowledged'] }})
                </button>
            </div>
        </div>

        {{-- SOP Cards --}}
        @forelse($this->assignments() as $assignment)
            <article class="rounded-2xl border border-gray-200/80 bg-white p-4 shadow-xs dark:border-gray-800 dark:bg-gray-900 transition hover:border-gray-300">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <span class="rounded-md bg-blue-50 px-2 py-0.5 text-[11px] font-bold text-blue-700 dark:bg-blue-950/40 dark:text-blue-300">
                                {{ $assignment->sop->code }}
                            </span>
                            <span class="rounded-md bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                                v{{ $assignment->sop->version }}
                            </span>
                            @if($assignment->sop->category)
                                <span class="rounded-md bg-purple-50 px-2 py-0.5 text-[11px] font-medium text-purple-700 dark:bg-purple-950/40 dark:text-purple-300">
                                    {{ $assignment->sop->category }}
                                </span>
                            @endif
                        </div>
                        <h2 class="mt-2 text-base font-bold text-gray-900 dark:text-white leading-snug">
                            {{ $assignment->sop->title }}
                        </h2>
                        <div class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                            <span class="inline-flex items-center gap-1">
                                <svg class="h-3.5 w-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                                </svg>
                                {{ $assignment->branch->name }}
                            </span>
                            <span class="inline-flex items-center gap-1">
                                <svg class="h-3.5 w-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                                </svg>
                                Berlaku {{ $assignment->sop->effective_date->format('d M Y') }}
                            </span>
                        </div>
                    </div>
                    <span class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-bold {{ $assignment->acknowledged_at ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300' : ($assignment->opened_at ? 'bg-blue-100 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300') }}">
                        {{ $assignment->acknowledged_at ? 'Dipahami' : ($assignment->opened_at ? 'Dibuka' : 'Baru') }}
                    </span>
                </div>

                @if($assignment->sop->summary)
                    <div class="mt-3 rounded-xl bg-gray-50 p-3 text-xs leading-relaxed text-gray-600 dark:bg-gray-800/60 dark:text-gray-300">
                        {{ $assignment->sop->summary }}
                    </div>
                @endif

                @if($assignment->acknowledged_at)
                    <div class="mt-3 flex items-center gap-1.5 text-[11px] text-emerald-600 dark:text-emerald-400">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Dikonfirmasi pada {{ $assignment->acknowledged_at->format('d M Y H:i') }}
                    </div>
                @endif

                <div class="mt-4 flex gap-2">
                    <button
                        wire:click="openSop({{ $assignment->id }})"
                        class="flex-1 inline-flex items-center justify-center gap-2 rounded-xl border border-blue-200 bg-blue-50 px-3 py-2.5 text-xs font-bold text-blue-700 transition hover:bg-blue-100 active:scale-[0.98] dark:border-blue-900/50 dark:bg-blue-950/30 dark:text-blue-300"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                        </svg>
                        Lihat Dokumen
                    </button>
                    @if(! $assignment->acknowledged_at)
                        <button
                            wire:click="acknowledge({{ $assignment->id }})"
                            wire:confirm="Konfirmasi: Saya menyatakan telah membaca, memahami, dan siap menjalankan SOP ini."
                            class="flex-1 inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-3 py-2.5 text-xs font-bold text-white shadow-xs transition hover:bg-blue-700 active:scale-[0.98]"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                            </svg>
                            Saya Sudah Memahami
                        </button>
                    @endif
                </div>
            </article>
        @empty
            <div class="rounded-2xl border border-gray-200/80 bg-white px-6 py-16 text-center shadow-xs dark:border-gray-800 dark:bg-gray-900">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-blue-50 text-blue-500 dark:bg-blue-950/40">
                    <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25A8.966 8.966 0 0 1 18 3.75c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                    </svg>
                </div>
                <h3 class="mt-4 text-base font-bold text-gray-900 dark:text-white">Tidak ada SOP ditemukan</h3>
                <p class="mt-1 text-xs text-gray-500">
                    @if(filled($this->search) || $this->filter !== 'all' || $this->selectedBranchId)
                        Coba sesuaikan pencarian atau filter status kamu.
                    @else
                        Belum ada SOP yang ditugaskan untuk branch kamu.
                    @endif
                </p>
            </div>
        @endforelse
    </main>
</div>
