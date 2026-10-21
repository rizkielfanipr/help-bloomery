{{-- Formula of one recommendation, step by step, from stored components only. --}}
@php
    $run = $item->run;
    $weekdays = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];
    $methods = ['weekday_weighted' => 'Weekday weighted average', 'seasonal_naive' => 'Same-weekday seasonal naive', 'rolling_weekday_factor' => 'Rolling average × weekday factor', 'weekday_median' => 'Median weekday'];
    $backtest = $item->backtest ?? [];
    $row = fn (string $label, string $value): string => '<div class="flex justify-between gap-4 py-1"><dt class="text-gray-500">'.e($label).'</dt><dd class="text-right font-medium text-gray-900 dark:text-white">'.e($value).'</dd></div>';
@endphp
<div class="space-y-5 text-sm">
    <section>
        <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">1. Identitas</h3>
        <dl class="mt-2 divide-y divide-gray-100 dark:divide-gray-800">
            {!! $row('Branch / Company / ESB Branch', ($item->branch?->name ?? '-').' / '.$item->company_code.' / '.$item->esb_branch_code) !!}
            {!! $row('Produk', ($item->product_name ?? '-').' ('.$item->product_code.')') !!}
            {!! $row('Kategori · UOM', ($item->category_name ?? 'Tanpa kategori').' · '.$item->uom) !!}
            {!! $row('Histori', $run->history_from->isoFormat('D MMM Y').' – '.$run->history_to->isoFormat('D MMM Y').' ('.$item->valid_days.' hari valid)') !!}
            {!! $row('Forecast', $run->forecast_from->isoFormat('D MMM Y').' – '.$run->forecast_to->isoFormat('D MMM Y')) !!}
        </dl>
    </section>

    <section>
        <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">2. Baseline per hari ({{ $methods[$item->selected_method] ?? $item->selected_method }})</h3>
        <div class="mt-2 overflow-x-auto">
            <table class="w-full min-w-[480px] text-xs">
                <thead class="text-left text-gray-500"><tr><th class="py-1">Hari</th><th class="py-1 text-right">Baseline</th><th class="py-1">Sampel (tanggal × bobot)</th></tr></thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach($item->weekday_baseline ?? [] as $weekday => $baseline)
                        <tr><td class="py-1">{{ $weekdays[$weekday] ?? $weekday }}</td><td class="py-1 text-right">{{ $fmt($baseline['value']) }}</td>
                            <td class="py-1 text-gray-500">@if($baseline['fallback'])<span class="text-amber-600">Histori sedikit → rata-rata keseluruhan. </span>@endif{{ collect($baseline['samples'])->map(fn ($sample) => \Carbon\CarbonImmutable::parse($sample['date'])->isoFormat('D/M').': '.$fmt($sample['usage']).' × '.$fmt($sample['weight'], 3))->join(', ') }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section>
        <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">3. Komponen</h3>
        <dl class="mt-2 divide-y divide-gray-100 dark:divide-gray-800">
            {!! $row('Demand historis (total · rata-rata/hari)', $fmt($item->historical_demand).' · '.$fmt($item->average_daily_demand)) !!}
            {!! $row('Trend factor (raw → capped)', ($item->trend_raw !== null ? $fmt($item->trend_raw, 3) : 'n/a').' → '.$fmt($item->trend_final, 3).' · '.$item->trend_label->getLabel()) !!}
            {!! $row('Forecast demand', $fmt($item->forecast_demand).' '.$item->uom) !!}
            {!! $row('Waste rate (raw → capped)', $item->waste_rate_raw === null ? 'Tidak tersedia' : $fmt((float) $item->waste_rate_raw * 100, 2).'% → '.$fmt((float) $item->waste_rate_capped * 100, 2).'%') !!}
            {!! $row('Expected waste', $item->expected_waste === null ? 'Tidak diperhitungkan' : $fmt($item->expected_waste)) !!}
            {!! $row('Safety buffer (global default)', $fmt((float) $item->buffer_percent * 100, 1).'% = '.$fmt($item->safety_buffer)) !!}
            {!! $row('Available stock', $item->available_stock === null ? 'Tidak tersedia' : $fmt($item->available_stock).($item->stock_observed_at ? ' (per '.$item->stock_observed_at->timezone(config('app.timezone'))->isoFormat('D MMM, HH:mm').')' : '')) !!}
            {!! $row('Confirmed inbound', $item->confirmed_inbound === null ? 'Inbound belum diperhitungkan' : $fmt($item->confirmed_inbound)) !!}
        </dl>
    </section>

    <section class="rounded-xl border border-gray-200 p-3 dark:border-gray-700">
        <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">4. Rekomendasi</h3>
        @if($item->final_recommendation === null)
            <p class="mt-2 text-sm font-semibold text-red-700 dark:text-red-300">{{ $item->explanation }}</p>
        @else
            <dl class="mt-2 divide-y divide-gray-100 dark:divide-gray-800">
                {!! $row('Gross = forecast + expected waste + buffer', $fmt($item->gross_requirement)) !!}
                {!! $row('Raw = gross − stok − inbound', $fmt($item->raw_recommendation)) !!}
                {!! $row('Final = max(0, raw)', $fmt($item->final_recommendation).' '.$item->uom) !!}
                {!! $row('Coverage', $item->coverage_days === null ? 'Tidak ada demand pada periode' : $fmt($item->coverage_days, 1).' hari') !!}
                {!! $row('Perkiraan stok habis', $item->predicted_stockout_date?->isoFormat('D MMM Y') ?? 'Tidak dalam horizon') !!}
            </dl>
            <p class="mt-2 text-xs text-gray-600 dark:text-gray-300">{{ $item->explanation }}</p>
        @endif
    </section>

    <section>
        <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">5. Model, backtest, dan confidence</h3>
        <dl class="mt-2 divide-y divide-gray-100 dark:divide-gray-800">
            {!! $row('Algoritma · metode', $run->algorithm_version.' · '.($methods[$item->selected_method] ?? $item->selected_method)) !!}
            {!! $row('Backtest', ($backtest['sufficient'] ?? false) ? 'MAE '.$fmt($backtest['mae'] ?? null).' · WAPE '.(isset($backtest['wape']) ? $fmt($backtest['wape'] * 100, 1).'%' : 'n/a').' · Bias '.(isset($backtest['bias']) ? $fmt($backtest['bias'] * 100, 1).'%' : 'n/a').' · '.($backtest['sample_count'] ?? 0).' sampel' : 'Sampel belum cukup ('.($backtest['sample_count'] ?? 0).' hari)') !!}
            {!! $row('Confidence', $item->confidence->getLabel()) !!}
        </dl>
        @if(($item->reason_codes ?? []) !== [])
            <ul class="mt-2 flex flex-wrap gap-2">
                @foreach($item->reason_codes as $code)
                    <li @class(['rounded-md border px-2 py-0.5 text-xs', 'border-red-200 bg-red-50 text-red-700' => \App\Services\Ordering\OrderingReasonCodes::isBlocking($code), 'border-amber-200 bg-amber-50 text-amber-700' => ! \App\Services\Ordering\OrderingReasonCodes::isBlocking($code)])>{{ \App\Services\Ordering\OrderingReasonCodes::label($code) }}</li>
                @endforeach
            </ul>
        @endif
    </section>

    @if(($item->daily_breakdown ?? []) !== [])
        <section>
            <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">6. Proyeksi harian</h3>
            <div class="mt-2 overflow-x-auto">
                <table class="w-full min-w-[420px] text-xs">
                    <thead class="text-left text-gray-500"><tr><th class="py-1">Tanggal</th><th class="py-1 text-right">Forecast</th><th class="py-1 text-right">Kumulatif</th><th class="py-1 text-right">Sisa stok</th></tr></thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach($item->daily_breakdown as $day)
                            <tr><td class="py-1">{{ \Carbon\CarbonImmutable::parse($day['date'])->isoFormat('ddd, D MMM') }}</td><td class="py-1 text-right">{{ $fmt($day['forecast']) }}</td><td class="py-1 text-right">{{ isset($day['cumulative']) ? $fmt($day['cumulative']) : '—' }}</td><td class="py-1 text-right">{{ isset($day['projected_stock']) ? $fmt($day['projected_stock']) : '—' }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</div>
