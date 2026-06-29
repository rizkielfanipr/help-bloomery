<div class="flex flex-col bg-blue-600 dark:bg-blue-900"
     style="min-height:100dvh">

    {{-- ════════════════════════════════════════════
         VIOLET HEADER
    ════════════════════════════════════════════ --}}
    <div class="flex-shrink-0 px-5 pb-8 pt-14">
        <div class="mb-4 flex items-center gap-3">
            <a href="{{ route('filament.casual.pages.launcher-page') }}"
               class="flex h-9 w-9 items-center justify-center rounded-full bg-white/20 text-white transition active:bg-white/30">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
                </svg>
            </a>
            <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/20 text-white">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"/>
                </svg>
            </div>
            <span class="text-base font-semibold text-white">Sales Report</span>
        </div>

        <p class="text-blue-200">{{ auth()->user()->branch?->name ?? 'Tanpa Cabang' }}</p>
        <p class="text-xl font-semibold text-white">{{ now()->locale('id')->isoFormat('dddd, D MMMM Y') }}</p>
    </div>

    {{-- ════════════════════════════════════════════
         WHITE CONTENT CARD
    ════════════════════════════════════════════ --}}
    <div class="flex-1 overflow-y-auto rounded-t-3xl bg-gray-50 pb-28 pt-6 dark:bg-gray-950">

        @php $labelClass = 'mb-1.5 block text-xs font-semibold text-slate-600 dark:text-slate-400'; @endphp

        <div class="flex flex-col gap-4 px-5">
            <div class="flex flex-col gap-5 rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">

            {{-- Tanggal --}}
            <div>
                <label class="{{ $labelClass }}">Tanggal</label>
                <input type="date" wire:model.live="reportDate"
                       class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm text-slate-700 focus:border-blue-400 focus:outline-none focus:ring-0 dark:border-gray-700 dark:bg-gray-800 dark:text-slate-200">
            </div>

            {{-- Modal Shift --}}
            <div>
                <label class="{{ $labelClass }}">Modal Shift</label>
                <div class="grid grid-cols-2 gap-3">
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-800">
                        <p class="text-[10px] font-semibold text-slate-400">Shift 1</p>
                        <div class="mt-1 flex items-center gap-1">
                            <span class="text-xs text-slate-400">Rp</span>
                            <input type="number" wire:model="modalShift1" min="0" step="1000"
                                   placeholder="0"
                                   class="w-full border-0 bg-transparent p-0 text-sm font-semibold text-slate-700 placeholder-slate-300 focus:ring-0 dark:text-slate-200">
                        </div>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-800">
                        <p class="text-[10px] font-semibold text-slate-400">Shift 2</p>
                        <div class="mt-1 flex items-center gap-1">
                            <span class="text-xs text-slate-400">Rp</span>
                            <input type="number" wire:model="modalShift2" min="0" step="1000"
                                   placeholder="0"
                                   class="w-full border-0 bg-transparent p-0 text-sm font-semibold text-slate-700 placeholder-slate-300 focus:ring-0 dark:text-slate-200">
                        </div>
                    </div>
                </div>
            </div>

            {{-- Payment table --}}
            <div>
                <label class="{{ $labelClass }}">Sales Payment Method Take Away</label>
                <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">

                    {{-- Header row --}}
                    <div class="grid grid-cols-[1fr_90px_90px] border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-800/50">
                        <div class="px-3 py-2 text-[11px] font-semibold text-slate-500">Metode</div>
                        <div class="px-2 py-2 text-center text-[11px] font-semibold text-slate-500">Shift 1</div>
                        <div class="px-2 py-2 text-center text-[11px] font-semibold text-slate-500">Shift 2</div>
                    </div>

                    {{-- Data rows --}}
                    @foreach($this->paymentMethods as $method)
                        <div class="grid grid-cols-[1fr_90px_90px] border-b border-gray-100 last:border-0 dark:border-gray-800">
                            <div class="flex items-center px-3 py-2.5">
                                <span class="text-xs font-medium text-slate-700 dark:text-slate-300">{{ $method->name }}</span>
                            </div>
                            <div class="border-l border-gray-100 px-1.5 py-1.5 dark:border-gray-800">
                                <input type="number" wire:model="entries.{{ $method->id }}.shift_1"
                                       min="0" step="1000" placeholder="0"
                                       class="w-full rounded-lg border border-gray-200 bg-gray-50 px-2 py-1.5 text-right text-xs text-slate-700 placeholder-slate-300 focus:border-blue-400 focus:outline-none focus:ring-0 dark:border-gray-700 dark:bg-gray-800 dark:text-slate-200">
                            </div>
                            <div class="border-l border-gray-100 px-1.5 py-1.5 dark:border-gray-800">
                                <input type="number" wire:model="entries.{{ $method->id }}.shift_2"
                                       min="0" step="1000" placeholder="0"
                                       class="w-full rounded-lg border border-gray-200 bg-gray-50 px-2 py-1.5 text-right text-xs text-slate-700 placeholder-slate-300 focus:border-blue-400 focus:outline-none focus:ring-0 dark:border-gray-700 dark:bg-gray-800 dark:text-slate-200">
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
            </div>

            </div>{{-- /white card --}}

            {{-- Save button --}}
            <button wire:click="save" wire:loading.attr="disabled"
                    class="w-full rounded-2xl bg-blue-600 py-3.5 text-sm font-semibold text-white transition active:scale-95 disabled:opacity-60">
                <span wire:loading.remove wire:target="save">Simpan Sales Report</span>
                <span wire:loading wire:target="save">Menyimpan...</span>
            </button>

        </div>
    </div>

    {{-- ════════════════════════════════════════════
         BOTTOM NAV
    ════════════════════════════════════════════ --}}
    <x-sales-report.bottom-nav active="report" />

</div>
