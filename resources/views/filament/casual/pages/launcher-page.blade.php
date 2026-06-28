@php
    $user    = auth()->user();
    $branch  = $user->branch?->name ?? 'Bloomery';
    $tiles   = $this->tiles();
    $recents = $this->recentRequests();

    $statusClass = fn (string $color): string => match ($color) {
        'success' => 'bg-emerald-100 text-emerald-700',
        'warning' => 'bg-amber-100 text-amber-700',
        'danger'  => 'bg-red-100 text-red-700',
        'info'    => 'bg-sky-100 text-sky-700',
        'primary' => 'bg-blue-100 text-blue-700',
        default   => 'bg-gray-100 text-gray-600',
    };
@endphp

<div style="background:#f1f5f9; min-height:100dvh;">

    {{-- ══════════════════════════════════════════
         HEADER
    ══════════════════════════════════════════ --}}
    <div class="relative bg-gradient-to-br from-emerald-600 to-emerald-700 px-5 pb-14 pt-14">

        {{-- blobs clipped inside their own wrapper so they never affect siblings --}}
        <div class="pointer-events-none absolute inset-0 overflow-hidden">
            <div class="absolute -right-8 -top-8 h-48 w-48 rounded-full bg-white/[0.07]"></div>
            <div class="absolute -bottom-10 left-1/2 h-36 w-36 -translate-x-1/2 rounded-full bg-white/[0.05]"></div>
        </div>

        {{-- top bar: brand + logout --}}
        <div class="relative mb-5 flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-white/20">
                    <svg class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 0 0 3.75-.615A2.993 2.993 0 0 0 9.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 0 0 2.25 1.016 2.993 2.993 0 0 0 2.25-1.016 3.001 3.001 0 0 0 3.75.614m-16.5 0a3.004 3.004 0 0 1-.621-4.72l1.189-1.19A1.5 1.5 0 0 1 5.378 3h13.243a1.5 1.5 0 0 1 1.06.44l1.19 1.189a3 3 0 0 1-.621 4.72M6.75 18h3.75a.75.75 0 0 0 .75-.75V13.5a.75.75 0 0 0-.75-.75H6.75a.75.75 0 0 0-.75.75v3.75c0 .414.336.75.75.75Z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-[10px] font-semibold uppercase tracking-widest text-emerald-200">Bloomery Patisserie</p>
                    <p class="text-xs font-medium text-white/90">{{ $branch }}</p>
                </div>
            </div>
            <form method="POST" action="{{ route('filament.casual.auth.logout') }}">
                @csrf
                <button type="submit"
                        class="flex h-8 w-8 items-center justify-center rounded-full bg-white/10 text-white/70 transition active:bg-white/20">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12.75"/>
                    </svg>
                </button>
            </form>
        </div>

        {{-- greeting --}}
        <div class="relative flex items-center justify-between">
            <div>
                <p class="text-sm text-emerald-100/80">
                    {{ now()->locale('id')->isoFormat('dddd, D MMMM') }}
                </p>
                <h1 class="mt-0.5 text-2xl font-bold text-white">
                    Halo, {{ \Illuminate\Support\Str::before($user->name, ' ') }}!
                </h1>
                <p class="mt-0.5 text-sm text-emerald-200">Pilih layanan yang kamu butuhkan.</p>
            </div>
            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-white/20 text-sm font-bold text-white ring-2 ring-white/30">
                {{ collect(explode(' ', $user->name))->map(fn ($w) => strtoupper($w[0] ?? ''))->take(2)->join('') }}
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════
         MAIN CONTENT
    ══════════════════════════════════════════ --}}
    <div class="-mt-5 rounded-t-3xl bg-white px-4 pb-12 pt-6" style="min-height: calc(100dvh - 140px);">

        {{-- ── LAYANAN ──────────────────────────── --}}
        <p class="mb-3 px-0.5 text-[11px] font-bold uppercase tracking-widest text-slate-400">Layanan</p>

        <div class="grid grid-cols-4 gap-2">
            @foreach ($tiles as $tile)
                <a href="{{ $tile['href'] }}"
                   class="flex flex-col items-center gap-2 rounded-2xl bg-slate-50 px-1 py-3 transition active:scale-[0.93] active:bg-slate-100">

                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl {{ $tile['iconBg'] }}">
                        <svg class="h-[22px] w-[22px] {{ $tile['iconColor'] }}"
                             fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $tile['path'] }}"/>
                        </svg>
                    </div>

                    <span class="w-full text-center text-[10.5px] font-semibold leading-tight text-slate-600">
                        {{ $tile['label'] }}
                    </span>
                </a>
            @endforeach
        </div>

        {{-- ── RIWAYAT PENGAJUAN ────────────────── --}}
        <div class="mt-7">
            <p class="mb-3 px-0.5 text-[11px] font-bold uppercase tracking-widest text-slate-400">Riwayat Pengajuan</p>

            @if ($recents->isEmpty())
                <div class="flex flex-col items-center gap-2.5 rounded-2xl bg-slate-50 px-4 py-8 text-center">
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-slate-100">
                        <svg class="h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25Z"/>
                        </svg>
                    </div>
                    <p class="text-xs font-medium text-slate-500">Belum ada pengajuan</p>
                    <p class="text-[11px] text-slate-400">Pengajuan yang kamu kirim akan muncul di sini.</p>
                </div>
            @else
                <div class="overflow-hidden rounded-2xl bg-slate-50">
                    @foreach ($recents as $i => $item)
                        <div class="{{ $i > 0 ? 'border-t border-slate-100' : '' }} flex items-center gap-3 px-4 py-3.5">

                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl {{ $item['iconBg'] }}">
                                <svg class="h-[18px] w-[18px] {{ $item['iconColor'] }}"
                                     fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['path'] }}"/>
                                </svg>
                            </div>

                            <div class="min-w-0 flex-1">
                                <p class="truncate text-[13px] font-semibold leading-snug text-slate-700">
                                    {{ $item['label'] }}
                                </p>
                                <p class="mt-0.5 text-[11px] text-slate-400">
                                    {{ $item['type'] }} · {{ \Carbon\Carbon::parse($item['date'])->locale('id')->diffForHumans() }}
                                </p>
                            </div>

                            <span class="shrink-0 rounded-full px-2.5 py-1 text-[10px] font-bold {{ $statusClass($item['status_color']) }}">
                                {{ $item['status_label'] }}
                            </span>

                        </div>
                    @endforeach
                </div>
            @endif
        </div>

    </div>

    <x-filament-actions::modals />
</div>
