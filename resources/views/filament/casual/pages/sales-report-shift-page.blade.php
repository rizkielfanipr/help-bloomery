@php
    $labelClass = 'mb-1.5 block text-xs font-semibold text-slate-600 dark:text-slate-400';
@endphp

<div class="flex flex-col bg-blue-600 dark:bg-blue-900" style="min-height:100dvh">

    {{-- HEADER --}}
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
            <span class="text-base font-semibold text-white">Sales Report Shift {{ $shiftNumber }}</span>
        </div>
        <p class="text-blue-200">{{ auth()->user()->branch?->name ?? 'Tanpa Cabang' }}</p>
        <p class="text-xl font-semibold text-white">
            {{ \Carbon\Carbon::parse($reportDate)->locale('id')->isoFormat('dddd, D MMMM Y') }}
        </p>
        <p class="mt-1 text-sm font-medium text-blue-100">{{ $shiftStart }}–{{ $shiftEnd }} · berdasarkan waktu transaksi selesai</p>
    </div>

    {{-- WHITE CONTENT CARD --}}
    <div class="flex-1 overflow-y-auto rounded-t-3xl bg-gray-50 pb-10 pt-6 dark:bg-gray-950">
        <div class="flex flex-col gap-4 px-4">

            {{-- Submitted banner --}}
            @if($isSubmitted)
                <div class="flex items-center gap-3 rounded-2xl border border-green-200 bg-green-50 p-4 dark:border-green-800 dark:bg-green-900/20">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/50">
                        <svg class="h-5 w-5 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-green-700 dark:text-green-400">Data sudah dikunci</p>
                        <p class="text-xs text-green-600 dark:text-green-500">
                            {{ \App\Enums\SalesReportStatus::from($currentStatus)->getLabel() }}. Koreksi selanjutnya dilakukan Supervisor melalui back office.
                        </p>
                    </div>
                </div>
            @endif

            {{-- Tanggal --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <label class="{{ $labelClass }}">Tanggal</label>
                <div class="flex items-center gap-2 rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 dark:border-gray-700 dark:bg-gray-800">
                    <svg class="h-4 w-4 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>
                    </svg>
                    <span class="text-sm text-slate-600 dark:text-slate-300">
                        {{ \Carbon\Carbon::parse($reportDate)->locale('id')->isoFormat('dddd, D MMMM Y') }}
                    </span>
                </div>
            </div>

            {{-- Staff pengisi --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <label for="employeeId" class="{{ $labelClass }}">Staff Pengisi <span class="text-red-500">*</span></label>
                @if($isSubmitted)
                    <div class="rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 dark:border-gray-700 dark:bg-gray-800">
                        <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $employeeName ?? 'Data employee tersimpan' }}</p>
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                            {{ collect([$employeeCode, $employeePosition])->filter()->join(' · ') ?: '-' }}
                        </p>
                    </div>
                @else
                    <select id="employeeId" wire:model="employeeId"
                            class="w-full rounded-xl border bg-gray-50 px-3 py-2.5 text-sm text-slate-700 focus:border-blue-400 focus:outline-none focus:ring-0 dark:bg-gray-800 dark:text-slate-200
                                   {{ $errors->has('employeeId') ? 'border-red-400 dark:border-red-500' : 'border-gray-200 dark:border-gray-700' }}">
                        <option value="">Pilih staff sesuai branch</option>
                        @foreach($this->employees as $employee)
                            <option value="{{ $employee->id }}">{{ $employee->employee_code }} · {{ $employee->name }} · {{ $employee->position }}</option>
                        @endforeach
                    </select>
                    @if($this->employees->isEmpty())
                        <p class="mt-1.5 text-xs text-amber-600">Belum ada employee aktif pada branch ini. Tambahkan melalui Master › Employee.</p>
                    @endif
                    @error('employeeId')
                        <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                @endif
            </div>

            {{-- ESB Fetch card --}}
            @if(! $isSubmitted)
                <div class="rounded-2xl border p-4
                    {{ $esbFetched ? 'border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-900/20' : 'border-blue-100 bg-blue-50 dark:border-blue-900 dark:bg-blue-900/20' }}">
                    <div class="flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold {{ $esbFetched ? 'text-green-800 dark:text-green-300' : 'text-blue-800 dark:text-blue-300' }}">
                                {{ $esbFetched ? 'Data ESB sudah dimuat' : 'Fetch Data dari ESB' }}
                            </p>
                            <p class="mt-0.5 text-xs {{ $esbFetched ? 'text-green-600 dark:text-green-400' : 'text-blue-600 dark:text-blue-400' }}">
                                {{ $esbFetched ? 'Isi kolom Sales Store untuk setiap metode.' : 'Wajib fetch ESB sebelum bisa menyimpan.' }}
                            </p>
                        </div>
                        <button wire:click="fetchFromEsb" wire:loading.attr="disabled"
                                class="flex shrink-0 items-center gap-1.5 rounded-xl px-3.5 py-2.5 text-sm font-semibold text-white transition active:scale-95 disabled:opacity-60
                                       {{ $esbFetched ? 'bg-green-600 active:bg-green-700' : 'bg-blue-600 active:bg-blue-700' }}">
                            <svg wire:loading wire:target="fetchFromEsb" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            <svg wire:loading.remove wire:target="fetchFromEsb" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                            </svg>
                            <span wire:loading.remove wire:target="fetchFromEsb">{{ $esbFetched ? 'Refresh' : 'Fetch ESB' }}</span>
                            <span wire:loading wire:target="fetchFromEsb">Mengambil...</span>
                        </button>
                    </div>
                </div>
            @endif

            {{-- Payment rows --}}
            @if(! empty($rows))
                <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">

                    {{-- Column header --}}
                    <div class="grid grid-cols-[1fr_140px] border-b border-gray-100 bg-gray-50 px-4 py-2 dark:border-gray-800 dark:bg-gray-800/50">
                        <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Payment Method</span>
                        <span class="text-right text-[10px] font-bold uppercase tracking-wide text-slate-400">Sales Store</span>
                    </div>

                    {{-- Rows --}}
                    @foreach($rows as $idx => $row)
                        @php
                            $hasDifference = abs($this->getSelisih($idx)) > 0.009;
                            $hasError = $errors->has("rows.{$idx}.notes");
                        @endphp

                        <div class="border-b border-gray-100 dark:border-gray-800 last:border-0">
                            <div class="grid grid-cols-[1fr_140px] items-center gap-3 px-4 py-3">
                                <div class="min-w-0">
                                    <p class="truncate text-xs font-semibold text-slate-700 dark:text-slate-200">{{ $row['name'] }}</p>
                                    @if($showDiscrepancies && $hasDifference)
                                        <span class="mt-1 inline-flex items-center gap-1 rounded-full bg-red-50 px-2 py-0.5 text-[10px] font-semibold text-red-600 dark:bg-red-900/20 dark:text-red-400">
                                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/>
                                            </svg>
                                            Difference detected
                                        </span>
                                    @endif
                                </div>

                                @if($isSubmitted)
                                    <div class="text-right text-xs font-mono font-semibold text-slate-700 dark:text-slate-200">
                                        {{ number_format((float) ($row['sales_store'] ?? 0), 0, ',', '.') }}
                                    </div>
                                @else
                                    <input type="number"
                                           wire:model.live="rows.{{ $idx }}.sales_store"
                                           min="0" step="1000" placeholder="0"
                                           class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-right text-xs font-mono text-slate-700 placeholder-slate-300 focus:border-blue-400 focus:outline-none focus:ring-0 dark:border-gray-700 dark:bg-gray-800 dark:text-slate-200">
                                @endif
                            </div>

                            {{-- Notes become visible only after backend detects a difference. --}}
                            @if(($showDiscrepancies && $hasDifference) || ! empty($row['notes']))
                                <div class="px-4 pb-3">
                                    @if($isSubmitted)
                                        @if(! empty($row['notes']))
                                            <div class="flex items-start gap-1.5 rounded-lg bg-red-50 px-3 py-2 dark:bg-red-900/20">
                                                <svg class="mt-0.5 h-3.5 w-3.5 shrink-0 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                                                </svg>
                                                <p class="text-xs text-red-600 dark:text-red-400">{{ $row['notes'] }}</p>
                                            </div>
                                        @endif
                                    @else
                                        <div class="flex items-center gap-1.5 mb-1">
                                            <svg class="h-3 w-3 text-red-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/>
                                            </svg>
                                            <span class="text-[10px] font-semibold text-red-500">Notes are required for this payment method</span>
                                        </div>
                                        <input type="text"
                                               wire:model.live="rows.{{ $idx }}.notes"
                                               placeholder="Explain the sales difference..."
                                               class="w-full rounded-lg border bg-gray-50 px-3 py-2 text-xs text-slate-600 placeholder-slate-400 focus:outline-none focus:ring-0 dark:bg-gray-800 dark:text-slate-300
                                                      {{ $hasError ? 'border-red-400 focus:border-red-400 dark:border-red-500' : 'border-gray-200 focus:border-blue-400 dark:border-gray-700' }}">
                                        @error("rows.{$idx}.notes")
                                            <p class="mt-1 text-[10px] text-red-500">{{ $message }}</p>
                                        @enderror
                                    @endif
                                </div>
                            @endif

                        </div>
                    @endforeach

                    {{-- Total row --}}
                    <div class="border-t-2 border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-800/50">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-[10px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Total Sales Store</span>
                            <span class="text-sm font-mono font-bold text-blue-600 dark:text-blue-400">
                                Rp {{ number_format($this->totalStore(), 0, ',', '.') }}
                            </span>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Submit / Confirm area --}}
            @if(! $isSubmitted)
                @if($showConfirm)
                    <p class="text-center text-xs text-slate-400 dark:text-slate-500">
                        Data dikunci selama proses approval
                    </p>
                    <div class="flex gap-3">
                        <button wire:click="cancelConfirm"
                                class="flex flex-1 items-center justify-center rounded-2xl bg-gray-100 py-4 text-sm font-semibold text-slate-600 transition active:bg-gray-200 dark:bg-gray-800 dark:text-slate-300">
                            Batal
                        </button>
                        <button wire:click="save" wire:loading.attr="disabled"
                                class="flex flex-[2] items-center justify-center gap-1.5 rounded-2xl bg-blue-600 py-4 text-sm font-semibold text-white transition active:scale-95 active:bg-blue-700 disabled:opacity-60">
                            <svg wire:loading.remove wire:target="save" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                            </svg>
                            <span wire:loading.remove wire:target="save">Ya, Submit</span>
                            <span wire:loading wire:target="save">Menyimpan...</span>
                        </button>
                    </div>
                @else
                    <button wire:click="requestConfirm" wire:loading.attr="disabled"
                            class="w-full rounded-2xl py-4 text-sm font-semibold text-white transition active:scale-95 disabled:opacity-60
                                   {{ $esbFetched ? 'bg-blue-600 active:bg-blue-700' : 'cursor-not-allowed bg-slate-400' }}">
                        <span wire:loading.remove wire:target="requestConfirm">
                            {{ $esbFetched ? ($rejectionReason ? 'Submit Ulang Laporan' : 'Submit Laporan') : 'Fetch ESB Dulu Sebelum Submit' }}
                        </span>
                        <span wire:loading wire:target="requestConfirm">Memvalidasi...</span>
                    </button>
                @endif
            @endif

        </div>
    </div>

</div>
