<x-filament-panels::page>
    @php
        $pricing = $this->pricing();
        $material = $pricing->material;
        $sectionClass = 'rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900 sm:p-6';
        $labelClass = 'text-xs font-semibold text-gray-500 dark:text-gray-400';
        $valueClass = 'mt-0.5 break-words text-sm font-medium text-gray-900 dark:text-white';
        $money = fn ($value) => $value === null ? '-' : 'Rp '.number_format((float) $value, 0, ',', '.');
        $jakarta = fn ($date) => $date ? $date->copy()->timezone('Asia/Jakarta')->format('d M Y H:i').' WIB' : '-';
    @endphp

    <div class="space-y-6">
        <section class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900 sm:p-6">
            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <div class="flex min-w-0 items-start gap-4">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-950/40 dark:text-blue-300"><x-heroicon-o-currency-dollar class="h-6 w-6" aria-hidden="true" /></div>
                    <div class="min-w-0">
                        <p class="font-mono text-xs font-bold uppercase tracking-wider text-blue-600 dark:text-blue-400">{{ $material->product_code }} · {{ $pricing->origin === 'wip' ? 'WIP' : 'Sourcing' }} · revisi {{ $pricing->revision }}</p>
                        <h2 class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ $material->product_name }}</h2>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Masuk antrean {{ $jakarta($pricing->queued_at) }}</p>
                    </div>
                </div>
                <x-helpdesk.monitoring.status-badge :label="$pricing->status->getLabel()" :color="$pricing->status->getColor()" class="text-sm" />
            </div>
            <div class="mt-4 flex items-start gap-2 rounded-lg border border-blue-100 bg-blue-50 px-3 py-2 text-sm text-blue-800 dark:border-blue-900 dark:bg-blue-950/30 dark:text-blue-200">
                <x-heroicon-o-information-circle class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
                <span><strong>Mode manual terkontrol.</strong> Aplikasi belum mengirim Pricelist ke ESB secara otomatis karena kontrak API resmi belum tersedia. Input harga di Master Pricelist ESB, lalu konfirmasi di sini dengan referensinya.</span>
            </div>
            @if ($pricing->conflict_reason)
                <p class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200"><strong>Konflik:</strong> {{ $pricing->conflict_reason }}</p>
            @endif
            @if ($pricing->failure_reason)
                <p class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-300">{{ $pricing->failure_reason }}</p>
            @endif
        </section>

        <div class="grid gap-6 xl:grid-cols-2">
            <section class="{{ $sectionClass }}">
                <h3 class="mb-4 text-sm font-semibold text-gray-900 dark:text-white">Produk (read-only)</h3>
                <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div><dt class="{{ $labelClass }}">Product ID ESB</dt><dd class="{{ $valueClass }} font-mono">{{ $pricing->esb_product_id ?? $material->esb_product_id ?? '-' }}</dd></div>
                    <div><dt class="{{ $labelClass }}">Product Detail ID</dt><dd class="{{ $valueClass }} font-mono">{{ $pricing->product_detail_id }}</dd></div>
                    <div><dt class="{{ $labelClass }}">UOM Target</dt><dd class="{{ $valueClass }}">{{ $pricing->uom_name ?? '-' }}</dd></div>
                    <div><dt class="{{ $labelClass }}">Kategori</dt><dd class="{{ $valueClass }}">{{ $material->category_name ?: '-' }}</dd></div>
                    <div><dt class="{{ $labelClass }}">Supplier Terpilih</dt><dd class="{{ $valueClass }}">{{ $pricing->supplier_name ?? ($pricing->origin === 'wip' ? 'Tidak berlaku (WIP)' : '-') }}</dd></div>
                    <div><dt class="{{ $labelClass }}">Sampel</dt><dd class="{{ $valueClass }}">{{ $pricing->sampleCycle ? 'Siklus #'.$pricing->sampleCycle->cycle_number.' disetujui '.$jakarta($pricing->sampleCycle->evaluated_at) : '-' }}</dd></div>
                </dl>
            </section>

            <section class="{{ $sectionClass }}">
                <h3 class="mb-4 text-sm font-semibold text-gray-900 dark:text-white">Harga</h3>
                <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="rounded-xl border border-dashed border-gray-300 p-3 dark:border-gray-600"><dt class="{{ $labelClass }}">Harga Penawaran Supplier</dt><dd class="{{ $valueClass }} text-gray-600 dark:text-gray-300">{{ $money($pricing->offer_price) }}</dd><p class="mt-1 text-[11px] text-gray-500">Referensi saja — bukan Pricelist resmi.</p></div>
                    <div class="rounded-xl border border-blue-200 bg-blue-50/40 p-3 dark:border-blue-900 dark:bg-blue-950/20"><dt class="{{ $labelClass }}">Harga Pricelist Resmi</dt><dd class="mt-0.5 text-lg font-bold text-gray-900 dark:text-white">{{ $money($pricing->price) }}</dd><p class="mt-1 text-[11px] text-gray-500">{{ $pricing->status === \App\Enums\MaterialPricingStatus::Priced ? 'Terverifikasi' : 'Belum terverifikasi' }}</p></div>
                    <div><dt class="{{ $labelClass }}">Company / Branch</dt><dd class="{{ $valueClass }}">{{ $pricing->company_code ?? '-' }}{{ $pricing->branch_scope ? ' · '.$pricing->branch_scope : '' }}</dd></div>
                    <div><dt class="{{ $labelClass }}">Mulai Berlaku</dt><dd class="{{ $valueClass }}">{{ $pricing->effective_date?->format('d M Y') ?? '-' }}</dd></div>
                    <div><dt class="{{ $labelClass }}">Draft oleh</dt><dd class="{{ $valueClass }}">{{ $pricing->drafter?->name ?? '-' }} · {{ $jakarta($pricing->drafted_at) }}</dd></div>
                    <div><dt class="{{ $labelClass }}">Verifikasi</dt><dd class="{{ $valueClass }}">{{ $pricing->verifier ? $pricing->verifier->name.' · '.$jakarta($pricing->verified_at).' · ref '.$pricing->external_reference : '-' }}</dd></div>
                    <div class="sm:col-span-2"><dt class="{{ $labelClass }}">Catatan Finance</dt><dd class="{{ $valueClass }}">{{ $pricing->finance_notes ?: '-' }}</dd></div>
                </dl>
            </section>
        </div>

        <section class="{{ $sectionClass }}">
            <h3 class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">Riwayat Product Pricelist Ini</h3>
            <ul class="divide-y divide-gray-100 text-sm dark:divide-gray-800">
                @foreach ($this->history as $item)
                    <li class="flex flex-wrap items-center gap-2 py-2" wire:key="pricing-{{ $item->id }}">
                        <span class="font-semibold">Revisi {{ $item->revision }}</span>
                        <x-helpdesk.monitoring.status-badge :label="$item->status->getLabel()" :color="$item->status->getColor()" />
                        <span class="text-xs text-gray-500">{{ $money($item->price) }} · Detail #{{ $item->product_detail_id }}{{ $item->external_reference ? ' · ref '.$item->external_reference : '' }}{{ $item->verifier ? ' · '.$item->verifier->name : '' }}</span>
                        @if ($item->id !== $pricing->id)<a href="{{ \App\Filament\Helpdesk\Resources\MaterialPricelists\MaterialPricelistResource::getUrl('view', ['record' => $item]) }}" class="text-xs font-semibold text-blue-600 hover:underline dark:text-blue-400">Buka</a>@endif
                    </li>
                @endforeach
            </ul>
        </section>
    </div>
</x-filament-panels::page>
