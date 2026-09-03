@php
    $fieldClass = 'w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm text-slate-700 placeholder-slate-300 focus:border-blue-400 focus:outline-none focus:ring-0 dark:border-gray-700 dark:bg-gray-900 dark:text-slate-200';
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
    </div>

    {{-- WHITE CONTENT CARD --}}
    <div class="flex-1 rounded-t-3xl bg-gray-50 pt-6 dark:bg-gray-950">
        <div class="flex flex-col gap-4 px-4 pb-6">

            {{-- Submitted banner --}}
            @if($isSubmitted)
                <div class="flex items-center gap-3 rounded-2xl border border-green-200 bg-green-50 p-4 dark:border-green-800 dark:bg-green-900/20">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/50">
                        <svg class="h-5 w-5 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-green-700 dark:text-green-400">Shift {{ $shiftNumber }} sudah dikirim</p>
                        <p class="text-xs text-green-600 dark:text-green-500">
                            {{ \App\Enums\SalesReportStatus::from($currentStatus)->getLabel() }}. Input hanya dapat dilakukan satu kali; koreksi selanjutnya dilakukan Supervisor melalui back office.
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
                <label class="{{ $labelClass }}">Staff In Charge <span class="text-red-400">*</span></label>
                @if($isSubmitted)
                    <div class="space-y-2">
                        @forelse($submittedEmployees as $emp)
                            <div class="rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 dark:border-gray-700 dark:bg-gray-800">
                                <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $emp['name'] ?? 'Data employee tersimpan' }}</p>
                                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                    {{ collect([$emp['code'] ?? null, $emp['position'] ?? null])->filter()->join(' · ') ?: '-' }}
                                </p>
                            </div>
                        @empty
                            <p class="text-sm text-slate-400">-</p>
                        @endforelse
                    </div>
                @else
                    <div class="space-y-2">
                        @foreach($this->employees as $employee)
                            @php
                                $isChecked = in_array($employee->id, $employeeIds);
                                $borderColor = $isChecked ? '#60a5fa' : '#e5e7eb';
                            @endphp
                            <label style="-webkit-tap-highlight-color: transparent; outline: none !important; box-shadow: none !important; border-color: {{ $borderColor }} !important;"
                                   class="flex cursor-pointer items-center gap-3 rounded-xl border px-3 py-2.5 transition
                                          {{ $isChecked ? 'bg-blue-50 dark:bg-blue-950/30' : 'bg-gray-50 dark:bg-gray-800' }}">
                                <input type="checkbox" wire:model="employeeIds" value="{{ $employee->id }}"
                                       style="outline: none !important; box-shadow: none !important;"
                                       class="h-4 w-4 shrink-0 rounded border-gray-300 text-blue-600 dark:border-gray-600">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $employee->name }}</p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ $employee->employee_code }} · {{ $employee->position }}</p>
                                </div>
                            </label>
                        @endforeach
                    </div>
                    @if($this->employees->isEmpty())
                        <p class="mt-1.5 text-xs text-amber-600">Belum ada employee aktif pada branch ini. Tambahkan melalui Master › Employee.</p>
                    @endif
                    @error('employeeIds')
                        <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                    @error('employeeIds.*')
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
                                {{ $esbFetched ? 'Daftar payment method sudah dimuat' : 'Muat Daftar Payment Method' }}
                            </p>
                            <p class="mt-0.5 text-xs {{ $esbFetched ? 'text-green-600 dark:text-green-400' : 'text-blue-600 dark:text-blue-400' }}">
                                {{ $esbFetched ? 'Isi kolom Sales Store untuk setiap metode.' : 'Wajib dimuat sebelum bisa menyimpan.' }}
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
                            <span wire:loading.remove wire:target="fetchFromEsb">{{ $esbFetched ? 'Refresh' : 'Muat Daftar' }}</span>
                            <span wire:loading wire:target="fetchFromEsb">Mengambil...</span>
                        </button>
                    </div>
                </div>
            @endif

            {{-- Payment rows, grouped by Dine In / Takeaway. Always show both,
                 even when one has no data, so staff sees why it's missing
                 rather than the section silently disappearing. --}}
            @if($esbFetched || $isSubmitted)
                @php
                    // preserveKeys=true is required here: groupBy() re-indexes each
                    // group from 0 by default, which would make DINE IN's first row
                    // and TAKEAWAY's first row both resolve to rows.0 and collide on
                    // the same wire:model path below.
                    $groupedRows = collect($rows)->groupBy('label', true);
                    $emptyLabelInfo = [
                        'not_configured' => ['icon' => 'heroicon-o-link-slash', 'message' => 'Cabang belum memiliki konfigurasi ESB untuk kategori ini.'],
                        'failed' => ['icon' => 'heroicon-o-exclamation-triangle', 'message' => 'Gagal memuat data ESB untuk kategori ini. Coba tekan Refresh.'],
                        'no_transactions' => ['icon' => 'heroicon-o-inbox', 'message' => 'Tidak ada transaksi untuk kategori ini pada tanggal ini.'],
                    ];
                @endphp

                @foreach(['DINE IN', 'TAKEAWAY'] as $label)
                    @php $group = $groupedRows->get($label, collect()); @endphp
                    <div wire:key="group-{{ $label }}" class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">

                        {{-- Group header --}}
                        <div class="flex items-center justify-between border-b border-gray-100 bg-gray-50 px-4 py-2 dark:border-gray-800 dark:bg-gray-800/50">
                            <span class="text-xs font-bold uppercase tracking-wide text-blue-600 dark:text-blue-400">{{ $label }}</span>
                            <span class="text-right text-[10px] font-bold uppercase tracking-wide text-slate-400">Sales Store</span>
                        </div>

                        @if($group->isEmpty())
                            {{-- Empty state — icon + explanation, instead of just vanishing --}}
                            @php $info = $emptyLabelInfo[$labelStatus[$label] ?? 'no_transactions'] ?? $emptyLabelInfo['no_transactions']; @endphp
                            <div class="flex flex-col items-center gap-3 px-6 pb-11 pt-8 text-center">
                                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-50 dark:bg-slate-800">
                                    <x-dynamic-component :component="$info['icon']" class="h-5 w-5 text-slate-300 dark:text-slate-500" />
                                </div>
                                <span class="max-w-[260px] text-xs leading-relaxed text-slate-400 dark:text-slate-500">{{ $info['message'] }}</span>
                            </div>
                        @else
                            {{-- Rows --}}
                            @foreach($group as $idx => $row)
                                <div wire:key="row-{{ $idx }}" class="border-b border-gray-100 px-4 py-3 last:border-0 dark:border-gray-800">
                                    <div class="grid grid-cols-[1fr_140px] items-center gap-3">
                                        <p class="truncate text-xs font-semibold text-slate-700 dark:text-slate-200">{{ $row['name'] }}</p>

                                        @if($isSubmitted)
                                            <div class="text-right text-xs font-mono font-semibold text-slate-700 dark:text-slate-200">
                                                {{ number_format((float) ($row['sales_store'] ?? 0), 0, ',', '.') }}
                                            </div>
                                        @else
                                            <input type="number"
                                                   inputmode="decimal"
                                                   wire:model.live="rows.{{ $idx }}.sales_store"
                                                   min="0" step="1000" placeholder="0"
                                                   class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-3 text-right text-base font-mono text-slate-700 placeholder-slate-300 focus:border-blue-400 focus:outline-none focus:ring-0 dark:border-gray-700 dark:bg-gray-800 dark:text-slate-200">
                                        @endif
                                    </div>

                                    @if($isSubmitted)
                                        @if(! empty($row['notes']))
                                            <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ $row['notes'] }}</p>
                                        @endif
                                    @else
                                        <input type="text"
                                               wire:model.live="rows.{{ $idx }}.notes"
                                               placeholder="Catatan (opsional)"
                                               class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-base text-slate-600 placeholder-slate-400 focus:border-blue-400 focus:outline-none focus:ring-0 dark:border-gray-700 dark:bg-gray-800 dark:text-slate-300">
                                    @endif
                                </div>
                            @endforeach

                            {{-- Subtotal row --}}
                            <div class="border-t-2 border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-800/50">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="text-[10px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Total {{ $label }}</span>
                                    <span class="text-sm font-mono font-bold text-blue-600 dark:text-blue-400">
                                        Rp {{ number_format($this->totalStoreForLabel($label), 0, ',', '.') }}
                                    </span>
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach

                {{-- Grand total --}}
                <div class="flex items-center justify-between gap-3 rounded-2xl border border-gray-200 bg-white px-4 py-3 dark:border-gray-700 dark:bg-gray-900">
                    <span class="text-[10px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Total Semua</span>
                    <span class="text-sm font-mono font-bold text-blue-600 dark:text-blue-400">
                        Rp {{ number_format($this->totalStore(), 0, ',', '.') }}
                    </span>
                </div>
            @endif

            {{-- Compliments per shift --}}
            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <div class="flex items-center justify-between border-b border-gray-100 pb-3 dark:border-gray-800">
                    <div>
                        <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">Compliment Shift</p>
                        <p class="mt-0.5 text-xs text-slate-400">Nota dan keterangan compliment pada shift ini.</p>
                    </div>
                    @if(! $isSubmitted)
                        <button type="button" wire:click="addCompliment"
                                class="flex items-center gap-1.5 rounded-xl border border-blue-200 bg-blue-50 px-3.5 py-2 text-xs font-semibold text-blue-600 transition active:scale-95 hover:bg-blue-100 dark:border-blue-900 dark:bg-blue-950/50 dark:text-blue-300">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                            </svg>
                            Tambah Compliment
                        </button>
                    @endif
                </div>

                @if(! $isSubmitted && count($compliments) > 0)
                    {{-- ERP Style Information Box --}}
                    <div class="mt-4 rounded-xl border border-blue-100 bg-blue-50 px-4 py-3 dark:border-blue-900 dark:bg-blue-950/30">
                        <div class="flex items-start gap-2">
                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z"/>
                            </svg>
                            <div class="text-xs leading-relaxed text-blue-700 dark:text-blue-300">
                                <p class="font-semibold">Panduan Pengisian Compliment:</p>
                                <p class="mt-0.5">Pilih jenis compliment, upload foto atau PDF nota transaksi/POS, dan berikan keterangan detail mengenai alasan atau pihak penerima compliment.</p>
                            </div>
                        </div>
                    </div>
                @endif

                @if($isSubmitted)
                    <div class="mt-4 flex flex-col gap-3">
                        @forelse($submittedCompliments as $compliment)
                            <article class="rounded-xl border border-gray-200 bg-gray-50/50 p-4 dark:border-gray-800 dark:bg-gray-800/40">
                                <div class="flex items-start justify-between gap-3">
                                    <span class="inline-flex items-center rounded-lg bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-600 dark:bg-blue-900/30 dark:text-blue-300">
                                        {{ $compliment['type'] }}
                                    </span>
                                </div>
                                <p class="mt-2.5 whitespace-pre-line text-sm leading-relaxed text-slate-700 dark:text-slate-200">{{ $compliment['notes'] }}</p>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    @foreach($compliment['attachments'] ?? [] as $attachment)
                                        <a href="{{ \Illuminate\Support\Facades\Storage::disk('b2')->temporaryUrl($attachment, now()->addHour()) }}"
                                           target="_blank"
                                           class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-blue-600 transition hover:bg-blue-50 dark:border-gray-700 dark:bg-gray-800 dark:text-blue-300">
                                            <svg class="h-4 w-4 shrink-0 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941-7.81 7.81a1.5 1.5 0 002.112 2.13"/>
                                            </svg>
                                            <span class="truncate max-w-[200px]">{{ basename($attachment) }}</span>
                                        </a>
                                    @endforeach
                                </div>
                            </article>
                        @empty
                            <p class="py-6 text-center text-xs text-slate-400">Tidak ada compliment yang dilaporkan pada shift ini.</p>
                        @endforelse
                    </div>
                @else
                    <div class="mt-4 flex flex-col gap-4">
                        @forelse($compliments as $index => $compliment)
                            <div wire:key="compliment-{{ $index }}" class="flex flex-col gap-4 rounded-2xl border border-gray-200 bg-gray-50/50 p-4 dark:border-gray-700 dark:bg-gray-800/40">
                                <div class="flex items-center justify-between border-b border-gray-100 pb-3 dark:border-gray-800">
                                    <div class="flex items-center gap-2">
                                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-blue-100 text-xs font-bold text-blue-600 dark:bg-blue-900/50 dark:text-blue-300">
                                            {{ $index + 1 }}
                                        </span>
                                        <span class="text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-200">
                                            Compliment #{{ $index + 1 }}
                                        </span>
                                    </div>
                                    <button type="button" wire:click="removeCompliment({{ $index }})"
                                            class="flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-semibold text-red-500 transition hover:bg-red-50 dark:hover:bg-red-950/30">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                                        </svg>
                                        Hapus
                                    </button>
                                </div>

                                {{-- Jenis Compliment Dropdown (ERP style with chevron) --}}
                                <div>
                                    <label for="compliment_type_{{ $index }}" class="{{ $labelClass }}">
                                        Jenis Compliment <span class="text-red-400">*</span>
                                    </label>
                                    <div class="relative">
                                        <select id="compliment_type_{{ $index }}" wire:model="compliments.{{ $index }}.compliment_type_id"
                                                class="{{ $fieldClass }} appearance-none pr-10">
                                            <option value="">-- Pilih jenis compliment --</option>
                                            @foreach($this->complimentTypes as $type)
                                                <option value="{{ $type->id }}">{{ $type->name }}</option>
                                            @endforeach
                                        </select>
                                        <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center">
                                            <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m19 9-7 7-7-7"/>
                                            </svg>
                                        </div>
                                    </div>
                                    @error("compliments.{$index}.compliment_type_id")
                                        <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>
                                    @enderror
                                </div>

                                {{-- Keterangan Permintaan/Compliment --}}
                                <div>
                                    <label for="compliment_notes_{{ $index }}" class="{{ $labelClass }}">
                                        Keterangan Compliment <span class="text-red-400">*</span>
                                    </label>
                                    <textarea id="compliment_notes_{{ $index }}" wire:model="compliments.{{ $index }}.notes" rows="4" maxlength="2000"
                                              placeholder="Deskripsikan detail compliment (alasan compliment, nama tamu/keperluan, menu yang diberikan, dll)..."
                                              class="{{ $fieldClass }} resize-none leading-relaxed"></textarea>
                                    @error("compliments.{$index}.notes")
                                        <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>
                                    @enderror
                                </div>

                                {{-- Lampiran Nota (Styled exactly like ERP file upload list & trigger) --}}
                                <div>
                                    <label class="{{ $labelClass }}">
                                        Lampiran Nota <span class="text-red-400">*</span> <span class="ml-1 font-normal normal-case text-slate-400">(foto/PDF · maks. 5 MB)</span>
                                    </label>

                                    @if(! empty($compliment['attachments']))
                                        <div class="mb-2 space-y-2">
                                            @foreach($compliment['attachments'] as $attIdx => $file)
                                                <div class="flex items-center justify-between gap-2 rounded-xl border border-gray-200 bg-white px-3 py-2 dark:border-gray-700 dark:bg-gray-800">
                                                    <div class="flex min-w-0 items-center gap-2">
                                                        <svg class="h-4 w-4 shrink-0 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941-7.81 7.81a1.5 1.5 0 002.112 2.13"/>
                                                        </svg>
                                                        <span class="truncate text-xs text-slate-600 dark:text-slate-300">
                                                            {{ is_object($file) && method_exists($file, 'getClientOriginalName') ? $file->getClientOriginalName() : (is_string($file) ? basename($file) : 'Lampiran Nota '.($attIdx + 1)) }}
                                                        </span>
                                                    </div>
                                                    <button type="button" wire:click="removeComplimentAttachment({{ $index }}, {{ $attIdx }})"
                                                            class="shrink-0 text-red-400 transition hover:text-red-600">
                                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                                                        </svg>
                                                    </button>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif

                                    <label class="flex cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-gray-300 bg-white py-3.5 transition hover:border-blue-300 hover:bg-blue-50 dark:border-gray-700 dark:bg-gray-800/50 dark:hover:border-blue-600 dark:hover:bg-blue-900/20">
                                        <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/>
                                        </svg>
                                        <span class="text-sm text-gray-400">Tambah Foto / PDF Nota</span>
                                        <input type="file" wire:model="compliments.{{ $index }}.attachments" multiple accept="image/jpeg,image/png,image/webp,application/pdf" class="hidden">
                                    </label>
                                    <div wire:loading wire:target="compliments.{{ $index }}.attachments" class="mt-1.5 flex items-center gap-2 text-xs text-blue-500">
                                        <svg class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                        </svg>
                                        <span>Mengunggah file lampiran nota…</span>
                                    </div>
                                    @error("compliments.{$index}.attachments") <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p> @enderror
                                    @error("compliments.{$index}.attachments.*") <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        @empty
                            <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-gray-200 bg-gray-50/50 py-8 text-center dark:border-gray-800 dark:bg-gray-800/20">
                                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-blue-50 text-blue-500 dark:bg-blue-900/30">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z"/>
                                    </svg>
                                </div>
                                <p class="mt-2 text-xs font-medium text-slate-600 dark:text-slate-300">Belum ada compliment yang ditambahkan</p>
                                <p class="mt-0.5 text-[11px] text-slate-400">Klik tombol "+ Tambah Compliment" jika terdapat compliment pada shift ini.</p>
                            </div>
                        @endforelse
                    </div>
                @endif
            </section>

        </div>

        {{-- Submit / Confirm area --}}
        @if(! $isSubmitted)
            <div class="sticky bottom-0 border-t border-gray-100 bg-gray-50 px-4 pb-[max(1rem,env(safe-area-inset-bottom))] pt-3 dark:border-gray-800 dark:bg-gray-950">
                @if($showConfirm)
                    <p class="mb-3 text-center text-xs text-slate-400 dark:text-slate-500">
                        Input hanya bisa dilakukan sekali. Pastikan data sudah benar sebelum mengirim.
                    </p>
                    <div class="flex gap-3">
                        <button wire:click="cancelConfirm"
                                class="flex flex-1 items-center justify-center rounded-2xl bg-gray-100 py-3.5 text-sm font-semibold text-slate-600 transition active:scale-95 active:bg-gray-200 dark:bg-gray-800 dark:text-slate-300">
                            Batal
                        </button>
                        <button wire:click="save" wire:loading.attr="disabled"
                                class="flex flex-[2] items-center justify-center gap-1.5 rounded-2xl bg-blue-600 py-3.5 text-sm font-semibold text-white transition active:scale-95 active:bg-blue-700 disabled:opacity-60">
                            <svg wire:loading.remove wire:target="save" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                            </svg>
                            <span wire:loading.remove wire:target="save">Ya, Kirim Laporan</span>
                            <span wire:loading wire:target="save">Menyimpan...</span>
                        </button>
                    </div>
                @else
                    <button wire:click="requestConfirm" wire:loading.attr="disabled"
                            class="w-full rounded-2xl py-3.5 text-sm font-semibold text-white transition active:scale-95 disabled:opacity-60
                                   {{ $esbFetched ? 'bg-blue-600 active:bg-blue-700' : 'cursor-not-allowed bg-slate-400' }}">
                        <span wire:loading.remove wire:target="requestConfirm">
                            {{ $esbFetched ? 'Submit Laporan Shift '.$shiftNumber : 'Muat Daftar Payment Method Dulu' }}
                        </span>
                        <span wire:loading wire:target="requestConfirm">Memvalidasi...</span>
                    </button>
                @endif
            </div>
        @endif
    </div>

</div>
