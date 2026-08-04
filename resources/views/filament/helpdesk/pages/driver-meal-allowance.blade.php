<x-filament-panels::page>
    @php($period = $this->selectedPeriod)
    <div class="grid gap-5 xl:grid-cols-[300px_minmax(0,1fr)]">
        <aside class="rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
            @can('create driver meal allowance periods')
                <form wire:submit="createPeriod" class="mb-5 space-y-3 border-b border-gray-200 pb-5 dark:border-white/10">
                    <h2 class="font-semibold text-gray-950 dark:text-white">Buat Periode</h2>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="space-y-1.5">
                            <span class="block text-xs font-medium text-gray-700 dark:text-gray-300">Bulan</span>
                            <select wire:model="reportMonth" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm leading-5 shadow-none focus:border-primary-500 focus:ring-primary-500 dark:border-white/20 dark:bg-gray-900">
                                @foreach(range(1, 12) as $month)
                                    <option value="{{ $month }}">{{ \Carbon\Carbon::create(null, $month)->translatedFormat('F') }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="space-y-1.5">
                            <span class="block text-xs font-medium text-gray-700 dark:text-gray-300">Tahun</span>
                            <input type="number" wire:model="reportYear" min="2020" max="2100" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm leading-5 shadow-none focus:border-primary-500 focus:ring-primary-500 dark:border-white/20 dark:bg-gray-900">
                        </label>
                    </div>
                    <x-filament::button type="submit" class="w-full">Siapkan Periode</x-filament::button>
                </form>
            @endcan

            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500">Riwayat Periode</h2>
            <div class="space-y-2">
                @forelse($this->periods as $item)
                    <button wire:click="selectPeriod({{ $item->id }})" class="w-full rounded-lg border p-3 text-left transition {{ $selectedPeriodId === $item->id ? 'border-primary-500 bg-primary-50 dark:bg-primary-950/30' : 'border-gray-200 hover:border-gray-300 dark:border-white/10' }}">
                        <span class="block font-medium text-gray-950 dark:text-white">{{ \Carbon\Carbon::create($item->report_year, $item->report_month)->translatedFormat('F Y') }}</span>
                        <span class="mt-1 block text-xs text-gray-500">{{ $item->start_date->format('d M Y') }} – {{ $item->end_date->format('d M Y') }}</span>
                    </button>
                @empty
                    <p class="text-sm text-gray-500">Belum ada periode.</p>
                @endforelse
            </div>
        </aside>

        <section class="min-w-0">
            @if($period)
                <div class="rounded-xl border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900">
                    <header class="flex flex-col gap-4 border-b border-gray-200 p-5 dark:border-white/10 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <div class="flex items-center gap-2">
                                <h2 class="text-xl font-semibold text-gray-950 dark:text-white">{{ \Carbon\Carbon::create($period->report_year, $period->report_month)->translatedFormat('F Y') }}</h2>
                                @if($period->is_demo)<span class="rounded-full bg-violet-50 px-2.5 py-1 text-xs font-medium text-violet-700">Demo</span>@endif
                                <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $period->isOpen() ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700' }}">{{ $period->isOpen() ? 'Open' : 'Finalized' }}</span>
                            </div>
                            <p class="mt-1 text-sm text-gray-500">Cutoff {{ $period->start_date->format('d M Y') }} – {{ $period->end_date->format('d M Y') }}</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @can('export driver meal allowance periods')
                                <x-filament::button tag="a" color="gray" icon="heroicon-o-arrow-down-tray" href="{{ route('helpdesk.exports.driver-meal-allowance', ['period' => $period, 'type' => 'summary']) }}">Summary</x-filament::button>
                                <x-filament::button tag="a" color="gray" icon="heroicon-o-document-text" href="{{ route('helpdesk.exports.driver-meal-allowance', ['period' => $period, 'type' => 'detail']) }}">Detail</x-filament::button>
                            @endcan
                            @if($period->isOpen())
                                @can('edit driver meal allowance periods')
                                    <x-filament::button wire:click="syncPeriod" color="gray" icon="heroicon-o-arrow-path">Refresh Trip</x-filament::button>
                                @endcan
                                @can('finalize driver meal allowance periods')
                                    <x-filament::button wire:click="finalizePeriod" wire:confirm="Finalisasi periode ini? Data akan dikunci." icon="heroicon-o-lock-closed">Finalize</x-filament::button>
                                @endcan
                            @endif
                        </div>
                    </header>

                    <div class="grid gap-px border-b border-gray-200 bg-gray-200 sm:grid-cols-4 dark:border-white/10 dark:bg-white/10">
                        @foreach([['Driver', $period->driver_count], ['Trip', $period->trip_count], ['Total', 'Rp '.number_format($period->total_amount, 0, ',', '.')], ['Trip Terlambat', $this->lateTripCount()]] as [$label, $value])
                            <div class="bg-white p-4 dark:bg-gray-900"><p class="text-xs uppercase text-gray-500">{{ $label }}</p><p class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">{{ $value }}</p></div>
                        @endforeach
                    </div>

                    <div class="divide-y divide-gray-200 dark:divide-white/10">
                        @forelse($period->summaries as $summary)
                            <div x-data="{ open: false }" class="p-5">
                                <div class="grid items-center gap-4 lg:grid-cols-[minmax(180px,1.2fr)_100px_150px_150px_auto]">
                                    <div><p class="font-semibold text-gray-950 dark:text-white">{{ $summary->driver->name }}</p><p class="text-xs text-gray-500">{{ $summary->driver->username }} · {{ $summary->trip_count }} trip</p></div>
                                    <div><p class="text-xs text-gray-500">Dasar</p><p class="font-medium">Rp {{ number_format($summary->base_amount, 0, ',', '.') }}</p></div>
                                    <div><p class="text-xs text-gray-500">Penyesuaian</p><p class="font-medium {{ $summary->adjustment_amount < 0 ? 'text-red-600' : '' }}">Rp {{ number_format($summary->adjustment_amount, 0, ',', '.') }}</p></div>
                                    <div><p class="text-xs text-gray-500">Total</p><p class="font-semibold text-primary-600">Rp {{ number_format($summary->final_amount, 0, ',', '.') }}</p></div>
                                    <button type="button" x-on:click="open = ! open" class="rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-white/10"><span x-text="open ? 'Tutup' : 'Detail'"></span></button>
                                </div>

                                <div x-show="open" x-collapse class="mt-5 space-y-4 border-t border-gray-100 pt-4 dark:border-white/10">
                                    @if($period->isOpen() && auth()->user()->can('edit driver meal allowance periods'))
                                        <form wire:submit="saveAdjustment({{ $summary->id }})" class="grid items-end gap-4 rounded-lg border border-gray-200 p-5 md:grid-cols-[200px_1fr_auto] dark:border-white/10">
                                            <label class="space-y-1.5">
                                                <span class="block text-xs font-medium text-gray-700 dark:text-gray-300">Nominal Penyesuaian</span>
                                                <input type="number" step="1" wire:model="adjustments.{{ $summary->id }}" placeholder="Contoh: -5000" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm leading-5 shadow-none focus:border-primary-500 focus:ring-primary-500 dark:border-white/20 dark:bg-gray-900">
                                            </label>
                                            <label class="space-y-1.5">
                                                <span class="block text-xs font-medium text-gray-700 dark:text-gray-300">Alasan Penyesuaian</span>
                                                <input type="text" wire:model="adjustmentReasons.{{ $summary->id }}" placeholder="Jelaskan alasan penyesuaian nominal" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm leading-5 shadow-none focus:border-primary-500 focus:ring-primary-500 dark:border-white/20 dark:bg-gray-900">
                                            </label>
                                            <x-filament::button type="submit" size="sm" class="mb-0.5">Simpan</x-filament::button>
                                        </form>
                                    @endif

                                    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
                                        <table class="w-full text-sm">
                                            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 dark:bg-white/5"><tr><th class="p-3">Tanggal</th><th class="p-3">Kode / Rute</th><th class="p-3">Nominal</th><th class="p-3">Dihitung</th><th class="p-3">Alasan</th><th class="p-3"></th></tr></thead>
                                            <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                                                @foreach($this->dailyRows($summary) as $dailyRow)
                                                    @php($tripItem = $dailyRow['item'])
                                                    <tr>
                                                        <td class="p-3 whitespace-nowrap">
                                                            <span class="block font-medium text-gray-900 dark:text-white">{{ $dailyRow['date']->translatedFormat('d M Y') }}</span>
                                                            <span class="text-xs text-gray-500">{{ $dailyRow['date']->translatedFormat('l') }}</span>
                                                        </td>
                                                        @if($tripItem)
                                                            <td class="p-3"><span class="block font-medium">{{ $tripItem->trip_code }}</span><span class="text-xs text-gray-500">{{ $tripItem->route_name ?: '-' }}</span></td>
                                                            <td class="p-3"><input type="number" aria-label="Nominal uang makan {{ $tripItem->trip_code }}" wire:model="itemAmounts.{{ $tripItem->id }}" @disabled(!$period->isOpen()) class="w-36 rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm leading-5 shadow-none focus:border-primary-500 focus:ring-primary-500 disabled:bg-gray-50 disabled:text-gray-500 dark:border-white/20 dark:bg-gray-900"></td>
                                                            <td class="p-3"><input type="checkbox" aria-label="Hitung trip {{ $tripItem->trip_code }}" wire:model="itemIncluded.{{ $tripItem->id }}" @disabled(!$period->isOpen()) class="h-4 w-4 rounded border border-gray-300 text-primary-600 dark:border-white/20"></td>
                                                            <td class="p-3"><input type="text" aria-label="Alasan pengecualian {{ $tripItem->trip_code }}" wire:model="itemReasons.{{ $tripItem->id }}" @disabled(!$period->isOpen()) placeholder="Alasan jika tidak dihitung" class="min-w-56 rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm leading-5 shadow-none focus:border-primary-500 focus:ring-primary-500 disabled:bg-gray-50 disabled:text-gray-500 dark:border-white/20 dark:bg-gray-900"></td>
                                                            <td class="p-3">@if($period->isOpen() && auth()->user()->can('edit driver meal allowance periods'))<button wire:click="saveItem({{ $tripItem->id }})" class="text-sm font-medium text-primary-600">Simpan</button>@endif</td>
                                                        @else
                                                            <td class="p-3 text-sm text-gray-400">Tidak ada perjalanan</td>
                                                            <td class="p-3"><div class="w-36 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm text-gray-500 dark:border-white/10 dark:bg-white/5">Rp 0</div></td>
                                                            <td class="p-3 text-gray-300">—</td>
                                                            <td class="p-3 text-sm text-gray-400">—</td>
                                                            <td class="p-3"></td>
                                                        @endif
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="p-10 text-center text-gray-500">Belum ada trip completed pada periode ini.</div>
                        @endforelse
                    </div>

                    @if(!$period->isOpen() && auth()->user()->can('reopen driver meal allowance periods'))
                        <form wire:submit="reopenPeriod" class="flex flex-col items-end gap-3 border-t border-gray-200 p-5 sm:flex-row dark:border-white/10">
                            <label class="min-w-0 flex-1 space-y-1.5">
                                <span class="block text-xs font-medium text-gray-700 dark:text-gray-300">Alasan Membuka Kembali</span>
                                <input wire:model="reopenReason" placeholder="Jelaskan alasan periode perlu dibuka kembali" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm leading-5 shadow-none focus:border-primary-500 focus:ring-primary-500 dark:border-white/20 dark:bg-gray-900">
                            </label>
                            <x-filament::button type="submit" color="warning" icon="heroicon-o-lock-open" class="mb-0.5">Reopen</x-filament::button>
                        </form>
                    @endif
                </div>
            @else
                <div class="rounded-xl border border-dashed border-gray-300 p-12 text-center text-gray-500">Buat periode pertama untuk mulai menghitung uang makan driver.</div>
            @endif
        </section>
    </div>
</x-filament-panels::page>
