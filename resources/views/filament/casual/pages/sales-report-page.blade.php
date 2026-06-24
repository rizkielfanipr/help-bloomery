<div class="flex flex-col bg-violet-600" style="min-height:100dvh">

    {{-- Header --}}
    <div class="relative overflow-hidden px-5 pb-10 pt-safe-or-4 pt-14">
        <div class="pointer-events-none absolute -right-8 -top-8 h-36 w-36 rounded-full bg-white/10"></div>

        <div class="flex items-center gap-3">
            <a href="{{ route('filament.casual.pages.launcher-page') }}"
               class="flex h-8 w-8 items-center justify-center rounded-full bg-white/20 text-white">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
                </svg>
            </a>
            <div>
                <p class="text-xs text-violet-200">{{ auth()->user()->branch?->name ?? 'Tanpa Cabang' }}</p>
                <h1 class="text-lg font-bold text-white">Sales Report</h1>
            </div>
        </div>
    </div>

    {{-- Content --}}
    <div class="-mt-5 flex-1 rounded-t-3xl bg-gray-50 px-4 pb-10 pt-6 dark:bg-gray-950">

        {{-- Date picker --}}
        <div class="mb-4 flex items-center gap-2">
            <label class="text-xs font-semibold uppercase tracking-widest text-slate-400">Tanggal</label>
            <input type="date" wire:model.live="reportDate"
                   class="ml-auto rounded-lg border-0 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 shadow-sm ring-1 ring-gray-200 focus:ring-2 focus:ring-violet-500 dark:bg-gray-900 dark:text-gray-200 dark:ring-gray-700">
        </div>

        {{-- Modal Shift --}}
        <div class="mb-4 grid grid-cols-2 gap-3">
            <div class="rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-700">
                <label class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Modal Shift 1</label>
                <div class="mt-1 flex items-center gap-1">
                    <span class="text-xs text-slate-400">Rp</span>
                    <input type="number" wire:model="modalShift1" min="0" step="1000"
                           placeholder="0"
                           class="w-full border-0 bg-transparent p-0 text-sm font-semibold text-slate-700 placeholder-slate-300 focus:ring-0 dark:text-slate-200">
                </div>
            </div>
            <div class="rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-700">
                <label class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Modal Shift 2</label>
                <div class="mt-1 flex items-center gap-1">
                    <span class="text-xs text-slate-400">Rp</span>
                    <input type="number" wire:model="modalShift2" min="0" step="1000"
                           placeholder="0"
                           class="w-full border-0 bg-transparent p-0 text-sm font-semibold text-slate-700 placeholder-slate-300 focus:ring-0 dark:text-slate-200">
                </div>
            </div>
        </div>

        {{-- Payment table --}}
        <div class="mb-4 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-700">

            <div class="border-b border-gray-100 px-4 py-2.5 dark:border-gray-800">
                <p class="text-[11px] font-bold uppercase tracking-widest text-slate-500">Sales Payment Method Take Away</p>
            </div>

            {{-- Header row --}}
            <div class="grid grid-cols-[1fr_90px_90px] gap-0 border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-800/50">
                <div class="px-3 py-2 text-[11px] font-semibold text-slate-500">Method Payment</div>
                <div class="px-2 py-2 text-center text-[11px] font-semibold text-slate-500">Shift 1</div>
                <div class="px-2 py-2 text-center text-[11px] font-semibold text-slate-500">Shift 2</div>
            </div>

            {{-- Data rows --}}
            @foreach($this->paymentMethods as $method)
                <div class="grid grid-cols-[1fr_90px_90px] gap-0 border-b border-gray-100 last:border-0 dark:border-gray-800">
                    <div class="flex items-center px-3 py-2.5">
                        <span class="text-xs font-medium text-slate-700 dark:text-slate-300">{{ $method->name }}</span>
                    </div>
                    <div class="border-l border-gray-100 px-1.5 py-1.5 dark:border-gray-800">
                        <input type="number" wire:model="entries.{{ $method->id }}.shift_1"
                               min="0" step="1000" placeholder="0"
                               class="w-full rounded-lg border-0 bg-gray-50 px-2 py-1.5 text-right text-xs text-slate-700 placeholder-slate-300 ring-1 ring-gray-200 focus:ring-2 focus:ring-violet-400 dark:bg-gray-800 dark:text-slate-200 dark:ring-gray-700">
                    </div>
                    <div class="border-l border-gray-100 px-1.5 py-1.5 dark:border-gray-800">
                        <input type="number" wire:model="entries.{{ $method->id }}.shift_2"
                               min="0" step="1000" placeholder="0"
                               class="w-full rounded-lg border-0 bg-gray-50 px-2 py-1.5 text-right text-xs text-slate-700 placeholder-slate-300 ring-1 ring-gray-200 focus:ring-2 focus:ring-violet-400 dark:bg-gray-800 dark:text-slate-200 dark:ring-gray-700">
                    </div>
                </div>
            @endforeach

            {{-- Total row --}}
            <div class="grid grid-cols-[1fr_90px_90px] border-t-2 border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800/50">
                <div class="px-3 py-2.5">
                    <span class="text-xs font-bold text-slate-700 dark:text-slate-200">Total</span>
                </div>
                <div class="border-l border-gray-200 px-2 py-2.5 text-right dark:border-gray-700">
                    <span class="text-xs font-bold text-slate-700 dark:text-slate-200">
                        Rp {{ number_format($this->totalShift1(), 0, ',', '.') }}
                    </span>
                </div>
                <div class="border-l border-gray-200 px-2 py-2.5 text-right dark:border-gray-700">
                    <span class="text-xs font-bold text-slate-700 dark:text-slate-200">
                        Rp {{ number_format($this->totalShift2(), 0, ',', '.') }}
                    </span>
                </div>
            </div>
        </div>

        {{-- Notes (per method) collapsed by default - accessible via detail link --}}
        <p class="mb-4 text-center text-[11px] text-slate-400">
            Kolom keterangan tersedia di view detail admin
        </p>

        {{-- Save button --}}
        <button wire:click="save" wire:loading.attr="disabled"
                class="w-full rounded-2xl bg-violet-600 py-3.5 text-sm font-semibold text-white shadow-sm transition active:scale-95 disabled:opacity-60">
            <span wire:loading.remove wire:target="save">Simpan Sales Report</span>
            <span wire:loading wire:target="save">Menyimpan...</span>
        </button>

    </div>
</div>
