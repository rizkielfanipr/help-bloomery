@php
    $isShift1    = $shift === 1;
    $labelClass  = 'mb-1.5 block text-xs font-semibold text-slate-600 dark:text-slate-400';
@endphp

<div class="{{ $isShift1 ? 'bg-blue-600 dark:bg-blue-900' : 'bg-indigo-600 dark:bg-indigo-900' }} flex flex-col"
     style="min-height:100dvh">

    {{-- ════════════════════════════════════════════
         HEADER
    ════════════════════════════════════════════ --}}
    <div class="flex-shrink-0 px-5 pb-8 pt-14">
        <div class="mb-4 flex items-center gap-3">
            <a href="{{ route('filament.casual.pages.sales-report-page') }}?reportDate={{ $reportDate }}"
               wire:navigate
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
            <span class="text-base font-semibold text-white">Sales Report – Shift {{ $shift }}</span>
        </div>

        <p class="{{ $isShift1 ? 'text-blue-200' : 'text-indigo-200' }}">
            {{ auth()->user()->branch?->name ?? 'Tanpa Cabang' }}
        </p>
        <p class="text-xl font-semibold text-white">
            {{ \Carbon\Carbon::parse($reportDate)->locale('id')->isoFormat('dddd, D MMMM Y') }}
        </p>
    </div>

    {{-- ════════════════════════════════════════════
         WHITE CONTENT CARD
    ════════════════════════════════════════════ --}}
    <div class="flex-1 overflow-y-auto rounded-t-3xl bg-gray-50 pb-10 pt-6 dark:bg-gray-950">

        <div class="flex flex-col gap-4 px-5">

            {{-- Submitted banner --}}
            @if($isSubmitted)
                <div class="flex items-center gap-3 rounded-2xl border border-green-200 bg-green-50 p-4 dark:border-green-800 dark:bg-green-900/20">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/50">
                        <svg class="h-5 w-5 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-green-700 dark:text-green-400">Data sudah terkunci</p>
                        <p class="text-xs text-green-600 dark:text-green-500">Shift {{ $shift }} tidak dapat diubah kembali.</p>
                    </div>
                </div>
            @endif

            {{-- Tanggal (read-only) --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
                <label class="{{ $labelClass }}">Tanggal</label>
                <div class="flex items-center gap-2 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-800">
                    <svg class="h-4 w-4 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>
                    </svg>
                    <span class="text-sm text-slate-600 dark:text-slate-300">
                        {{ \Carbon\Carbon::parse($reportDate)->locale('id')->isoFormat('dddd, D MMMM Y') }}
                    </span>
                </div>
            </div>

            {{-- Form card --}}
            <div class="flex flex-col gap-4 rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">

                {{-- Modal --}}
                <div>
                    <label class="{{ $labelClass }}">Modal Shift {{ $shift }}</label>
                    @if($isSubmitted)
                        <div class="flex items-center gap-2 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-800">
                            <span class="text-xs text-slate-400">Rp</span>
                            <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">
                                {{ number_format((float) $modalShift, 0, ',', '.') ?: '0' }}
                            </span>
                        </div>
                    @else
                        <div class="flex items-center gap-2 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-800">
                            <span class="text-xs text-slate-400">Rp</span>
                            <input type="number" wire:model="modalShift" min="0" step="1000" placeholder="0"
                                   class="w-full border-0 bg-transparent p-0 text-sm font-semibold text-slate-700 placeholder-slate-300 focus:ring-0 dark:text-slate-200">
                        </div>
                        @error('modalShift') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                    @endif
                </div>

                {{-- Payment rows --}}
                <div>
                    <label class="{{ $labelClass }}">Sales Payment Method</label>
                    <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">

                        @foreach($this->paymentMethods as $method)
                            <div class="flex items-center gap-3 border-b border-gray-100 px-4 py-3 last:border-0 dark:border-gray-800">
                                <span class="flex-1 text-sm font-medium text-slate-700 dark:text-slate-300">{{ $method->name }}</span>
                                @if($isSubmitted)
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-xs text-slate-400">Rp</span>
                                        <span class="min-w-[80px] text-right text-sm text-slate-600 dark:text-slate-300">
                                            {{ number_format((float) ($entries[$method->id]['amount'] ?? 0), 0, ',', '.') ?: '0' }}
                                        </span>
                                    </div>
                                @else
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-xs text-slate-400">Rp</span>
                                        <input type="number" wire:model="entries.{{ $method->id }}.amount"
                                               min="0" step="1000" placeholder="0"
                                               class="w-28 rounded-lg border border-gray-200 bg-gray-50 px-2 py-1.5 text-right text-sm text-slate-700 placeholder-slate-300 focus:outline-none focus:ring-0 dark:border-gray-700 dark:bg-gray-800 dark:text-slate-200
                                                      {{ $isShift1 ? 'focus:border-blue-400' : 'focus:border-indigo-400' }}">
                                    </div>
                                @endif
                            </div>
                        @endforeach

                        {{-- Total row --}}
                        <div class="flex items-center gap-3 border-t-2 border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-800/50">
                            <span class="flex-1 text-sm font-bold text-slate-700 dark:text-slate-200">Total</span>
                            <span class="{{ $isShift1 ? 'text-blue-600 dark:text-blue-400' : 'text-indigo-600 dark:text-indigo-400' }} text-sm font-bold">
                                Rp {{ number_format($this->total(), 0, ',', '.') }}
                            </span>
                        </div>
                    </div>
                </div>

            </div>{{-- /form card --}}

            {{-- Submit / Confirm area --}}
            @if(! $isSubmitted)
                @if($showConfirm)
                    {{-- Inline confirmation — same spirit as profile photo action bar --}}
                    <p class="text-center text-xs text-slate-400 dark:text-slate-500">
                        Data tidak dapat diubah setelah disimpan
                    </p>
                    <div class="flex gap-3">
                        <button wire:click="cancelConfirm"
                                class="flex flex-1 items-center justify-center rounded-2xl bg-gray-100 py-4 text-sm font-semibold text-slate-600 transition active:bg-gray-200 dark:bg-gray-800 dark:text-slate-300">
                            Batal
                        </button>
                        <button wire:click="save" wire:loading.attr="disabled"
                                class="{{ $isShift1 ? 'bg-blue-600 active:bg-blue-700' : 'bg-indigo-600 active:bg-indigo-700' }} flex flex-[2] items-center justify-center gap-1.5 rounded-2xl py-4 text-sm font-semibold text-white transition active:scale-95 disabled:opacity-60">
                            <span wire:loading.remove wire:target="save">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                                </svg>
                            </span>
                            <span wire:loading.remove wire:target="save">Ya, Simpan</span>
                            <span wire:loading wire:target="save">Menyimpan...</span>
                        </button>
                    </div>
                @else
                    <button wire:click="requestConfirm" wire:loading.attr="disabled"
                            class="{{ $isShift1 ? 'bg-blue-600 active:bg-blue-700' : 'bg-indigo-600 active:bg-indigo-700' }} w-full rounded-2xl py-4 text-sm font-semibold text-white transition active:scale-95 disabled:opacity-60">
                        <span wire:loading.remove wire:target="requestConfirm">Simpan Shift {{ $shift }}</span>
                        <span wire:loading wire:target="requestConfirm">Memvalidasi...</span>
                    </button>
                @endif
            @endif

        </div>
    </div>

</div>
