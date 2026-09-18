@php
    $fulfillment = $record->fulfillment;
    $status = $fulfillment?->status ?? \App\Enums\MarketingMaterialFulfillmentStatus::NotStarted;
@endphp

<div class="space-y-4">
    <div class="flex items-start justify-between gap-2">
        <div>
            <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $record->title }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                {{ \App\Models\RndProjectMarketingMaterial::TYPES[$record->type] ?? $record->type }}
                &middot; Diupload oleh {{ $record->creator?->name ?? '—' }}
            </p>
        </div>
        <x-filament::badge :color="$status->getColor()">{{ $status->getLabel() }}</x-filament::badge>
    </div>

    @if ($record->isImage())
        <img src="{{ $record->fileUrl() }}" alt="{{ $record->title }}" class="max-h-64 w-full rounded-lg border border-gray-200 object-contain dark:border-gray-700">
    @endif

    <a href="{{ $record->downloadUrl() }}" target="_blank"
        class="inline-flex items-center gap-1 text-xs font-medium text-primary-600 hover:underline dark:text-primary-400">
        <x-heroicon-o-arrow-down-tray class="h-3.5 w-3.5" />
        Download File Asli ({{ $record->original_name }})
    </a>

    @if ($record->notes)
        <p class="text-xs text-gray-600 dark:text-gray-300">{{ $record->notes }}</p>
    @endif

    <div class="space-y-3 border-t border-gray-200 pt-3 dark:border-gray-700">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Pemesanan (Purchasing)</p>
            @if ($fulfillment?->vendor_name)
                <dl class="mt-1 grid grid-cols-2 gap-x-4 gap-y-1 text-xs text-gray-600 dark:text-gray-300 sm:grid-cols-3">
                    <div><dt class="text-gray-400">Vendor</dt><dd>{{ $fulfillment->vendor_name }}</dd></div>
                    <div><dt class="text-gray-400">Tanggal Pesan</dt><dd>{{ $fulfillment->order_date?->translatedFormat('d M Y') ?? '—' }}</dd></div>
                    <div><dt class="text-gray-400">Estimasi Selesai</dt><dd>{{ $fulfillment->estimated_completion_date?->translatedFormat('d M Y') ?? '—' }}</dd></div>
                    <div><dt class="text-gray-400">Dipesan Oleh</dt><dd>{{ $fulfillment->orderedBy?->name ?? '—' }}</dd></div>
                </dl>
                @if ($fulfillment->purchasing_notes)
                    <p class="mt-1 text-xs text-gray-600 dark:text-gray-300">{{ $fulfillment->purchasing_notes }}</p>
                @endif
            @else
                <p class="mt-1 text-xs text-gray-400">Belum diproses Purchasing.</p>
            @endif
        </div>

        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Penerimaan (Inventory)</p>
            @if ($fulfillment?->received_date)
                <dl class="mt-1 grid grid-cols-2 gap-x-4 gap-y-1 text-xs text-gray-600 dark:text-gray-300 sm:grid-cols-3">
                    <div><dt class="text-gray-400">Jumlah</dt><dd>{{ $fulfillment->received_quantity ?? '—' }}</dd></div>
                    <div><dt class="text-gray-400">Tanggal Terima</dt><dd>{{ $fulfillment->received_date?->translatedFormat('d M Y') ?? '—' }}</dd></div>
                    <div><dt class="text-gray-400">Lokasi</dt><dd>{{ $fulfillment->location ? "{$fulfillment->location->branch->name} — {$fulfillment->location->code}" : '—' }}</dd></div>
                    <div><dt class="text-gray-400">Diterima Oleh</dt><dd>{{ $fulfillment->receivedBy?->name ?? '—' }}</dd></div>
                </dl>
                @if ($fulfillment->inventory_notes)
                    <p class="mt-1 text-xs text-gray-600 dark:text-gray-300">{{ $fulfillment->inventory_notes }}</p>
                @endif
            @else
                <p class="mt-1 text-xs text-gray-400">Belum diproses Inventory.</p>
            @endif
        </div>
    </div>
</div>
