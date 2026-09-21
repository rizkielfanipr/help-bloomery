<x-filament-panels::page>
    @php
        $ranking = $this->ranking();
        $totalCredit = $ranking->sum('total_credit');
        $inputClass = 'w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white';
    @endphp
    <div class="space-y-6">
        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <div class="flex flex-col gap-5 p-5 sm:p-6 md:flex-row md:items-center md:justify-between">
                <div class="flex min-w-0 items-start gap-4">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-950/40 dark:text-blue-300">
                        <x-heroicon-o-shopping-cart class="h-6 w-6" />
                    </div>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider text-blue-600 dark:text-blue-400">Finance · Performance</p>
                        <h2 class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">Basket Size</h2>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-gray-500 dark:text-gray-400">Pantau kredit basket size karyawan per shift dan telusuri Sales Report sumbernya dalam satu workspace.</p>
                    </div>
                </div>
                <div class="flex shrink-0 flex-col items-start gap-2 self-start md:items-end">
                    <div class="flex items-center gap-2 rounded-lg border border-blue-100 bg-blue-50 px-3 py-2 text-sm font-semibold text-blue-700 dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-300">
                        <x-heroicon-o-calendar-days class="h-4 w-4" />
                        {{ $dateFrom }} – {{ $dateTo }}
                    </div>
                    @if($this->canRecalculate())
                        @php
                            $recalculableCount = $this->recalculableCount();
                            $recalculateScope = $this->branchId ? ($this->branches()->firstWhere('id', $this->branchId)?->name ?? 'cabang terpilih') : 'semua cabang';
                            $recalculateLimit = \App\Filament\Helpdesk\Pages\BasketSizePage::RECALCULATE_LIMIT;
                            $canRecalculateNow = $recalculableCount > 0 && $recalculableCount <= $recalculateLimit;
                            $confirmText = "Hitung ulang {$recalculableCount} shift ({$dateFrom} – {$dateTo}, {$recalculateScope})? Transaksi ESB ditarik ulang memakai jam shift di master cabang saat ini, lalu basket size dan kredit staff pada periode ini diperbarui. Proses berjalan bertahap, biarkan halaman ini tetap terbuka.";
                        @endphp
                        <div
                            class="flex flex-col items-start gap-2 md:items-end"
                            x-data="{
                                running: false,
                                done() { return $wire.recalculationTotal - $wire.recalculationQueue.length; },
                                async run() {
                                    if (this.running) return;
                                    this.running = true;
                                    try {
                                        await $wire.startRecalculation();
                                        while ($wire.recalculationQueue.length > 0) {
                                            await $wire.processRecalculationBatch();
                                        }
                                    } finally {
                                        this.running = false;
                                    }
                                },
                            }"
                        >
                            <button type="button" x-on:click="if (confirm(@js($confirmText))) run()" x-bind:disabled="running || {{ $canRecalculateNow ? 'false' : 'true' }}"
                                class="inline-flex items-center gap-2 rounded-lg border border-blue-200 bg-white px-3 py-2 text-sm font-semibold text-blue-700 transition hover:bg-blue-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-blue-900 dark:bg-gray-900 dark:text-blue-300 dark:hover:bg-blue-950/40">
                                <x-heroicon-o-arrow-path class="h-4 w-4" x-bind:class="running ? 'animate-spin' : ''" />
                                <span x-show="! running">Hitung Ulang</span>
                                <span x-show="running" x-cloak x-text="$wire.recalculationTotal > 0 ? `Menghitung ulang ${done()} / ${$wire.recalculationTotal}` : 'Menghitung ulang...'"></span>
                            </button>
                            <div x-show="running && $wire.recalculationTotal > 0" x-cloak class="h-1.5 w-full max-w-xs overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700" role="progressbar" aria-label="Progres hitung ulang basket size">
                                <div class="h-1.5 rounded-full bg-blue-600 transition-all" x-bind:style="`width: ${$wire.recalculationTotal > 0 ? Math.round(done() / $wire.recalculationTotal * 100) : 0}%`"></div>
                            </div>
                            <p class="max-w-xs text-xs leading-5 text-gray-500 dark:text-gray-400 md:text-right">
                                @if($recalculableCount === 0)
                                    Tidak ada shift pada filter ini.
                                @elseif($recalculableCount > $recalculateLimit)
                                    {{ $recalculableCount }} shift pada filter ini, maksimal {{ $recalculateLimit }} per proses. Persempit tanggal atau pilih cabang.
                                @else
                                    {{ $recalculableCount }} shift pada filter ini, dihitung bertahap. Gunakan setelah jam shift di master cabang diubah.
                                @endif
                            </p>
                        </div>
                    @endif
                </div>
            </div>
            <div class="flex items-start gap-2 border-t border-gray-200 bg-gray-50/60 px-5 py-3 text-xs leading-5 text-gray-500 dark:border-gray-700 dark:bg-gray-800/30 dark:text-gray-400 sm:px-6"><x-heroicon-o-information-circle class="mt-0.5 h-4 w-4 shrink-0 text-blue-500" /><span>Peringkat mengikuti rata-rata kredit per shift pada periode dan cabang yang dipilih. Pilih karyawan untuk melihat rincian setiap shift.</span></div>
        </section>

        <section class="grid gap-4 rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900 sm:grid-cols-2 sm:p-5 lg:grid-cols-[1fr_1fr_1fr_2fr]">
                <div><label for="basket-date-from" class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Dari Tanggal</label><input id="basket-date-from" wire:model.live="dateFrom" type="date" class="{{ $inputClass }}"></div>
                <div><label for="basket-date-to" class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Sampai Tanggal</label><input id="basket-date-to" wire:model.live="dateTo" type="date" class="{{ $inputClass }}"></div>
                <div><label for="basket-branch" class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Cabang</label><select id="basket-branch" wire:model.live="branchId" class="{{ $inputClass }}"><option value="">Semua Cabang</option>@foreach($this->branches() as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select></div>
                <div class="flex items-center gap-3 rounded-xl bg-gray-50 p-3 text-sm text-gray-500 dark:bg-gray-800/50 dark:text-gray-400 sm:col-span-2 lg:col-span-1"><x-heroicon-o-calculator class="h-5 w-5 shrink-0 text-blue-500" /><div><p class="font-semibold text-gray-700 dark:text-gray-200">Rata-rata kredit per shift</p><p class="mt-1 text-xs leading-5">Total kredit dan jumlah shift tetap tersedia pada tabel peringkat.</p></div></div>
        </section>

        <section class="grid gap-4 lg:grid-cols-[1fr_2fr]">
            <div class="rounded-2xl border border-blue-200 bg-blue-50 p-5 dark:border-blue-900 dark:bg-blue-950/30"><div class="flex items-center gap-2 text-sm font-semibold text-blue-700 dark:text-blue-300"><x-heroicon-o-presentation-chart-line class="h-5 w-5" />Total Kredit</div><p class="mt-3 break-words text-3xl font-bold tracking-tight text-blue-900 dark:text-blue-100 sm:text-4xl">Rp {{ number_format((float) $totalCredit, 0, ',', '.') }}</p><p class="mt-3 text-xs leading-5 text-blue-700 dark:text-blue-300">Akumulasi kredit {{ $ranking->count() }} karyawan pada periode dan cabang yang dipilih.</p></div>
            <div class="grid grid-cols-2 gap-3">
                <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900"><div class="flex items-start justify-between gap-2"><p class="text-xs text-gray-500 dark:text-gray-400">Karyawan Dinilai</p><x-heroicon-o-users class="h-4 w-4 shrink-0 text-gray-400" /></div><p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($ranking->count(), 0, ',', '.') }}</p></div>
                <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900"><div class="flex items-start justify-between gap-2"><p class="text-xs text-gray-500 dark:text-gray-400">Total Shift</p><x-heroicon-o-calendar-days class="h-4 w-4 shrink-0 text-gray-400" /></div><p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($ranking->sum('shift_count'), 0, ',', '.') }}</p></div>
            </div>
        </section>

        <div class="flex items-center justify-between gap-3"><div><h3 class="font-bold text-gray-900 dark:text-white">Peringkat Karyawan</h3><p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Diurutkan berdasarkan rata-rata kredit per shift tertinggi. Buka rincian untuk melihat setiap shift.</p></div><span wire:loading wire:target="dateFrom,dateTo,branchId" role="status" class="shrink-0 text-xs text-blue-600">Memuat peringkat…</span></div>
        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[820px] text-sm">
                    <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                        <tr>
                            <th scope="col" class="px-5 py-3 text-left">Peringkat</th>
                            <th scope="col" class="px-5 py-3 text-left">Karyawan</th>
                            <th scope="col" class="px-5 py-3 text-left">Posisi</th>
                            <th scope="col" class="px-5 py-3 text-right">Jumlah Shift</th>
                            <th scope="col" class="px-5 py-3 text-right">Rata-Rata Kredit</th>
                            <th scope="col" class="px-5 py-3 text-right">Total Kredit</th>
                            <th scope="col" class="px-5 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($ranking as $index => $row)
                            <tr wire:key="basket-employee-{{ $row->employee_id }}" class="hover:bg-gray-50/70 dark:hover:bg-gray-800/40">
                                <td class="px-5 py-4"><span class="inline-flex min-w-9 items-center justify-center rounded-lg bg-blue-50 px-2 py-1 font-bold text-blue-700 dark:bg-blue-950/40 dark:text-blue-300">{{ $index + 1 }}</span></td>
                                <td class="px-5 py-4"><p class="font-bold text-gray-900 dark:text-white">{{ $row->employee_name }}</p><p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $row->employee_code }}</p></td>
                                <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $row->employee_position ?: '—' }}</td>
                                <td class="px-5 py-4 text-right font-semibold tabular-nums text-gray-900 dark:text-white">{{ number_format($row->shift_count, 0, ',', '.') }}</td>
                                <td class="px-5 py-4 text-right font-bold tabular-nums text-blue-700 dark:text-blue-300">Rp {{ number_format((float) $row->average_credit, 0, ',', '.') }}</td>
                                <td class="px-5 py-4 text-right tabular-nums text-gray-600 dark:text-gray-300">Rp {{ number_format((float) $row->total_credit, 0, ',', '.') }}</td>
                                <td class="px-5 py-4 text-right"><a href="{{ \App\Filament\Helpdesk\Pages\BasketSizePage::getUrl(['employee' => $row->employee_id, 'dateFrom' => $dateFrom, 'dateTo' => $dateTo, 'branchId' => $branchId]) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"><x-heroicon-o-calendar-days class="h-4 w-4" />Detail Shift</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-5 py-12 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada catatan Basket Size pada periode dan cabang ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

    </div>

    @if($employee)
        @php($closeUrl = \App\Filament\Helpdesk\Pages\BasketSizePage::getUrl(['dateFrom' => $dateFrom, 'dateTo' => $dateTo, 'branchId' => $branchId]))
        <div id="basket-size-history" x-data x-trap.inert.noscroll="true" x-on:keydown.escape.window="window.location.href = @js($closeUrl)" class="fixed inset-0 z-[130] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-label="Rincian Shift Karyawan">
            <a href="{{ $closeUrl }}" class="absolute inset-0 bg-gray-950/60" aria-label="Tutup rincian shift"></a>
            <x-rnd.picker-modal title="Rincian Shift · {{ $ranking->firstWhere('employee_id', $employee)?->employee_name ?? 'Karyawan' }}" description="Kredit per shift pada periode terpilih beserta Sales Report sumbernya.">
                <x-slot:close><a href="{{ $closeUrl }}" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800" aria-label="Tutup rincian shift"><x-heroicon-o-x-mark class="h-5 w-5" /></a></x-slot:close>
                <div class="max-h-[70vh] space-y-3 overflow-y-auto p-5">
                    @forelse($this->history() as $row)
                        <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div><p class="font-bold text-gray-900 dark:text-white">{{ $row->basketSizeRecord->report_date->format('d M Y') }}</p><p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ $row->basketSizeRecord->branch?->name }} · {{ $row->basketSizeRecord->shift_name }}</p><p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ substr($row->basketSizeRecord->shift_start_time, 0, 5) }}–{{ substr($row->basketSizeRecord->shift_end_time, 0, 5) }}</p></div>
                                <div class="sm:text-right"><p class="text-xs text-gray-500 dark:text-gray-400">Kredit</p><p class="mt-1 text-lg font-bold text-blue-700 dark:text-blue-300">Rp {{ number_format((float) $row->basket_size_credit, 0, ',', '.') }}</p></div>
                            </div>
                            <div class="mt-4 grid grid-cols-2 gap-3 rounded-xl bg-gray-50 p-3 dark:bg-gray-800/40 sm:grid-cols-3"><div><p class="text-xs text-gray-500 dark:text-gray-400">Revenue</p><p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">Rp {{ number_format((float) $row->basketSizeRecord->revenue, 0, ',', '.') }}</p></div><div><p class="text-xs text-gray-500 dark:text-gray-400">Pax</p><p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">{{ number_format($row->basketSizeRecord->total_pax) }}</p></div><div><p class="text-xs text-gray-500 dark:text-gray-400">Basket Size Shift</p><p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">Rp {{ number_format((float) $row->basketSizeRecord->basket_size, 0, ',', '.') }}</p></div></div>
                            <a href="{{ $this->salesReportUrl($row->sales_report_id) }}" class="mt-3 inline-flex items-center gap-1 text-xs font-semibold text-blue-600 hover:underline dark:text-blue-300">Buka Sales Report <x-heroicon-o-arrow-up-right class="h-3.5 w-3.5" /></a>
                        </div>
                    @empty
                        <p class="rounded-xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">Tidak ada riwayat karyawan pada filter ini.</p>
                    @endforelse
                </div>
                <div class="flex justify-end border-t border-gray-200 px-5 py-4 dark:border-gray-700"><a href="{{ $closeUrl }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Tutup</a></div>
            </x-rnd.picker-modal>
        </div>
    @endif
</x-filament-panels::page>
