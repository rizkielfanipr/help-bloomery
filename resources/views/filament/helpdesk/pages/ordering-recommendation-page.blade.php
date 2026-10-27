<x-filament-panels::page>
@php
    $fmt = fn ($value, int $decimals = 2): string => $value === null ? '—' : rtrim(rtrim(number_format((float) $value, $decimals, ',', '.'), '0'), ',');
    $fieldClass = 'w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100';
    $labelClass = 'mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400';
    $confidenceTone = [
        'high' => 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-300',
        'medium' => 'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-900 dark:bg-blue-950/30 dark:text-blue-300',
        'low' => 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-300',
        'blocked' => 'border-red-200 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-300',
    ];
    $stateTone = [
        'fresh' => 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-200',
        'stale' => 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200',
        'partial' => 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200',
        'failed' => 'border-red-200 bg-red-50 text-red-800 dark:border-red-900 dark:bg-red-950/30 dark:text-red-200',
    ];
    $rankingTabs = [
        'priority' => ['label' => 'Prioritas Order', 'icon' => 'heroicon-o-shopping-cart'],
        'top_demand' => ['label' => 'Top Demand', 'icon' => 'heroicon-o-chart-bar'],
        'trend_up' => ['label' => 'Trend Naik', 'icon' => 'heroicon-o-arrow-trending-up'],
        'trend_down' => ['label' => 'Trend Turun', 'icon' => 'heroicon-o-arrow-trending-down'],
        'waste' => ['label' => 'Waste', 'icon' => 'heroicon-o-trash'],
        'stockout' => ['label' => 'Stockout Risk', 'icon' => 'heroicon-o-exclamation-triangle'],
    ];
@endphp

