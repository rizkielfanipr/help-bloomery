@php
    $user    = auth()->user();
    $branch  = $user->branch?->name ?? 'Bloomery';
    $tiles   = $this->tiles();
    $recents = $this->recentRequests();

    $firstName = \Illuminate\Support\Str::before($user->name, ' ');

    $hour     = now()->hour;
    $greeting = $hour < 11 ? 'Selamat Pagi' : ($hour < 15 ? 'Selamat Siang' : ($hour < 18 ? 'Selamat Sore' : 'Selamat Malam'));

    $iconColorMap = [
        'bg-blue-50'    => '#3b82f6',
        'bg-emerald-50' => '#10b981',
        'bg-orange-50'  => '#f97316',
        'bg-sky-50'     => '#0ea5e9',
        'bg-violet-50'  => '#8b5cf6',
        'bg-teal-50'    => '#14b8a6',
        'bg-amber-50'   => '#f59e0b',
        'bg-pink-50'    => '#ec4899',
        'bg-indigo-50'  => '#6366f1',
    ];

    $iconBgColorMap = [
        'bg-blue-50'    => '#eff6ff',
        'bg-emerald-50' => '#ecfdf5',
        'bg-orange-50'  => '#fff7ed',
        'bg-sky-50'     => '#f0f9ff',
        'bg-violet-50'  => '#f5f3ff',
        'bg-teal-50'    => '#f0fdfa',
        'bg-amber-50'   => '#fffbeb',
        'bg-pink-50'    => '#fdf2f8',
        'bg-indigo-50'  => '#eef2ff',
    ];

    $statusClass = fn (string $color): string => match ($color) {
        'success' => 'bg-emerald-100 text-emerald-700',
        'warning' => 'bg-amber-100 text-amber-700',
        'danger'  => 'bg-red-100 text-red-700',
        'info'    => 'bg-sky-100 text-sky-700',
        'primary' => 'bg-blue-100 text-blue-700',
        default   => 'bg-slate-100 text-slate-600',
    };

    $tilesJson = collect($tiles)->map(fn ($t) => [
        'label'       => $t['label'],
        'href'        => $t['href'],
        'path'        => $t['path'],
        'iconColor'   => $iconColorMap[$t['iconBg']] ?? '#64748b',
        'iconBgColor' => $iconBgColorMap[$t['iconBg']] ?? '#f8fafc',
    ])->toJson();
@endphp

