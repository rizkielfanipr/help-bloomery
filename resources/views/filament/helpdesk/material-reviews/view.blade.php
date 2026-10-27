<x-filament-panels::page>
    @php
        $material = $this->material();
        $review = $this->currentReview;
        $checklist = $this->checklist;
        $duplicates = $this->duplicateCandidates;
        $esbStatus = $material->effectiveEsbSyncStatus();
        $sectionClass = 'rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900 sm:p-6';
        $labelClass = 'text-xs font-semibold text-gray-500 dark:text-gray-400';
        $valueClass = 'mt-0.5 break-words text-sm font-medium text-gray-900 dark:text-white';
        $jakarta = fn ($date) => $date ? $date->copy()->timezone('Asia/Jakarta')->format('d M Y H:i').' WIB' : '-';
    @endphp

    <div class="space-y-6">
        <section class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900 sm:p-6">
            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <div class="flex min-w-0 items-start gap-4">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-950/40 dark:text-blue-300"><x-heroicon-o-clipboard-document-check class="h-6 w-6" aria-hidden="true" /></div>
                    <div class="min-w-0">
                        <p class="font-mono text-xs font-bold uppercase tracking-wider text-blue-600 dark:text-blue-400">{{ $material->product_code }} · {{ \App\Services\Rnd\Material\MaterialClassification::label($material) }} · versi {{ $material->submission_version }}</p>
                        <h2 class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ $material->product_name }}</h2>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Diajukan {{ $review?->submitter?->name ?? '-' }} · {{ $jakarta($review?->submitted_at) }}{{ $review?->claimer ? ' · direview '.$review->claimer->name : '' }}</p>
                    </div>
                </div>
                <div class="flex shrink-0 flex-wrap gap-2">
                    @if ($material->review_status)
                        <x-helpdesk.monitoring.status-badge :label="'Review: '.$material->review_status->getLabel()" :color="$material->review_status->getColor()" />
                    @endif
                    <x-helpdesk.monitoring.status-badge :label="'ESB: '.$esbStatus->getLabel()" :color="$esbStatus->getColor()" />
                </div>
            </div>
            @if ($material->sync_error && in_array($esbStatus, [\App\Enums\MaterialEsbSyncStatus::NeedsReconciliation, \App\Enums\MaterialEsbSyncStatus::Failed], true))
                <p class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200">{{ $material->sync_error }}</p>
            @endif
        </section>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)]">
            <div class="space-y-6">
                <section class="{{ $sectionClass }}">
                    <h3 class="mb-4 text-sm font-semibold text-gray-900 dark:text-white">Identitas & Klasifikasi</h3>
                    <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div><dt class="{{ $labelClass }}">Kategori</dt><dd class="{{ $valueClass }}">{{ $material->category_name ?: '-' }} <span class="text-xs text-gray-500">(ID {{ $material->category_id ?? '-' }})</span></dd></div>
                        <div><dt class="{{ $labelClass }}">Subkategori</dt><dd class="{{ $valueClass }}">{{ $material->sub_category_name ?: '-' }} <span class="text-xs text-gray-500">(ID {{ $material->sub_category_id ?? '-' }})</span></dd></div>
                        <div><dt class="{{ $labelClass }}">Product ID ESB</dt><dd class="{{ $valueClass }} font-mono">{{ $material->esb_product_id ?: '-' }}</dd></div>
                        <div><dt class="{{ $labelClass }}">Catatan / Spesifikasi</dt><dd class="{{ $valueClass }}">{{ $material->notes ?: '-' }}</dd></div>
                    </dl>
                </section>

                <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                    <h3 class="px-5 pt-5 text-sm font-semibold text-gray-900 dark:text-white sm:px-6">Unit (UOM)</h3>
                    <div class="mt-3 overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                            <thead class="bg-gray-50 text-left text-xs font-semibold text-gray-500 dark:bg-gray-800/50 dark:text-gray-400">
                                <tr><th scope="col" class="px-4 py-2">UOM</th><th scope="col" class="px-4 py-2">SKU</th><th scope="col" class="px-4 py-2 text-right">Conversion</th><th scope="col" class="px-4 py-2">Flag</th><th scope="col" class="px-4 py-2">Product Detail ID</th></tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach ($material->units as $unit)
                                    <tr wire:key="unit-{{ $unit->id }}" @class(['opacity-60' => ! $unit->flag_active])>
                                        <td class="px-4 py-2 font-semibold">{{ $unit->uom_name }} <span class="text-xs font-normal text-gray-500">#{{ $unit->uom_id }}</span></td>
                                        <td class="px-4 py-2 font-mono text-xs">{{ $unit->sku }}</td>
                                        <td class="px-4 py-2 text-right">{{ rtrim(rtrim(number_format((float) $unit->conversion_factor, 4, '.', ''), '0'), '.') }}</td>
                                        <td class="px-4 py-2 text-xs">{{ collect(['Base' => $unit->is_base, 'Stock' => $unit->is_stock, 'Purchase' => $unit->is_purchase, 'Transfer' => $unit->is_transfer, 'Sales' => $unit->is_sales])->filter()->keys()->implode(', ') ?: '-' }}{{ $unit->flag_active ? '' : ' · nonaktif' }}</td>
                                        <td class="px-4 py-2 font-mono text-xs">{{ $unit->esb_product_detail_id ?: '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="{{ $sectionClass }}">
                    <h3 class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">Riwayat & Perubahan</h3>
                    <ol class="space-y-3">
                        @forelse ($this->auditTrail as $log)
                            <li class="rounded-lg border border-gray-100 px-3 py-2 text-sm dark:border-gray-800" wire:key="audit-{{ $log->id }}">
                                <p class="font-semibold text-gray-900 dark:text-white">{{ str($log->event)->headline() }}
                                    @if ($log->field_path)<span class="font-mono text-xs font-normal text-gray-500">{{ $log->field_path }}: {{ json_encode($log->before_value['v'] ?? null) }} → {{ json_encode($log->after_value['v'] ?? null) }}</span>@endif
                                    @if ($log->to_status)<span class="text-xs font-normal text-gray-500">{{ $log->from_status ? $log->from_status.' → ' : '' }}{{ $log->to_status }}</span>@endif
                                </p>
                                <p class="text-xs text-gray-500">{{ $log->actor?->name ?? 'Sistem' }} · {{ $jakarta($log->created_at) }}</p>
                                @if ($log->reason)<p class="mt-1 text-xs text-gray-700 dark:text-gray-300">{{ $log->reason }}</p>@endif
                            </li>
                        @empty
                            <li class="text-sm text-gray-500">Belum ada riwayat.</li>
                        @endforelse
                    </ol>
                </section>
            </div>

            <div class="space-y-6">
                <section class="{{ $sectionClass }}">
                    <h3 class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">Checklist IT</h3>
                    <ul class="space-y-2">
                        @foreach ($checklist as $item)
                            <li class="flex items-start gap-2 text-sm">
                                @if ($item['ok'])
                                    <x-heroicon-o-check-circle class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" aria-hidden="true" /><span class="sr-only">Lulus:</span>
                                @else
                                    <x-heroicon-o-exclamation-triangle class="mt-0.5 h-4 w-4 shrink-0 text-amber-600" aria-hidden="true" /><span class="sr-only">Perlu dicek:</span>
                                @endif
                                <span><span class="font-medium text-gray-900 dark:text-white">{{ $item['label'] }}</span>@if ($item['detail'])<span class="block text-xs text-gray-500">{{ $item['detail'] }}</span>@endif</span>
                            </li>
                        @endforeach
                        <li class="text-xs text-gray-500">Periksa juga kesesuaian nama/kode, UOM pembelian, flag unit, dan apakah produk harus ditautkan.</li>
                    </ul>
                </section>

                <section class="{{ $sectionClass }}">
                    <h3 class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">Kandidat Duplikasi</h3>
                    <p class="mb-2 text-xs text-gray-500">Lokal (kode, SKU, atau nama sama):</p>
                    <ul class="space-y-1 text-sm">
                        @forelse ($duplicates as $candidate)
                            <li class="font-mono text-xs">{{ $candidate->product_code }} — <span class="font-sans">{{ $candidate->product_name }}</span>{{ $candidate->esb_product_id ? ' · ESB #'.$candidate->esb_product_id : '' }}</li>
                        @empty
                            <li class="text-xs text-gray-500">Tidak ditemukan.</li>
                        @endforelse
                    </ul>
                    <p class="mb-2 mt-4 text-xs text-gray-500">ESB (hasil "Cari di ESB"; nama hanya indikator):</p>
                    <ul class="space-y-1 text-sm">
                        @forelse ($esbCandidates as $candidate)
                            <li class="font-mono text-xs">#{{ $candidate['productID'] }} {{ $candidate['productCode'] }} — <span class="font-sans">{{ $candidate['productName'] }}</span> <span class="text-gray-500">(cocok {{ $candidate['match'] }})</span></li>
                        @empty
                            <li class="text-xs text-gray-500">Belum dicari atau tidak ditemukan.</li>
                        @endforelse
                    </ul>
                </section>

                <section class="{{ $sectionClass }}">
                    <h3 class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">Versi Pengajuan</h3>
                    <ul class="space-y-2 text-sm">
                        @foreach ($this->reviewHistory as $history)
                            <li class="flex flex-wrap items-center gap-2" wire:key="review-{{ $history->id }}">
                                <span class="font-semibold">v{{ $history->submission_version }}</span>
                                <x-helpdesk.monitoring.status-badge :label="$history->status->getLabel()" :color="$history->status->getColor()" />
                                <span class="text-xs text-gray-500">{{ $jakarta($history->submitted_at) }}{{ $history->reviewer ? ' · '.$history->reviewer->name : '' }}{{ $history->consumed_at ? ' · approval dipakai' : '' }}</span>
                                @if ($history->reason)<span class="w-full text-xs text-gray-600 dark:text-gray-300">{{ $history->reason }}</span>@endif
                            </li>
                        @endforeach
                    </ul>
                </section>

                <section class="{{ $sectionClass }}">
                    <h3 class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">Notifikasi Telegram</h3>
                    <ul class="space-y-2 text-sm">
                        @forelse ($this->deliveries as $delivery)
                            <li class="flex flex-wrap items-center gap-2" wire:key="delivery-{{ $delivery->id }}">
                                <x-helpdesk.monitoring.status-badge :label="$delivery->status->getLabel()" :color="$delivery->status->getColor()" />
                                <span class="text-xs text-gray-500">{{ str($delivery->event_type)->headline() }} · {{ $delivery->attempts }} percobaan</span>
                                @if ($delivery->error_summary)<span class="w-full text-xs text-gray-600 dark:text-gray-300">{{ $delivery->error_summary }}</span>@endif
                                @if ($delivery->status === \App\Enums\NotificationDeliveryStatus::Failed && \Illuminate\Support\Facades\Gate::allows('retryNotification', $material))
                                    <button type="button" wire:click="retryDelivery({{ $delivery->id }})" wire:loading.attr="disabled" class="rounded-lg border border-gray-300 px-2 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200">Kirim Ulang</button>
                                @endif
                            </li>
                        @empty
                            <li class="text-xs text-gray-500">Belum ada notifikasi.</li>
                        @endforelse
                    </ul>
                </section>
            </div>
        </div>
    </div>
</x-filament-panels::page>
