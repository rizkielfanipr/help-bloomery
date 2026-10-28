{{-- Formula of one recommendation, step by step, from stored components only. --}}
@php
    $run = $item->run;
    $weekdays = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];
    $methods = [
        'weekday_weighted' => 'Weekday weighted average',
        'seasonal_naive' => 'Same-weekday seasonal naive',
        'rolling_weekday_factor' => 'Rolling average × weekday factor',
        'weekday_median' => 'Median weekday',
    ];
    $backtest = $item->backtest ?? [];
    $confidenceTone = [
        'high' => 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-300',
        'medium' => 'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-900 dark:bg-blue-950/30 dark:text-blue-300',
        'low' => 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-300',
        'blocked' => 'border-red-200 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-300',
    ];
    $stockUnsafe = $item->available_stock !== null && (float) $item->available_stock <= 0;
    $componentRows = [
        ['Demand historis', $fmt($item->historical_demand).' '.$item->uom, 'Rata-rata '.$fmt($item->average_daily_demand).' '.$item->uom.'/hari'],
        ['Trend factor', ($item->trend_raw !== null ? $fmt($item->trend_raw, 3) : 'n/a').' → '.$fmt($item->trend_final, 3), $item->trend_label->getLabel()],
        ['Forecast demand', $fmt($item->forecast_demand).' '.$item->uom, $run->horizon_days.' hari'],
        ['Waste rate', $item->waste_rate_raw === null ? 'Tidak tersedia' : $fmt((float) $item->waste_rate_raw * 100, 2).'% → '.$fmt((float) $item->waste_rate_capped * 100, 2).'%', $item->expected_waste === null ? 'Expected waste tidak dihitung' : 'Expected waste '.$fmt($item->expected_waste).' '.$item->uom],
        ['Safety buffer', $fmt((float) $item->buffer_percent * 100, 1).'%', $fmt($item->safety_buffer).' '.$item->uom],
        ['Available stock', $item->available_stock === null ? 'Tidak tersedia' : $fmt($item->available_stock).' '.$item->uom, $item->stock_observed_at ? 'Per '.$item->stock_observed_at->timezone(config('app.timezone'))->isoFormat('D MMM Y, HH:mm') : 'Waktu observasi tidak tersedia'],
        ['Confirmed inbound', $item->confirmed_inbound === null ? 'Inbound belum diperhitungkan' : $fmt($item->confirmed_inbound).' '.$item->uom, 'Belum ada sumber inbound terkonfirmasi'],
    ];
@endphp

