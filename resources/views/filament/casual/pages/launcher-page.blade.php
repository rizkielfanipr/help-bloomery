@php
    $user    = auth()->user();
    $branch  = $user->branch?->name ?? 'Bloomery';
    $tiles   = $this->tiles();
    $recents = $this->recentRequests();

    $firstName = \Illuminate\Support\Str::before($user->name, ' ');
    $initials  = collect(explode(' ', $user->name))->map(fn ($w) => strtoupper($w[0] ?? ''))->take(2)->join('');

    $hour     = now()->hour;
    $greeting = $hour < 11 ? 'Selamat Pagi' : ($hour < 15 ? 'Selamat Siang' : ($hour < 18 ? 'Selamat Sore' : 'Selamat Malam'));
    $emoji    = $hour < 11 ? '☀️' : ($hour < 15 ? '🌤️' : ($hour < 18 ? '🌆' : '🌙'));

    // Tile bg → icon color for the tile card style
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

    $statusClass = fn (string $color): string => match ($color) {
        'success' => 'bg-emerald-100 text-emerald-700',
        'warning' => 'bg-amber-100 text-amber-700',
        'danger'  => 'bg-red-100 text-red-700',
        'info'    => 'bg-sky-100 text-sky-700',
        'primary' => 'bg-blue-100 text-blue-700',
        default   => 'bg-slate-100 text-slate-600',
    };

    $currentPath = request()->path();
@endphp

