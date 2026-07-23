<x-filament-panels::page>
@php
    $status = $record->status;
    $typeLabel = $record->purchase_type?->getLabel() ?? '-';
@endphp
<x-helpdesk.desktop-detail-shell
    :code="$record->purchase_request_number ?: 'PR-'.str_pad((string) $record->id, 6, '0', STR_PAD_LEFT)"
    :title="$record->item_name"
    :subtitle="($record->division ?: 'No Division').' · '.($record->branch?->name ?? 'No Branch')"
    :status="$status->getLabel()"
    :status-color="$status->getColor()"
    meta-label="Tanggal Pengajuan"
    :meta-value="$record->created_at->format('d M Y, H:i')"
>
    <x-slot:main>
        <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-800">
            <h2 class="mb-5 font-bold">Detail Pengajuan</h2>
            <dl class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <div><dt class="text-xs text-gray-400">Pemohon</dt><dd class="mt-1 font-semibold">{{ $record->user?->name ?? '-' }}</dd></div>
                <div><dt class="text-xs text-gray-400">Branch</dt><dd class="mt-1 font-semibold">{{ $record->branch?->name ?? '-' }}</dd></div>
                <div><dt class="text-xs text-gray-400">Jumlah</dt><dd class="mt-1 text-xl font-bold">{{ number_format($record->quantity) }}</dd></div>
                <div><dt class="text-xs text-gray-400">Jenis Pembelian</dt><dd class="mt-1 font-semibold">{{ $typeLabel }}</dd></div>
                <div><dt class="text-xs text-gray-400">No. Item Journal</dt><dd class="mt-1 font-mono font-semibold">{{ $record->journal_item_number ?: '-' }}</dd></div>
                <div><dt class="text-xs text-gray-400">No. Purchase Request</dt><dd class="mt-1 font-mono font-semibold">{{ $record->purchase_request_number ?: '-' }}</dd></div>
                <div><dt class="text-xs text-gray-400">Diproses Oleh</dt><dd class="mt-1 font-semibold">{{ $record->processedBy?->name ?? 'Belum diproses' }}</dd></div>
                <div><dt class="text-xs text-gray-400">Terakhir Diperbarui</dt><dd class="mt-1 font-semibold">{{ $record->updated_at->format('d M Y, H:i') }}</dd></div>
            </dl>
            <div class="mt-6 border-t border-gray-100 pt-5 dark:border-gray-800">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Alasan Pembelian</p>
                <p class="mt-2 whitespace-pre-line text-sm leading-6 text-gray-700 dark:text-gray-300">{{ $record->purchase_reason }}</p>
            </div>
            @if($record->ecommerce_link)
                <a href="{{ $record->ecommerce_link }}" target="_blank" rel="noopener" class="mt-4 inline-flex rounded-xl bg-blue-50 px-4 py-2.5 text-sm font-semibold text-blue-700 hover:bg-blue-100 dark:bg-blue-950/30 dark:text-blue-300">Buka Link e-Commerce ↗</a>
            @endif
        </section>

        @if($record->attachment_paths)
            <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-800">
                <h2 class="mb-4 font-bold">Lampiran</h2>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($record->attachment_paths as $path)
                        <a href="{{ \Illuminate\Support\Facades\Storage::disk('b2')->temporaryUrl($path, now()->addHour()) }}" target="_blank" class="rounded-xl border border-gray-200 p-4 text-sm font-semibold hover:border-blue-400 dark:border-gray-700">↗ {{ basename($path) }}</a>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-800">
            <h2 class="mb-4 font-bold">Catatan Purchasing</h2>
            <div class="rounded-xl bg-gray-50 p-5 text-sm leading-6 text-gray-700 dark:bg-gray-800/60 dark:text-gray-300">{{ $record->admin_notes ?: 'Belum ada catatan.' }}</div>
        </section>
    </x-slot:main>

    <x-slot:aside>
        <section class="rounded-2xl bg-white p-6 shadow-lg ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-800">
            <h2 class="font-bold">Tindak Lanjut Purchasing</h2>
            <p class="mt-1 text-xs text-gray-400">Perbarui status dan catatan langsung dari halaman ini.</p>
            @can('edit purchase requests')
                <div class="mt-5 space-y-4">
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">Status</label>
                        <select wire:model="status" class="w-full rounded-xl border border-gray-300 bg-white px-3 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-900">
                            @foreach(\App\Enums\PurchaseRequestStatus::cases() as $option)<option value="{{ $option->value }}">{{ $option->getLabel() }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">Catatan Purchasing</label>
                        <textarea wire:model="adminNotes" rows="5" class="w-full rounded-xl border border-gray-300 bg-white px-3 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-900" placeholder="Tambahkan catatan proses pembelian..."></textarea>
                    </div>
                    <button wire:click="saveFollowUp" wire:loading.attr="disabled" class="w-full rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50">Simpan Tindak Lanjut</button>
                </div>
            @endcan
        </section>
        <section class="rounded-2xl bg-gradient-to-br from-blue-600 to-indigo-600 p-5 text-white shadow-lg shadow-blue-900/15">
            <p class="text-xs text-blue-100">Progress Pembelian</p>
            <div class="mt-4 space-y-3">
                @foreach(\App\Enums\PurchaseRequestStatus::cases() as $step)
                    <div class="flex items-center gap-3 text-sm">
                        <span class="h-2.5 w-2.5 rounded-full {{ $status === $step ? 'bg-white ring-4 ring-white/20' : 'bg-blue-400/60' }}"></span>
                        <span class="{{ $status === $step ? 'font-semibold' : 'text-blue-100/70' }}">{{ $step->getLabel() }}</span>
                    </div>
                @endforeach
            </div>
        </section>
    </x-slot:aside>
</x-helpdesk.desktop-detail-shell>
</x-filament-panels::page>