<div data-testid="ordering-recommendation-detail-content" class="space-y-5 text-sm">
    <section class="overflow-hidden rounded-2xl border border-blue-200 bg-gradient-to-r from-blue-50 to-indigo-50 dark:border-blue-900 dark:from-blue-950/40 dark:to-indigo-950/30">
        <div class="flex flex-col gap-4 p-5 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-md border border-blue-200 bg-white/80 px-2 py-1 font-mono text-xs font-bold text-blue-700 dark:border-blue-900 dark:bg-gray-900/70 dark:text-blue-300">{{ $item->product_code }}</span>
                    <span class="rounded-md border border-gray-200 bg-white/80 px-2 py-1 text-xs font-semibold text-gray-600 dark:border-gray-700 dark:bg-gray-900/70 dark:text-gray-300">{{ $item->uom }}</span>
                    <span class="rounded-md border px-2 py-1 text-xs font-semibold {{ $confidenceTone[$item->confidence->value] }}">{{ $item->confidence->getLabel() }}</span>
                </div>
                <h3 class="mt-3 break-words text-xl font-bold text-gray-950 dark:text-white">{{ $item->product_name ?? $item->product_code }}</h3>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $item->category_name ?? 'Tanpa kategori' }} · {{ $item->branch?->name }} · {{ $item->company_code }}/{{ $item->esb_branch_code }}</p>
            </div>
            <div class="shrink-0 rounded-xl border border-blue-200 bg-white/90 px-5 py-4 text-left shadow-sm dark:border-blue-900 dark:bg-gray-900/90 lg:min-w-56 lg:text-right">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Rekomendasi order</p>
                <p @class(['mt-1 text-2xl font-bold', 'text-blue-700 dark:text-blue-300' => $item->final_recommendation !== null, 'text-red-700 dark:text-red-300' => $item->final_recommendation === null])>
                    {{ $item->final_recommendation !== null ? $fmt($item->final_recommendation).' '.$item->uom : 'Tidak dapat dihitung' }}
                </p>
                <p class="mt-1 text-xs text-gray-500">Periode {{ $run->forecast_from->isoFormat('D MMM') }}–{{ $run->forecast_to->isoFormat('D MMM Y') }}</p>
            </div>
        </div>
    </section>

    <section aria-labelledby="ordering-detail-identity" class="rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
        <div class="border-b border-gray-200 px-5 py-3 dark:border-gray-700">
            <h3 id="ordering-detail-identity" class="font-bold text-gray-900 dark:text-white">Identitas dan Periode</h3>
        </div>
        <dl class="grid gap-px bg-gray-200 dark:bg-gray-700 sm:grid-cols-2 xl:grid-cols-4">
            @foreach([
                ['Branch / Company', ($item->branch?->name ?? '-').' / '.$item->company_code],
                ['ESB Branch', $item->esb_branch_code],
                ['Histori', $run->history_from->isoFormat('D MMM Y').'–'.$run->history_to->isoFormat('D MMM Y')],
                ['Data valid', $item->valid_days.' hari'],
            ] as [$label, $value])
                <div class="bg-white px-4 py-3 dark:bg-gray-900">
                    <dt class="text-xs font-medium text-gray-500">{{ $label }}</dt>
                    <dd class="mt-1 break-words font-semibold text-gray-900 dark:text-white">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    </section>

    <section aria-labelledby="ordering-detail-baseline" class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 px-5 py-3 dark:border-gray-700">
            <div>
                <h3 id="ordering-detail-baseline" class="font-bold text-gray-900 dark:text-white">Baseline per Hari</h3>
                <p class="mt-0.5 text-xs text-gray-500">Metode: {{ $methods[$item->selected_method] ?? $item->selected_method }}</p>
            </div>
            <span class="rounded-md bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ count($item->weekday_baseline ?? []) }} hari</span>
        </div>
        <div class="max-h-[420px] overflow-auto">
            <table class="w-full min-w-[760px] text-xs">
                <thead class="sticky top-0 z-10 bg-gray-50 text-left uppercase tracking-wide text-gray-500 dark:bg-gray-800">
                    <tr><th scope="col" class="px-4 py-3">Hari</th><th scope="col" class="px-4 py-3 text-right">Baseline</th><th scope="col" class="px-4 py-3 text-center">Sampel</th><th scope="col" class="px-4 py-3">Tanggal × Usage × Bobot</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse($item->weekday_baseline ?? [] as $weekday => $baseline)
                        <tr class="align-top">
                            <td class="px-4 py-3 font-semibold text-gray-900 dark:text-white">{{ $weekdays[$weekday] ?? $weekday }}</td>
                            <td class="px-4 py-3 text-right font-bold text-blue-700 dark:text-blue-300">{{ $fmt($baseline['value']) }} {{ $item->uom }}</td>
                            <td class="px-4 py-3 text-center text-gray-600 dark:text-gray-300">{{ count($baseline['samples'] ?? []) }}</td>
                            <td class="px-4 py-3">
                                @if(! empty($baseline['fallback']))
                                    <p class="mb-2 inline-flex rounded-md bg-amber-50 px-2 py-1 text-amber-700 dark:bg-amber-950/30 dark:text-amber-300">Histori sedikit — memakai rata-rata keseluruhan</p>
                                @endif
                                <div class="flex flex-wrap gap-1.5">
                                    @forelse($baseline['samples'] ?? [] as $sample)
                                        <span class="rounded-md border border-gray-200 bg-gray-50 px-2 py-1 text-gray-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">{{ \Carbon\CarbonImmutable::parse($sample['date'])->isoFormat('D/M') }} · {{ $fmt($sample['usage']) }} × {{ $fmt($sample['weight'], 3) }}</span>
                                    @empty
                                        <span class="text-gray-400">Tidak ada sampel.</span>
                                    @endforelse
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-gray-400">Baseline harian tidak tersedia.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div class="grid gap-5 xl:grid-cols-2">
        <section aria-labelledby="ordering-detail-components" class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-5 py-3 dark:border-gray-700"><h3 id="ordering-detail-components" class="font-bold text-gray-900 dark:text-white">Komponen Perhitungan</h3></div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[520px] text-xs">
                    <thead class="bg-gray-50 text-left uppercase tracking-wide text-gray-500 dark:bg-gray-800"><tr><th class="px-4 py-3">Komponen</th><th class="px-4 py-3 text-right">Nilai</th><th class="px-4 py-3">Keterangan</th></tr></thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach($componentRows as [$label, $value, $note])
                            <tr class="align-top">
                                <td class="px-4 py-3 font-medium text-gray-600 dark:text-gray-300">{{ $label }}</td>
                                <td @class(['px-4 py-3 text-right font-semibold', 'text-red-600 dark:text-red-400' => $label === 'Available stock' && $stockUnsafe, 'text-gray-900 dark:text-white' => ! ($label === 'Available stock' && $stockUnsafe)])>{{ $value }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ $note }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section aria-labelledby="ordering-detail-result" class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-5 py-3 dark:border-gray-700"><h3 id="ordering-detail-result" class="font-bold text-gray-900 dark:text-white">Hasil Rekomendasi</h3></div>
            @if($item->final_recommendation === null)
                <div class="p-5"><div class="rounded-xl border border-red-200 bg-red-50 p-4 text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-300"><p class="font-semibold">Rekomendasi belum dapat dihitung</p><p class="mt-1 text-xs leading-5">{{ $item->explanation }}</p></div></div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[500px] text-xs">
                        <thead class="bg-gray-50 text-left uppercase tracking-wide text-gray-500 dark:bg-gray-800"><tr><th class="px-4 py-3">Tahap</th><th class="px-4 py-3 text-right">Hasil</th></tr></thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach([
                                ['Gross = forecast + expected waste + buffer', $fmt($item->gross_requirement).' '.$item->uom],
                                ['Raw = gross − stok − inbound', $fmt($item->raw_recommendation).' '.$item->uom],
                                ['Final = max(0, raw)', $fmt($item->final_recommendation).' '.$item->uom],
                                ['Coverage', $item->coverage_days === null ? 'Tidak ada demand' : $fmt($item->coverage_days, 1).' hari'],
                                ['Perkiraan stok habis', $item->predicted_stockout_date?->isoFormat('D MMM Y') ?? 'Tidak dalam horizon'],
                            ] as [$label, $value])
                                <tr><td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $label }}</td><td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-white">{{ $value }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-blue-200 bg-blue-50 px-5 py-4 dark:border-blue-900 dark:bg-blue-950/30"><p class="text-xs font-semibold uppercase tracking-wide text-blue-600 dark:text-blue-300">Keputusan sistem</p><p class="mt-1 text-sm leading-6 text-gray-700 dark:text-gray-200">{{ $item->explanation }}</p></div>
            @endif
        </section>
    </div>

    <section aria-labelledby="ordering-detail-quality" class="rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
        <div class="border-b border-gray-200 px-5 py-3 dark:border-gray-700"><h3 id="ordering-detail-quality" class="font-bold text-gray-900 dark:text-white">Model dan Kualitas Prediksi</h3></div>
        <dl class="grid gap-px bg-gray-200 dark:bg-gray-700 md:grid-cols-3">
            <div class="bg-white px-4 py-3 dark:bg-gray-900"><dt class="text-xs text-gray-500">Algoritma dan metode</dt><dd class="mt-1 font-semibold text-gray-900 dark:text-white">{{ $run->algorithm_version }} · {{ $methods[$item->selected_method] ?? $item->selected_method }}</dd></div>
            <div class="bg-white px-4 py-3 dark:bg-gray-900"><dt class="text-xs text-gray-500">Backtest</dt><dd class="mt-1 font-semibold text-gray-900 dark:text-white">{{ ($backtest['sufficient'] ?? false) ? 'MAE '.$fmt($backtest['mae'] ?? null).' · WAPE '.(isset($backtest['wape']) ? $fmt($backtest['wape'] * 100, 1).'%' : 'n/a').' · Bias '.(isset($backtest['bias']) ? $fmt($backtest['bias'] * 100, 1).'%' : 'n/a') : 'Sampel belum cukup' }}</dd><p class="mt-1 text-xs text-gray-500">{{ $backtest['sample_count'] ?? 0 }} sampel</p></div>
            <div class="bg-white px-4 py-3 dark:bg-gray-900"><dt class="text-xs text-gray-500">Confidence</dt><dd class="mt-1"><span class="inline-flex rounded-md border px-2 py-1 text-xs font-semibold {{ $confidenceTone[$item->confidence->value] }}">{{ $item->confidence->getLabel() }}</span></dd></div>
        </dl>
        @if(($item->reason_codes ?? []) !== [])
            <ul class="flex flex-wrap gap-2 border-t border-gray-200 px-5 py-3 dark:border-gray-700">
                @foreach($item->reason_codes as $code)
                    <li @class(['rounded-md border px-2 py-1 text-xs font-medium', 'border-red-200 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-300' => \App\Services\Ordering\OrderingReasonCodes::isBlocking($code), 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-300' => ! \App\Services\Ordering\OrderingReasonCodes::isBlocking($code)])>{{ \App\Services\Ordering\OrderingReasonCodes::label($code) }}</li>
                @endforeach
            </ul>
        @endif
    </section>

    @if(($item->daily_breakdown ?? []) !== [])
        <section aria-labelledby="ordering-detail-daily" class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-5 py-3 dark:border-gray-700"><h3 id="ordering-detail-daily" class="font-bold text-gray-900 dark:text-white">Proyeksi Harian</h3><p class="mt-0.5 text-xs text-gray-500">Perubahan kebutuhan dan proyeksi stok selama horizon forecast.</p></div>
            <div class="max-h-80 overflow-auto">
                <table class="w-full min-w-[560px] text-xs">
                    <thead class="sticky top-0 bg-gray-50 text-left uppercase tracking-wide text-gray-500 dark:bg-gray-800"><tr><th class="px-4 py-3">Tanggal</th><th class="px-4 py-3 text-right">Forecast</th><th class="px-4 py-3 text-right">Kumulatif</th><th class="px-4 py-3 text-right">Sisa Stok</th></tr></thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach($item->daily_breakdown as $day)
                            <tr><td class="px-4 py-3 font-medium text-gray-800 dark:text-gray-200">{{ \Carbon\CarbonImmutable::parse($day['date'])->isoFormat('ddd, D MMM Y') }}</td><td class="px-4 py-3 text-right">{{ $fmt($day['forecast']) }} {{ $item->uom }}</td><td class="px-4 py-3 text-right">{{ isset($day['cumulative']) ? $fmt($day['cumulative']).' '.$item->uom : '—' }}</td><td @class(['px-4 py-3 text-right font-semibold', 'text-red-600 dark:text-red-400' => isset($day['projected_stock']) && (float) $day['projected_stock'] <= 0])>{{ isset($day['projected_stock']) ? $fmt($day['projected_stock']).' '.$item->uom : '—' }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</div>