{{-- ═══════════════════════════════════════════════
     WRAPPER
═══════════════════════════════════════════════ --}}
<div>
<div class="mx-auto max-w-[430px]" style="background:#f2f2f7; min-height:100dvh; display:flex; flex-direction:column;">

    {{-- ═══ TOP BAR ═══ --}}
    <div class="sticky top-0 z-50 flex items-center justify-between px-4 py-3.5"
         style="background:#065f46; box-shadow:0 1px 0 rgba(0,0,0,0.12)">

        <div class="flex h-8 w-8 items-center justify-center rounded-xl" style="background:rgba(255,255,255,0.12)">
            <img src="{{ asset('images/bloomery-icon.png') }}" alt="" class="h-5 w-5 object-contain" style="filter:brightness(10)">
        </div>

        <span class="text-[15px] font-semibold text-white">Bloomery</span>

        <a href="{{ route('filament.casual.pages.notification-page') }}"
           class="flex h-8 w-8 items-center justify-center rounded-full" style="background:rgba(255,255,255,0.12)">
            <svg class="h-4.5 w-4.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"/>
            </svg>
        </a>
    </div>

    {{-- ═══ SCROLLABLE CONTENT ═══ --}}
    <div class="flex-1 overflow-y-auto pb-24">

        {{-- White card —– rounded top --}}
        <div class="rounded-t-[28px] bg-white px-4 pt-5 pb-1" style="margin-top:2px">

            {{-- Greeting --}}
            <div class="mb-4">
                <h1 class="text-[22px] font-extrabold leading-snug text-slate-900">
                    {{ $greeting }}, {{ $firstName }}! {{ $emoji }}
                </h1>
                <p class="mt-0.5 text-[13px] text-slate-400">{{ $branch }}</p>
            </div>

            {{-- Search bar --}}
            <div class="mb-5 flex items-center gap-2.5 rounded-full px-4 py-2.5" style="background:#f2f2f7">
                <svg class="h-4 w-4 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
                </svg>
                <span class="text-[14px] text-slate-400">Cari layanan...</span>
            </div>

            {{-- ── SERVICE GRID ── --}}
            <div class="grid grid-cols-4 gap-2.5 pb-5">
                @foreach ($tiles as $tile)
                    @php $iconColor = $iconColorMap[$tile['iconBg']] ?? '#64748b'; @endphp
                    <a href="{{ $tile['href'] }}"
                       class="flex flex-col items-center gap-2 rounded-2xl px-1 pt-3.5 pb-3 transition-all active:scale-[0.90]"
                       style="background:#f2f2f7">

                        <div class="flex h-[46px] w-[46px] items-center justify-center rounded-[14px]" style="background:white">
                            <svg class="h-[22px] w-[22px]"
                                 style="color:{{ $iconColor }}"
                                 fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $tile['path'] }}"/>
                            </svg>
                        </div>

                        <span class="w-full text-center text-[10.5px] font-semibold leading-tight text-slate-600">
                            {{ $tile['label'] }}
                        </span>
                    </a>
                @endforeach
            </div>
        </div>

        {{-- ── INFO CARDS ── --}}
        <div class="mt-2 grid grid-cols-2 gap-3 bg-white px-4 py-4">
            <div class="flex items-center justify-between rounded-2xl border border-slate-100 px-4 py-3.5"
                 style="background:#f9fafb">
                <div>
                    <p class="text-[11px] font-medium text-slate-400">Cabang</p>
                    <p class="mt-0.5 text-[14px] font-bold text-slate-800">{{ \Str::limit($branch, 14) }}</p>
                </div>
                <div class="flex h-9 w-9 items-center justify-center rounded-xl" style="background:#dcfce7">
                    <svg class="h-5 w-5" style="color:#16a34a" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 0 0 3.75-.615A2.993 2.993 0 0 0 9.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 0 0 2.25 1.016 2.993 2.993 0 0 0 2.25-1.016 3.001 3.001 0 0 0 3.75.614m-16.5 0a3.004 3.004 0 0 1-.621-4.72l1.189-1.19A1.5 1.5 0 0 1 5.378 3h13.243a1.5 1.5 0 0 1 1.06.44l1.19 1.189a3 3 0 0 1-.621 4.72M6.75 18h3.75a.75.75 0 0 0 .75-.75V13.5a.75.75 0 0 0-.75-.75H6.75a.75.75 0 0 0-.75.75v3.75c0 .414.336.75.75.75Z"/>
                    </svg>
                </div>
            </div>
            <div class="flex items-center justify-between rounded-2xl border border-slate-100 px-4 py-3.5"
                 style="background:#f9fafb">
                <div>
                    <p class="text-[11px] font-medium text-slate-400">Pengajuan</p>
                    <p class="mt-0.5 text-[14px] font-bold text-slate-800">{{ $recents->count() }} aktif</p>
                </div>
                <div class="flex h-9 w-9 items-center justify-center rounded-xl" style="background:#dbeafe">
                    <svg class="h-5 w-5" style="color:#2563eb" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25Z"/>
                    </svg>
                </div>
            </div>
        </div>

        {{-- ── RECENT ACTIVITY ── --}}
        <div class="mt-2 bg-white px-4 pt-5 pb-4">

            <div class="mb-4 flex items-center justify-between">
                <span class="text-[17px] font-extrabold text-slate-900">Pengajuan Terbaru</span>
                @if ($recents->isNotEmpty())
                    <span class="text-[13px] font-semibold" style="color:#065f46">Lihat Semua →</span>
                @endif
            </div>

            @if ($recents->isEmpty())
                <div class="flex flex-col items-center gap-3 rounded-2xl px-4 py-10 text-center" style="background:#f9fafb; border:1.5px dashed #e2e8f0">
                    <div class="flex h-14 w-14 items-center justify-center rounded-full" style="background:#f1f5f9">
                        <svg class="h-6 w-6 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-[14px] font-semibold text-slate-500">Belum ada pengajuan</p>
                        <p class="mt-0.5 text-[12px] text-slate-400">Pengajuanmu akan tampil di sini</p>
                    </div>
                </div>
            @else
                {{-- Horizontal scroll cards --}}
                <div class="flex gap-3 overflow-x-auto pb-2" style="scrollbar-width:none; -ms-overflow-style:none;">
                    @foreach ($recents as $item)
                        @php $iconColor = $iconColorMap[$item['iconBg']] ?? '#64748b'; @endphp
                        <div class="w-44 shrink-0 rounded-2xl border border-slate-100 p-4" style="background:white; box-shadow:0 2px 8px rgba(0,0,0,0.05)">

                            <div class="mb-3 flex h-10 w-10 items-center justify-center rounded-xl" style="background:#f2f2f7">
                                <svg class="h-5 w-5" style="color:{{ $iconColor }}"
                                     fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['path'] }}"/>
                                </svg>
                            </div>

                            <p class="text-[10.5px] font-semibold text-slate-400">{{ $item['type'] }}</p>
                            <p class="mt-0.5 text-[12px] font-bold leading-snug text-slate-800 line-clamp-2">{{ $item['label'] }}</p>

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

    </div>{{-- end scrollable --}}

    {{-- ═══ BOTTOM NAV (fixed) ═══ --}}
    <nav class="fixed bottom-0 z-50 border-t border-slate-100 bg-white pb-safe"
         style="width:min(100%, 430px); left:50%; transform:translateX(-50%); box-shadow:0 -4px 20px rgba(0,0,0,0.06)">
        <div class="flex items-center justify-around px-2 py-2">

            {{-- Home --}}
            <a href="{{ route('filament.casual.pages.launcher-page') }}"
               class="flex flex-col items-center gap-1 px-3 py-1.5 rounded-xl transition-all active:scale-90"
               style="{{ request()->routeIs('filament.casual.pages.launcher-page') ? 'color:#065f46' : 'color:#94a3b8' }}">
                <svg class="h-6 w-6" fill="{{ request()->routeIs('filament.casual.pages.launcher-page') ? 'currentColor' : 'none' }}" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"/>
                </svg>
                <span class="text-[10px] font-semibold">Beranda</span>
            </a>

            {{-- Notifikasi --}}
            <a href="{{ route('filament.casual.pages.notification-page') }}"
               class="flex flex-col items-center gap-1 px-3 py-1.5 rounded-xl transition-all active:scale-90"
               style="{{ request()->routeIs('filament.casual.pages.notification-page') ? 'color:#065f46' : 'color:#94a3b8' }}">
                <svg class="h-6 w-6" fill="{{ request()->routeIs('filament.casual.pages.notification-page') ? 'currentColor' : 'none' }}" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"/>
                </svg>
                <span class="text-[10px] font-semibold">Notifikasi</span>
            </a>

            {{-- Center: Absensi (accent button) --}}
            <a href="{{ route('filament.casual.pages.clock-page') }}"
               class="flex flex-col items-center gap-1 transition-all active:scale-90">
                <div class="flex h-12 w-12 items-center justify-center rounded-full shadow-lg"
                     style="background:linear-gradient(145deg, #047857, #065f46); box-shadow:0 4px 16px rgba(6,95,70,0.4)">
                    <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                    </svg>
                </div>
                <span class="text-[10px] font-semibold" style="color:#065f46">Absensi</span>
            </a>

            {{-- Profil --}}
            <a href="{{ route('filament.casual.pages.profile-page') }}"
               class="flex flex-col items-center gap-1 px-3 py-1.5 rounded-xl transition-all active:scale-90"
               style="{{ request()->routeIs('filament.casual.pages.profile-page') ? 'color:#065f46' : 'color:#94a3b8' }}">
                <svg class="h-6 w-6" fill="{{ request()->routeIs('filament.casual.pages.profile-page') ? 'currentColor' : 'none' }}" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
                </svg>
                <span class="text-[10px] font-semibold">Profil</span>
            </a>

            {{-- Logout --}}
            <form method="POST" action="{{ route('filament.casual.auth.logout') }}" class="flex flex-col items-center">
                @csrf
                <button type="submit"
                        class="flex flex-col items-center gap-1 px-3 py-1.5 rounded-xl transition-all active:scale-90"
                        style="color:#94a3b8">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12.75"/>
                    </svg>
                    <span class="text-[10px] font-semibold">Keluar</span>
                </button>
            </form>

        </div>
    </nav>

</div>

<x-filament-actions::modals />
</div>