<div class="flex flex-col bg-blue-600 dark:bg-blue-900" style="min-height:100dvh"
     x-data="{
         showAllMenus: false,
         search: '',
         tiles: {{ $tilesJson }},
         get isSearching() { return this.search.trim().length > 0; },
         get filteredTiles() {
             if (!this.isSearching) return [];
             const q = this.search.toLowerCase();
             return this.tiles.filter(t => t.label.toLowerCase().includes(q));
         }
     }">

    {{-- ════════════════════════════════════════════
         BLUE HEADER
    ════════════════════════════════════════════ --}}
    <div class="flex-shrink-0 px-5 pb-8 pt-14">

        {{-- Logo + brand --}}
        <div class="mb-5 flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-white/20">
                <img src="{{ asset('images/bloomery-icon.png') }}" alt="" class="h-6 w-6 object-contain" style="filter:brightness(10)">
            </div>
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-widest text-blue-300">Superapp</p>
                <p class="text-base font-bold leading-none text-white">Bloomery</p>
            </div>
        </div>

        {{-- Greeting --}}
        <p class="text-sm text-blue-200">{{ $branch }}</p>
        <h1 class="mt-0.5 text-2xl font-extrabold text-white">{{ $greeting }}, {{ $firstName }}!</h1>
    </div>

    {{-- ════════════════════════════════════════════
         WHITE CONTENT CARD
    ════════════════════════════════════════════ --}}
    <div class="flex-1 overflow-y-auto rounded-t-3xl bg-gray-50 pb-28 pt-5 dark:bg-gray-950">

        {{-- Search bar --}}
        <div class="mx-5 mb-5 flex items-center gap-2.5 rounded-2xl bg-white px-4 py-3 ring-1 ring-black/5 dark:bg-gray-900 dark:ring-white/10">
            <svg class="h-4 w-4 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
            </svg>
            <input x-model="search" type="search" placeholder="Cari layanan..."
                   class="flex-1 bg-transparent text-[14px] text-slate-700 placeholder-slate-400 focus:outline-none dark:text-slate-200"
                   autocomplete="off">
            <button x-show="search" @click="search = ''"
                    class="shrink-0 text-slate-300 transition active:text-slate-500"
                    style="display:none">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- Search results --}}
        <div x-show="isSearching" class="mx-5 mb-5" style="display:none">
            <template x-if="filteredTiles.length > 0">
                <div class="grid grid-cols-4 gap-2.5">
                    <template x-for="tile in filteredTiles" :key="tile.href">
                        <a :href="tile.href"
                           class="flex aspect-square flex-col items-center justify-center gap-1.5 rounded-2xl bg-white ring-1 ring-black/5 transition-all active:scale-[0.92] dark:bg-gray-900 dark:ring-white/10">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[12px]"
                                 :style="`background:${tile.iconBgColor}`">
                                <svg class="h-5 w-5" :style="`color:${tile.iconColor}`"
                                     fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" :d="tile.path"/>
                                </svg>
                            </div>
                            <span class="line-clamp-2 w-full px-1 text-center text-[9px] font-semibold leading-tight text-slate-600 dark:text-slate-300"
                                  x-text="tile.label"></span>
                        </a>
                    </template>
                </div>
            </template>
            <template x-if="filteredTiles.length === 0">
                <div class="flex flex-col items-center gap-2 py-10 text-center">
                    <div class="flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                        <svg class="h-6 w-6 text-slate-300 dark:text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
                        </svg>
                    </div>
                    <p class="text-sm font-semibold text-slate-400">Layanan tidak ditemukan</p>
                    <p class="text-xs text-slate-300">Coba kata kunci lain</p>
                </div>
            </template>
        </div>

        {{-- ── SERVICE GRID (default) ── --}}
        <div x-show="!isSearching" class="mx-5 mb-5 grid grid-cols-4 gap-2.5">
            @foreach (array_slice($tiles, 0, 7) as $tile)
                @php
                    $iconColor  = $iconColorMap[$tile['iconBg']] ?? '#64748b';
                    $iconBgColor = $iconBgColorMap[$tile['iconBg']] ?? '#f8fafc';
                @endphp
                <a href="{{ $tile['href'] }}"
                   class="flex aspect-square flex-col items-center justify-center gap-1.5 rounded-2xl bg-white ring-1 ring-black/5 transition-all active:scale-[0.92] dark:bg-gray-900 dark:ring-white/10">

                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[12px]"
                         style="background:{{ $iconBgColor }}">
                        <svg class="h-5 w-5"
                             style="color:{{ $iconColor }}"
                             fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $tile['path'] }}"/>
                        </svg>
                    </div>

                    <span class="line-clamp-2 w-full px-1 text-center text-[9px] font-semibold leading-tight text-slate-600 dark:text-slate-300">
                        {{ $tile['label'] }}
                    </span>
                </a>
            @endforeach

            {{-- Tile ke-8: Lihat Semua --}}
            <button type="button" @click="showAllMenus = true"
                    class="flex aspect-square flex-col items-center justify-center gap-1.5 rounded-2xl bg-white ring-1 ring-black/5 transition-all active:scale-[0.92] dark:bg-gray-900 dark:ring-white/10">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[12px]" style="background:#f1f5f9">
                    <svg class="h-5 w-5" style="color:#64748b" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z"/>
                    </svg>
                </div>
                <span class="line-clamp-2 w-full px-1 text-center text-[9px] font-semibold leading-tight text-slate-600 dark:text-slate-300">
                    Lihat Semua
                </span>
            </button>
        </div>

        {{-- ── INFO CARDS ── --}}
        <div class="mx-5 mb-5 grid grid-cols-2 gap-3">
            <div class="flex items-center justify-between rounded-2xl bg-white px-4 py-3.5 ring-1 ring-black/5 dark:bg-gray-900 dark:ring-white/10">
                <div>
                    <p class="text-[11px] font-medium text-slate-400">Cabang</p>
                    <p class="mt-0.5 text-[14px] font-bold text-slate-800 dark:text-white">{{ \Str::limit($branch, 14) }}</p>
                </div>
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-50 dark:bg-blue-900/30">
                    <svg class="h-5 w-5 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 0 0 3.75-.615A2.993 2.993 0 0 0 9.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 0 0 2.25 1.016 2.993 2.993 0 0 0 2.25-1.016 3.001 3.001 0 0 0 3.75.614m-16.5 0a3.004 3.004 0 0 1-.621-4.72l1.189-1.19A1.5 1.5 0 0 1 5.378 3h13.243a1.5 1.5 0 0 1 1.06.44l1.19 1.189a3 3 0 0 1-.621 4.72M6.75 18h3.75a.75.75 0 0 0 .75-.75V13.5a.75.75 0 0 0-.75-.75H6.75a.75.75 0 0 0-.75.75v3.75c0 .414.336.75.75.75Z"/>
                    </svg>
                </div>
            </div>
            <div class="flex items-center justify-between rounded-2xl bg-white px-4 py-3.5 ring-1 ring-black/5 dark:bg-gray-900 dark:ring-white/10">
                <div>
                    <p class="text-[11px] font-medium text-slate-400">Pengajuan</p>
                    <p class="mt-0.5 text-[14px] font-bold text-slate-800 dark:text-white">{{ $recents->count() }} aktif</p>
                </div>
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-violet-50 dark:bg-violet-900/30">
                    <svg class="h-5 w-5 text-violet-600 dark:text-violet-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25Z"/>
                    </svg>
                </div>
            </div>
        </div>

        {{-- ── RECENT ACTIVITY ── --}}
        <div class="mx-5">
            <div class="mb-3 flex items-center justify-between">
                <span class="text-[17px] font-extrabold text-slate-900 dark:text-white">Pengajuan Terbaru</span>
                @if ($recents->isNotEmpty())
                    <span class="text-[13px] font-semibold text-blue-600 dark:text-blue-400">Lihat Semua →</span>
                @endif
            </div>

            @if ($recents->isEmpty())
                <div class="flex flex-col items-center gap-3 rounded-2xl bg-white px-4 py-10 text-center ring-1 ring-black/5 dark:bg-gray-900 dark:ring-white/10">
                    <div class="flex h-14 w-14 items-center justify-center rounded-full bg-gray-50 dark:bg-gray-800">
                        <svg class="h-6 w-6 text-slate-300 dark:text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-[14px] font-semibold text-slate-500">Belum ada pengajuan</p>
                        <p class="mt-0.5 text-[12px] text-slate-400">Pengajuanmu akan tampil di sini</p>
                    </div>
                </div>
            @else
                <div class="flex gap-3 overflow-x-auto pb-2" style="scrollbar-width:none; -ms-overflow-style:none">
                    @foreach ($recents as $item)
                        @php
                            $iconColor   = $iconColorMap[$item['iconBg']] ?? '#64748b';
                            $iconBgColor = $iconBgColorMap[$item['iconBg']] ?? '#f8fafc';
                        @endphp
                        <div class="w-44 shrink-0 rounded-2xl bg-white p-4 ring-1 ring-black/5 dark:bg-gray-900 dark:ring-white/10">

                            <div class="mb-3 flex h-10 w-10 items-center justify-center rounded-xl"
                                 style="background:{{ $iconBgColor }}">
                                <svg class="h-5 w-5" style="color:{{ $iconColor }}"
                                     fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['path'] }}"/>
                                </svg>
                            </div>

                            <p class="text-[10.5px] font-semibold text-slate-400">{{ $item['type'] }}</p>
                            <p class="mt-0.5 line-clamp-2 text-[12px] font-bold leading-snug text-slate-800 dark:text-white">{{ $item['label'] }}</p>

                            <div class="mt-3 flex items-center justify-between">
                                <span class="rounded-full px-2 py-0.5 text-[9.5px] font-bold {{ $statusClass($item['status_color']) }}">
                                    {{ $item['status_label'] }}
                                </span>
                                <span class="text-[10px] text-slate-400">
                                    {{ \Carbon\Carbon::parse($item['date'])->locale('id')->diffForHumans() }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

    </div>

    {{-- ════════════════════════════════════════════
         ALL MENUS BOTTOM SHEET
    ════════════════════════════════════════════ --}}

    {{-- Backdrop --}}
    <div x-show="showAllMenus"
         x-transition:enter="transition duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-40 bg-black/60"
         style="display:none"
         @click="showAllMenus = false">
    </div>

    {{-- Sheet --}}
    <div x-show="showAllMenus"
         x-transition:enter="transition duration-300 ease-out"
         x-transition:enter-start="translate-y-full"
         x-transition:enter-end="translate-y-0"
         x-transition:leave="transition duration-200 ease-in"
         x-transition:leave-start="translate-y-0"
         x-transition:leave-end="translate-y-full"
         class="fixed bottom-0 left-1/2 -translate-x-1/2 w-full max-w-[430px] z-50 overflow-hidden rounded-t-3xl bg-white dark:bg-gray-900"
         style="display:none">

        {{-- Drag handle --}}
        <div class="flex justify-center pb-2 pt-3">
            <div class="h-1 w-10 rounded-full bg-gray-200 dark:bg-gray-700"></div>
        </div>

        {{-- Header --}}
        <div class="flex items-center justify-between px-5 pb-4 pt-1">
            <p class="text-base font-bold text-gray-900 dark:text-white">Semua Menu</p>
            <button @click="showAllMenus = false"
                    class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-100 text-gray-500 transition active:bg-gray-200 dark:bg-gray-800 dark:text-gray-400 dark:active:bg-gray-700">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- Tiles grid --}}
        <div class="grid grid-cols-4 gap-2.5 px-5 pb-10">
            @foreach ($tiles as $tile)
                @php
                    $iconColor   = $iconColorMap[$tile['iconBg']] ?? '#64748b';
                    $iconBgColor = $iconBgColorMap[$tile['iconBg']] ?? '#f8fafc';
                @endphp
                <a href="{{ $tile['href'] }}"
                   class="flex aspect-square flex-col items-center justify-center gap-1.5 rounded-2xl bg-gray-50 ring-1 ring-black/5 transition-all active:scale-[0.92] dark:bg-gray-800 dark:ring-white/10">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[12px]"
                         style="background:{{ $iconBgColor }}">
                        <svg class="h-5 w-5"
                             style="color:{{ $iconColor }}"
                             fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $tile['path'] }}"/>
                        </svg>
                    </div>
                    <span class="line-clamp-2 w-full px-1 text-center text-[9px] font-semibold leading-tight text-slate-600 dark:text-slate-300">
                        {{ $tile['label'] }}
                    </span>
                </a>
            @endforeach
        </div>
    </div>

    <x-filament-actions::modals />

    <x-launcher.bottom-nav active="home" />

</div>
