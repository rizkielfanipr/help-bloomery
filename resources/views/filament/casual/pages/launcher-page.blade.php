@php $user = auth()->user(); @endphp

<div class="flex flex-col bg-blue-600" style="min-height:100dvh">

    {{-- Header --}}
    <div class="relative overflow-hidden px-5 pb-10 pt-14">
        <div class="pointer-events-none absolute -right-8 -top-8 h-36 w-36 rounded-full bg-white/10"></div>
        <div class="pointer-events-none absolute -bottom-4 right-20 h-20 w-20 rounded-full bg-blue-500/40"></div>

        <p class="text-xs font-medium text-blue-200">
            {{ now()->locale('id')->isoFormat('dddd, D MMMM YYYY') }}
        </p>
        <h1 class="mt-1 text-2xl font-bold text-white">
            Halo, {{ \Illuminate\Support\Str::before($user->name, ' ') }}!
        </h1>
        <p class="mt-0.5 text-sm text-blue-200/80">Pilih layanan yang kamu butuhkan.</p>
    </div>

    {{-- Content card --}}
    <div class="-mt-5 flex-1 rounded-t-3xl bg-gray-50 px-4 pb-10 pt-6 dark:bg-gray-950">
        <p class="mb-4 text-[11px] font-semibold uppercase tracking-widest text-slate-400">Layanan</p>

        <div class="grid grid-cols-2 gap-3">
            @foreach ($this->tiles() as $tile)
                <a
                    href="{{ $tile['available'] ? $tile['href'] : '#' }}"
                    @if (! $tile['available']) @click.prevent="" @endif
                    class="relative flex flex-col items-start gap-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-black/5 dark:bg-gray-900 dark:ring-white/10 {{ $tile['available'] ? 'transition-transform active:scale-95' : 'cursor-not-allowed opacity-60' }}"
                >
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl {{ $tile['bg'] }}">
                        {!! $tile['svg'] !!}
                    </div>

                    <span class="text-[13px] font-semibold leading-snug text-slate-700 dark:text-slate-200">
                        {{ $tile['label'] }}
                    </span>

                    @if (! $tile['available'])
                        <span class="absolute right-3 top-3 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-medium text-slate-400 dark:bg-white/10 dark:text-slate-500">
                            Segera
                        </span>
                    @endif
                </a>
            @endforeach
        </div>

        <div class="mt-8 text-center">
            <form method="POST" action="{{ route('filament.casual.auth.logout') }}">
                @csrf
                <button type="submit" class="text-xs text-slate-400 underline-offset-2 transition-colors hover:text-slate-600 hover:underline dark:text-slate-600 dark:hover:text-slate-400">
                    Keluar dari akun
                </button>
            </form>
        </div>
    </div>

</div>