<div class="space-y-6">
    {{-- Filter --}}
    <section aria-labelledby="ordering-filter-heading" class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900 sm:p-5">
        <h2 id="ordering-filter-heading" class="sr-only">Filter analisis</h2>
        <form wire:submit="applyFilters" class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <span class="{{ $labelClass }}">Brand</span>
                    <div class="max-h-36 space-y-1 overflow-y-auto rounded-lg border border-gray-200 p-2 dark:border-gray-700">
                        @forelse($brands as $brand)
                            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"><input type="checkbox" wire:model.live="brandIds" value="{{ $brand->id }}" class="rounded border-gray-300 text-blue-600"> <span class="break-words">{{ $brand->name }}</span></label>
                        @empty
                            <p class="text-xs text-gray-400">Tidak ada brand.</p>
                        @endforelse
                    </div>
                </div>
                <div>
                    <span class="{{ $labelClass }}">Branch</span>
                    <div class="max-h-36 space-y-1 overflow-y-auto rounded-lg border border-gray-200 p-2 dark:border-gray-700">
                        @forelse($branchOptions as $branch)
                            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"><input type="checkbox" wire:model="branchIds" value="{{ $branch->id }}" class="rounded border-gray-300 text-blue-600"> <span class="break-words">{{ $branch->name }}</span></label>
                        @empty
                            <p class="text-xs text-gray-400">Tidak ada branch yang dapat Anda akses.</p>
                        @endforelse
                    </div>
                </div>
                <div class="space-y-3">
                    <div>
                        <label for="ordering-period" class="{{ $labelClass }}">Periode Tampilan</label>
                        <select id="ordering-period" wire:model.live="period" class="{{ $fieldClass }}">
                            <option value="7">7 hari terakhir</option>
                            <option value="30">30 hari terakhir</option>
                            <option value="custom">Custom</option>
                        </select>
                    </div>
                    @if($period === 'custom')
                        <div class="grid grid-cols-2 gap-2">
                            <div><label for="ordering-from" class="sr-only">Dari</label><input id="ordering-from" type="date" wire:model="dateFrom" class="{{ $fieldClass }}">@error('dateFrom')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                            <div><label for="ordering-to" class="sr-only">Sampai</label><input id="ordering-to" type="date" wire:model="dateTo" class="{{ $fieldClass }}">@error('dateTo')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                        </div>
                    @endif
                    <div>
                        <label for="ordering-horizon" class="{{ $labelClass }}">Forecast Horizon</label>
                        <select id="ordering-horizon" wire:model="horizon" class="{{ $fieldClass }}">
                            @foreach($horizons as $days)<option value="{{ $days }}">{{ $days }} hari</option>@endforeach
                        </select>
                    </div>
                </div>
                <div class="space-y-3">
                    <div>
                        <label for="ordering-category" class="{{ $labelClass }}">Kategori</label>
                        <select id="ordering-category" wire:model="category" class="{{ $fieldClass }}">
                            <option value="">Semua kategori</option>
                            @foreach($categoryOptions as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label for="ordering-confidence" class="{{ $labelClass }}">Confidence</label>
                            <select id="ordering-confidence" wire:model="confidence" class="{{ $fieldClass }}">
                                <option value="">Semua</option>
                                @foreach(\App\Enums\OrderingConfidence::cases() as $level)<option value="{{ $level->value }}">{{ $level->getLabel() }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label for="ordering-risk" class="{{ $labelClass }}">Stock Risk</label>
                            <select id="ordering-risk" wire:model="risk" class="{{ $fieldClass }}">
                                <option value="">Semua</option>
                                <option value="need_order">Perlu order</option>
                                <option value="stockout">Risiko stockout</option>
                                <option value="overstock">Potensi overstock</option>
                                <option value="blocked">Blocked</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label for="ordering-search" class="{{ $labelClass }}">Cari Produk</label>
                        <input id="ordering-search" type="search" wire:model="search" maxlength="100" placeholder="Kode atau nama produk" class="{{ $fieldClass }}">
                    </div>
                </div>
            </div>
            <div class="flex flex-wrap items-center justify-end gap-2">
                <button type="button" wire:click="resetFilters" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">Reset</button>
                @if($canExport)
                    <button type="button" wire:click="export" wire:loading.attr="disabled" wire:target="export" class="inline-flex items-center gap-2 rounded-lg border border-emerald-300 bg-white px-4 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-50 disabled:opacity-50 dark:border-emerald-800 dark:bg-gray-900 dark:text-emerald-300">
                        <x-heroicon-o-arrow-down-tray class="h-4 w-4" wire:loading.remove wire:target="export" />
                        <x-heroicon-o-arrow-path class="h-4 w-4 animate-spin" wire:loading wire:target="export" />
                        Export Excel
                    </button>
                @endif
                <button type="submit" wire:loading.attr="disabled" wire:target="applyFilters" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50">
                    <x-heroicon-o-arrow-path class="h-4 w-4 animate-spin" wire:loading wire:target="applyFilters" />
                    Tampilkan Analisis
                </button>
            </div>
        </form>
    </section>

    @if($runs->isEmpty())
        <section class="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-12 text-center dark:border-gray-700 dark:bg-gray-900">
            <x-heroicon-o-inbox class="mx-auto h-8 w-8 text-gray-300" />
            <p class="mt-3 text-sm font-semibold text-gray-700 dark:text-gray-200">Belum ada snapshot forecast</p>
            <p class="mx-auto mt-1 max-w-lg text-xs text-gray-500 dark:text-gray-400">Branch terpilih belum memiliki forecast {{ $applied['horizon'] ?? $horizon }} hari yang selesai dibuat. Setelah lokasi stok disimpan, backfill 8 minggu berjalan melalui queue dan waktunya bergantung pada respons ESB. Pastikan queue worker aktif, lalu muat ulang halaman ini setelah proses selesai.</p>
        </section>
    @else
        {{-- Freshness / completeness --}}
        @if($sourceState)
            @php $stateText = \App\Services\Ordering\OrderingRecommendationQuery::stateText($sourceState['state']); @endphp
            <section aria-label="Kelengkapan data" class="rounded-2xl border px-4 py-3 text-sm {{ $stateTone[$sourceState['state']] }}">
                <p class="font-semibold">{{ $activeRun->branch?->name }}: {{ $stateText['label'] }}</p>
                <p class="mt-0.5">{{ $stateText['description'] }}</p>
                <dl class="mt-2 grid gap-x-6 gap-y-1 text-xs sm:grid-cols-2 xl:grid-cols-4">
                    <div><dt class="inline opacity-80">Data ESB terakhir diambil:</dt> <dd class="inline font-medium">{{ $sourceState['last_synced_at'] ? \Carbon\CarbonImmutable::parse($sourceState['last_synced_at'])->timezone(config('app.timezone'))->isoFormat('D MMM Y, HH:mm') : 'belum pernah' }}</dd></div>
                    <div><dt class="inline opacity-80">Rekomendasi dihitung:</dt> <dd class="inline font-medium">{{ $activeRun->generated_at?->timezone(config('app.timezone'))->isoFormat('D MMM Y, HH:mm') }}</dd></div>
                    <div><dt class="inline opacity-80">Periode data:</dt> <dd class="inline font-medium">{{ $activeRun->history_from->isoFormat('D MMM Y') }} sampai {{ $activeRun->history_to->isoFormat('D MMM Y') }}</dd></div>
                    <div><dt class="inline opacity-80">Versi perhitungan:</dt> <dd class="inline font-medium">{{ $activeRun->algorithm_version }}</dd></div>
                </dl>
                @if($sourceState['warnings'] !== [])
                    <div class="mt-3 border-t border-current/20 pt-2">
                        <p class="text-xs font-semibold">Perlu diperhatikan</p>
                        <ul role="list" class="mt-1 space-y-1 text-xs">
                            @foreach($sourceState['warnings'] as $warning)
                                <li class="flex items-start gap-1.5"><x-heroicon-o-exclamation-triangle class="mt-px h-3.5 w-3.5 shrink-0" aria-hidden="true" /><span>{{ \App\Services\Ordering\OrderingReasonCodes::note($warning) }}</span></li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </section>
        @endif

        {{-- Branch overview --}}
        @if($overview !== [])
            <section aria-labelledby="ordering-overview-heading" class="space-y-3">
                <h2 id="ordering-overview-heading" class="text-sm font-semibold text-gray-900 dark:text-white">Ringkasan per Branch</h2>
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    @foreach($overview as $row)
                        <article @class(['rounded-xl border bg-white p-4 dark:bg-gray-900', 'border-blue-400 dark:border-blue-600' => $row['branch_id'] === $activeRun->branch_id, 'border-gray-200 dark:border-gray-700' => $row['branch_id'] !== $activeRun->branch_id])>
                            <div class="flex items-start justify-between gap-2">
                                <h3 class="break-words text-sm font-semibold text-gray-900 dark:text-white">{{ $row['branch'] }}</h3>
                                <span class="shrink-0 rounded-md border px-2 py-0.5 text-[11px] font-semibold {{ $stateTone[$row['source_state']['state']] }}">{{ \App\Services\Ordering\OrderingRecommendationQuery::stateText($row['source_state']['state'])['label'] }}</span>
                            </div>
                            <dl class="mt-3 grid grid-cols-2 gap-2 text-xs">
                                <div><dt class="text-gray-500">Perlu order</dt><dd class="font-semibold text-gray-900 dark:text-white">{{ $row['kpis']['need_order'] }} SKU</dd></div>
                                <div><dt class="text-gray-500">Risiko stockout</dt><dd class="font-semibold text-gray-900 dark:text-white">{{ $row['kpis']['stockout'] }} SKU</dd></div>
                                <div class="col-span-2"><dt class="text-gray-500">Trend naik tertinggi</dt><dd class="break-words font-semibold text-gray-900 dark:text-white">{{ $row['top_rising'] ? $row['top_rising']->product_name.' (+'.$fmt($row['top_rising']->trendPercent(), 1).'%)' : '—' }}</dd></div>
                                <div class="col-span-2"><dt class="text-gray-500">Waste tertinggi</dt><dd class="break-words font-semibold text-gray-900 dark:text-white">{{ $row['top_waste'] ? $row['top_waste']->product_name.' ('.$fmt((float) $row['top_waste']->waste_rate_capped * 100, 1).'%)' : '—' }}</dd></div>
                                <div class="col-span-2"><dt class="text-gray-500">Confidence</dt><dd class="text-gray-700 dark:text-gray-300">@foreach($row['confidence'] as $level => $count){{ ucfirst($level) }} {{ $count }}@if(! $loop->last) · @endif @endforeach</dd></div>
                            </dl>
                            <button type="button" wire:click="selectBranch({{ $row['branch_id'] }})" class="mt-3 text-xs font-semibold text-blue-600 hover:underline dark:text-blue-400">Lihat Detail Branch</button>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Ranking per branch --}}
        <section aria-labelledby="ordering-ranking-heading" class="rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <div class="flex flex-wrap items-end justify-between gap-3 border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                <div>
                    <h2 id="ordering-ranking-heading" class="text-base font-bold text-gray-900 dark:text-white">Produk Teratas: {{ $activeRun->branch?->name }}</h2>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ config('ordering_recommendation.ranking_limit') }} produk teratas berdasarkan {{ $rankingTabs[$rankingType]['label'] }} untuk
                        <strong class="font-semibold text-gray-700 dark:text-gray-200">{{ $activeRun->forecast_from->isoFormat('D MMM Y') }} sampai {{ $activeRun->forecast_to->isoFormat('D MMM Y') }}</strong>.
                        Batas waktu order dihitung dari perkiraan tanggal stok habis. Waktu pengiriman dari supplier dan barang yang sedang dalam perjalanan belum ikut dihitung.
                    </p>
                </div>
                @if($runs->count() > 1)
                    <div class="w-56"><label for="ordering-active-branch" class="{{ $labelClass }}">Branch</label>
                        <select id="ordering-active-branch" wire:change="selectBranch($event.target.value)" class="{{ $fieldClass }}">@foreach($runs as $run)<option value="{{ $run->branch_id }}" @selected($run->branch_id === $activeRun->branch_id)>{{ $run->branch?->name }}</option>@endforeach</select>
                    </div>
                @endif
            </div>
            <div role="tablist" aria-label="Jenis ranking" class="flex gap-2 overflow-x-auto border-b border-gray-200 px-5 py-3 dark:border-gray-700">
                @foreach($rankingTabs as $type => $tab)
                    <button type="button" role="tab" aria-selected="{{ $rankingType === $type ? 'true' : 'false' }}" wire:click="$set('rankingType', '{{ $type }}')" @class([
                        'inline-flex shrink-0 items-center gap-2 rounded-lg px-3 py-2 text-sm font-bold transition',
                        'bg-blue-600 text-white' => $rankingType === $type,
                        'border border-gray-200 text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800' => $rankingType !== $type,
                    ])>
                        <x-dynamic-component :component="$tab['icon']" class="h-4 w-4" aria-hidden="true" />
                        {{ $tab['label'] }}
                    </button>
                @endforeach
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1050px] text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-800/60">
                        <tr>
                            <th scope="col" class="w-12 px-3 py-3 text-center">#</th>
                            <th scope="col" class="px-3 py-3">Produk</th>
                            <th scope="col" class="px-3 py-3 text-right">Kebutuhan {{ $activeRun->horizon_days }} Hari</th>
                            <th scope="col" class="px-3 py-3 text-right">Tren & Waste</th>
                            <th scope="col" class="px-3 py-3 text-right">Stok & Coverage</th>
                            <th scope="col" class="px-3 py-3">Stok Aman Hingga</th>
                            <th scope="col" class="px-3 py-3">Order Sebelum</th>
                            <th scope="col" class="px-3 py-3 text-right">Rekomendasi</th>
                            <th scope="col" class="w-16 px-3 py-3"><span class="sr-only">Aksi</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($ranking as $item)
                            @php
                                $hasStock = $item->available_stock !== null;
                                $stockIsUnsafe = $hasStock && (float) $item->available_stock <= 0;
                                $stockoutDate = $item->predicted_stockout_date;
                                $safeUntilDate = $stockoutDate?->copy()->subDay();
                                $orderDeadline = $stockoutDate ?? $activeRun->forecast_from;
                                $needsOrder = $item->final_recommendation !== null && (float) $item->final_recommendation > 0;
                                $urgent = $needsOrder && ($stockIsUnsafe || $orderDeadline->lte($activeRun->forecast_from));
                            @endphp
                            <tr wire:key="ordering-ranking-{{ $rankingType }}-{{ $item->id }}" class="align-top">
                                <td class="px-3 py-3 text-center font-mono text-xs font-semibold text-gray-400">{{ $loop->iteration }}</td>
                                <td class="px-3 py-3">
                                    <p class="max-w-xs break-words font-semibold text-gray-900 dark:text-white">{{ $item->product_name ?? $item->product_code }}</p>
                                    <p class="mt-1 font-mono text-xs text-gray-500">{{ $item->product_code }} · {{ $item->uom }} · {{ $item->company_code }}</p>
                                </td>
                                <td class="px-3 py-3 text-right">
                                    <p class="font-semibold text-gray-900 dark:text-white">{{ $fmt($item->forecast_demand) }} {{ $item->uom }}</p>
                                    <p class="mt-1 text-xs text-gray-500">Rata-rata {{ $fmt($item->average_daily_demand) }}/hari</p>
                                </td>
                                <td class="px-3 py-3 text-right text-xs">
                                    <p class="font-semibold text-gray-800 dark:text-gray-200">{{ $item->trendPercent() !== null ? ($item->trendPercent() > 0 ? '+' : '').$fmt($item->trendPercent(), 1).'%' : '—' }}</p>
                                    <p class="mt-1 text-gray-500">Waste {{ $item->waste_rate_capped !== null ? $fmt((float) $item->waste_rate_capped * 100, 1).'%' : '—' }}</p>
                                </td>
                                <td class="px-3 py-3 text-right text-xs">
                                    <p @class(['font-semibold', 'text-red-600 dark:text-red-400' => $stockIsUnsafe, 'text-gray-800 dark:text-gray-200' => ! $stockIsUnsafe])>{{ $hasStock ? $fmt($item->available_stock).' '.$item->uom : 'Tidak tersedia' }}</p>
                                    <p class="mt-1 text-gray-500">{{ $item->coverage_days !== null ? $fmt($item->coverage_days, 1).' hari' : 'Coverage —' }}</p>
                                </td>
                                <td class="px-3 py-3 text-xs">
                                    @if($stockIsUnsafe)
                                        <span class="inline-flex rounded-md bg-red-50 px-2 py-1 font-semibold text-red-700 dark:bg-red-950/30 dark:text-red-300">Stok Habis</span>
                                    @elseif($safeUntilDate)
                                        <p class="font-semibold text-gray-800 dark:text-gray-200">{{ $safeUntilDate->isoFormat('D MMM Y') }}</p>
                                        <p class="mt-1 text-gray-500">Habis {{ $stockoutDate->isoFormat('D MMM Y') }}</p>
                                    @else
                                        <p class="font-semibold text-emerald-700 dark:text-emerald-300">≥ {{ $activeRun->forecast_to->isoFormat('D MMM Y') }}</p>
                                        <p class="mt-1 text-gray-500">Tidak diprediksi habis</p>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-xs">
                                    @if($needsOrder)
                                        <p @class(['font-semibold', 'text-red-600 dark:text-red-400' => $urgent, 'text-gray-800 dark:text-gray-200' => ! $urgent])>{{ $urgent ? 'Order Segera' : $orderDeadline->isoFormat('D MMM Y') }}</p>
                                        <p class="mt-1 text-gray-500">Batas {{ $orderDeadline->isoFormat('D MMM Y') }}</p>
                                    @else
                                        <p class="font-semibold text-gray-500">Belum perlu order</p>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-right">
                                    <p class="font-bold text-blue-700 dark:text-blue-300">{{ $item->final_recommendation !== null ? $fmt($item->final_recommendation).' '.$item->uom : 'Tidak dapat dihitung' }}</p>
                                    <span class="mt-1 inline-flex rounded-md border px-2 py-0.5 text-[11px] font-semibold {{ $confidenceTone[$item->confidence->value] }}">{{ $item->confidence->getLabel() }}</span>
                                </td>
                                <td class="px-3 py-3 text-right">
                                    <button type="button" wire:click="openDetail({{ $item->id }})" class="inline-flex rounded-lg border border-gray-200 p-2 text-blue-600 hover:bg-blue-50 dark:border-gray-700 dark:text-blue-400 dark:hover:bg-blue-950/30" aria-label="Lihat detail {{ $item->product_name ?? $item->product_code }}"><x-heroicon-o-eye class="h-4 w-4" /></button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="px-4 py-10 text-center text-xs text-gray-400">Tidak ada produk untuk {{ mb_strtolower($rankingTabs[$rankingType]['label']) }} pada branch dan periode ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Recommendation table --}}
        <section aria-labelledby="ordering-table-heading" class="rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <div class="flex flex-wrap items-end justify-between gap-3 border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                <div>
                    <h2 id="ordering-table-heading" class="text-base font-bold text-gray-900 dark:text-white">Prediksi Ordering Produk</h2>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Setiap baris berisi satu produk untuk satu branch dalam satu satuan. Jumlah dari branch atau satuan yang berbeda tidak digabungkan.</p>
                </div>
                <span class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-xs font-semibold text-gray-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">{{ $items->total() }} produk</span>
            </div>
            <div class="overflow-x-auto">
                <table data-testid="ordering-recommendation-products-table" class="w-full min-w-[1050px] text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-800/60">
                        <tr>
                            <th scope="col" class="px-3 py-3">Branch</th>
                            <th scope="col" class="px-3 py-3"><button type="button" wire:click="sortBy('product')" class="font-semibold uppercase hover:text-blue-600">Produk</button></th>
                            <th scope="col" class="px-3 py-3">UOM</th>
                            <th scope="col" class="px-3 py-3 text-right"><button type="button" wire:click="sortBy('trend')" class="font-semibold uppercase hover:text-blue-600">Tren</button> <span aria-hidden="true">&</span> <button type="button" wire:click="sortBy('waste')" class="font-semibold uppercase hover:text-blue-600">Waste</button></th>
                            <th scope="col" class="px-3 py-3 text-right">Stok & <button type="button" wire:click="sortBy('coverage')" class="font-semibold uppercase hover:text-blue-600">Coverage</button></th>
                            <th scope="col" class="px-3 py-3 text-right"><button type="button" wire:click="sortBy('forecast')" class="font-semibold uppercase hover:text-blue-600">Forecast</button></th>
                            <th scope="col" class="px-3 py-3 text-right"><button type="button" wire:click="sortBy('recommendation')" class="font-semibold uppercase hover:text-blue-600">Rekomendasi</button></th>
                            <th scope="col" class="w-16 px-3 py-3"><span class="sr-only">Aksi</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($items as $item)
                            @php
                                $hasStock = $item->available_stock !== null;
                                $stockIsUnsafe = $hasStock && (float) $item->available_stock <= 0;
                            @endphp
                            <tr wire:key="ordering-item-{{ $item->id }}" class="align-top transition hover:bg-gray-50/70 dark:hover:bg-gray-800/40">
                                <td class="px-3 py-3 text-xs">
                                    <p class="max-w-48 break-words font-semibold text-gray-800 dark:text-gray-200">{{ $item->branch?->name }}</p>
                                    <p class="mt-1 font-mono text-gray-500">{{ $item->company_code }} · {{ $item->esb_branch_code }}</p>
                                </td>
                                <td class="px-3 py-3">
                                    <p class="max-w-xs break-words font-semibold text-gray-900 dark:text-white">{{ $item->product_name ?? $item->product_code }}</p>
                                    <p class="mt-1 font-mono text-xs text-gray-500">{{ $item->product_code }}</p>
                                    <p class="mt-1 text-xs text-gray-400">{{ $item->category_name ?? 'Tanpa kategori' }}</p>
                                </td>
                                <td class="px-3 py-3"><span class="inline-flex rounded-md border border-gray-200 bg-gray-50 px-2 py-1 text-xs font-semibold text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">{{ $item->uom }}</span></td>
                                <td class="px-3 py-3 text-right text-xs">
                                    <p class="font-semibold text-gray-800 dark:text-gray-200">{{ $item->trendPercent() !== null ? ($item->trendPercent() > 0 ? '+' : '').$fmt($item->trendPercent(), 1).'%' : '—' }} · {{ $item->trend_label->getLabel() }}</p>
                                    <p class="mt-1 text-gray-500">Waste {{ $item->waste_rate_capped !== null ? $fmt((float) $item->waste_rate_capped * 100, 1).'%' : 'tidak tersedia' }}</p>
                                </td>
                                <td class="px-3 py-3 text-right text-xs">
                                    <p @class(['font-semibold', 'text-red-600 dark:text-red-400' => $stockIsUnsafe, 'text-gray-800 dark:text-gray-200' => ! $stockIsUnsafe])>{{ $hasStock ? $fmt($item->available_stock).' '.$item->uom : 'Tidak tersedia' }}</p>
                                    <p class="mt-1 text-gray-500">{{ $item->coverage_days !== null ? $fmt($item->coverage_days, 1).' hari' : ($item->confidence === \App\Enums\OrderingConfidence::Blocked ? 'Coverage —' : 'Tidak ada demand') }}</p>
                                    @if($item->predicted_stockout_date)<p class="mt-1 font-medium text-amber-700 dark:text-amber-300">Habis {{ $item->predicted_stockout_date->isoFormat('D MMM Y') }}</p>@endif
                                </td>
                                <td class="px-3 py-3 text-right">
                                    <p class="font-semibold text-gray-900 dark:text-white">{{ $fmt($item->forecast_demand) }} {{ $item->uom }}</p>
                                    <p class="mt-1 text-xs text-gray-500">{{ $activeRun->horizon_days }} hari</p>
                                </td>
                                <td class="px-3 py-3 text-right">
                                    <p class="font-bold text-blue-700 dark:text-blue-300">{{ $item->final_recommendation !== null ? $fmt($item->final_recommendation).' '.$item->uom : 'Tidak dapat dihitung' }}</p>
                                    <span class="mt-1 inline-flex rounded-md border px-2 py-0.5 text-[11px] font-semibold {{ $confidenceTone[$item->confidence->value] }}">{{ $item->confidence->getLabel() }}</span>
                                </td>
                                <td class="px-3 py-3 text-right"><button type="button" wire:click="openDetail({{ $item->id }})" class="inline-flex rounded-lg border border-gray-200 p-2 text-blue-600 transition hover:bg-blue-50 dark:border-gray-700 dark:text-blue-400 dark:hover:bg-blue-950/30" aria-label="Lihat detail {{ $item->product_name ?? $item->product_code }}"><x-heroicon-o-eye class="h-4 w-4" /></button></td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-4 py-10 text-center text-xs text-gray-400">Tidak ada produk yang cocok dengan filter. Ubah filter lalu tekan Tampilkan Analisis.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-200 px-5 py-4 dark:border-gray-700"><x-filament::pagination :paginator="$items" /></div>
        </section>
    @endif

    {{-- Detail --}}
    <x-filament::modal
        id="ordering-recommendation-detail"
        width="7xl"
        sticky-header
        :close-by-clicking-away="true"
        :extra-modal-window-attribute-bag="new \Illuminate\View\ComponentAttributeBag(['style' => 'max-height: calc(100dvh - 2rem); overflow-y: auto; overscroll-behavior: contain;'])"
        x-on:modal-closed.window="if ($event.detail.id === 'ordering-recommendation-detail') $wire.closeDetail()"
    >
        <x-slot name="heading">Detail Recommendation</x-slot>
        @if($detail)
            @include('filament.helpdesk.pages.partials.ordering-recommendation-detail', ['item' => $detail, 'fmt' => $fmt])
        @endif
    </x-filament::modal>
</div>

</x-filament-panels::page>
