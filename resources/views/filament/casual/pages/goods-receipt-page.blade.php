@php
    $branch = auth()->user()?->branch?->name;
    $field = 'mt-1.5 w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-slate-700 focus:border-blue-400 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-slate-200';
    $label = 'text-xs font-semibold capitalize text-slate-600 dark:text-slate-300';
    $help = 'mt-3 rounded-xl border border-blue-100 bg-blue-50 px-4 py-3 text-xs leading-5 text-blue-800 dark:border-blue-900 dark:bg-blue-950/30 dark:text-blue-200';
    $infoHeading = 'mb-1 block text-[11px] font-bold uppercase tracking-wide';
@endphp

<div class="flex min-h-dvh flex-col bg-blue-600 dark:bg-blue-900">
    <header class="px-5 pb-8 pt-14">
        <div class="flex items-center gap-3">
            @if ($purchaseOrder)
                <button wire:click="backToList" type="button" aria-label="Kembali ke daftar Purchase Order" class="flex h-9 w-9 items-center justify-center rounded-full bg-white/20 text-white transition hover:bg-white/30 active:scale-95">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $this->iconPath('arrow-left') }}"/></svg>
                </button>
            @else
                <a href="{{ \App\Filament\Casual\Pages\LauncherPage::getUrl() }}" aria-label="Kembali ke halaman aplikasi" class="flex h-9 w-9 items-center justify-center rounded-full bg-white/20 text-white transition hover:bg-white/30 active:scale-95">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $this->iconPath('arrow-left') }}"/></svg>
                </a>
            @endif
            <div><h1 class="font-semibold text-white">Penerimaan & QC Inbound</h1><p class="text-sm text-blue-200">{{ $branch ?? 'Tanpa Cabang' }}</p></div>
        </div>
    </header>

    <main class="flex-1 rounded-t-3xl bg-gray-50 pb-24 pt-6 dark:bg-gray-950">
        <div class="flex flex-col gap-5 px-5">
            @if (! $purchaseOrder)
                <section class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-sm font-bold text-slate-800 dark:text-white">Daftar Purchase Order</h2>
                            <p class="mt-0.5 text-xs text-slate-400">Data Authorized dan Receiving dari ESB</p>
                        </div>
                        <button wire:click="loadPurchaseOrders" wire:loading.attr="disabled" wire:target="loadPurchaseOrders" type="button" class="flex shrink-0 items-center gap-1.5 rounded-lg bg-blue-50 px-3 py-2 text-xs font-semibold text-blue-700 transition hover:bg-blue-100 disabled:opacity-50 dark:bg-blue-950/40 dark:text-blue-300 dark:hover:bg-blue-950/60">
                        <svg wire:loading.remove wire:target="loadPurchaseOrders" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $this->iconPath('sync') }}"/></svg>
                        <svg wire:loading wire:target="loadPurchaseOrders" class="h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3"/><path class="opacity-75" fill="currentColor" d="M12 3a9 9 0 00-9 9h3a6 6 0 016-6V3z"/></svg>
                            <span wire:loading.remove wire:target="loadPurchaseOrders">Sync ESB</span>
                            <span wire:loading wire:target="loadPurchaseOrders">Sync...</span>
                        </button>
                    </div>
                    <div class="flex items-center rounded-xl border border-gray-200 bg-gray-50 p-1 transition focus-within:border-blue-300 focus-within:bg-white focus-within:ring-4 focus-within:ring-blue-50 dark:border-gray-700 dark:bg-gray-800 dark:focus-within:border-blue-700 dark:focus-within:bg-gray-900 dark:focus-within:ring-blue-950/30">
                        <svg class="ml-2.5 h-5 w-5 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $this->iconPath('search') }}"/></svg>
                        <input wire:model.live.debounce.300ms="search" wire:keydown.enter="searchPurchaseOrders" type="search" placeholder="Cari nomor PO, vendor, atau cabang" class="min-w-0 flex-1 border-0 bg-transparent px-3 py-2.5 text-sm text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-0 dark:text-slate-200">
                        <button wire:click="searchPurchaseOrders" type="button" aria-label="Cari Purchase Order" title="Cari" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-blue-600 text-white transition hover:bg-blue-700 active:scale-95">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $this->iconPath('search') }}"/></svg>
                        </button>
                    </div>
                    <span wire:loading wire:target="loadPurchaseOrders" class="mt-2 block text-[11px] text-blue-500">Menyinkronkan Data ESB...</span>
                </section>
                @if ($loadError)<div class="rounded-xl bg-red-50 p-3 text-xs text-red-700">{{ $loadError }}</div>@endif
                @php
                    $filteredPurchaseOrders = $this->filteredPurchaseOrders();
                    $purchaseOrderTotal = $filteredPurchaseOrders->count();
                    $purchaseOrderLastPage = max(1, (int) ceil($purchaseOrderTotal / 10));
                    $visiblePurchaseOrders = $filteredPurchaseOrders->slice(($purchaseOrderPage - 1) * 10, 10);
                @endphp
                @forelse ($visiblePurchaseOrders as $po)
                    <article class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm transition hover:border-blue-200 hover:shadow-md dark:border-gray-700 dark:bg-gray-900 dark:hover:border-blue-800">
                        <div class="flex items-start gap-3 p-4">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-950/40 dark:text-blue-300">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $this->iconPath('purchase-order') }}"/></svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="truncate text-sm font-bold text-slate-900 dark:text-white">{{ $po['purchaseNum'] }}</h2>
                                    <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">{{ $po['statusName'] ?? 'Authorized' }}</span>
                                </div>
                                <div class="mt-2 space-y-1.5">
                                    <div class="flex items-start gap-2 text-xs"><span class="w-14 shrink-0 text-slate-400">Vendor</span><span class="font-semibold text-slate-700 dark:text-slate-200">{{ $po['supplierName'] ?? '-' }}</span></div>
                                    <div class="flex items-start gap-2 text-xs"><span class="w-14 shrink-0 text-slate-400">Cabang</span><span class="font-medium text-slate-600 dark:text-slate-300">{{ $po['branchName'] ?? '-' }}</span></div>
                                </div>
                            </div>
                            <button data-po="{{ $po['purchaseNum'] }}" wire:click="selectPurchaseOrder($event.currentTarget.dataset.po)" wire:loading.attr="disabled" type="button" aria-label="Buat GR & QC untuk {{ $po['purchaseNum'] }}" title="Buat GR & QC" class="flex shrink-0 flex-col items-center gap-1 rounded-xl bg-blue-600 px-3 py-2.5 text-white transition hover:bg-blue-700 active:scale-95 disabled:opacity-50">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $this->iconPath('create') }}"/></svg>
                                <span class="text-[10px] font-semibold">Buat GR</span>
                            </button>
                        </div>
                        <div class="grid grid-cols-2 divide-x divide-gray-100 border-t border-gray-100 bg-gray-50/70 px-4 py-3 dark:divide-gray-800 dark:border-gray-800 dark:bg-gray-800/40">
                            <div class="pr-3"><p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Tanggal PO</p><p class="mt-0.5 text-xs font-semibold text-slate-700 dark:text-slate-200">{{ filled($po['purchaseDate'] ?? null) ? \Illuminate\Support\Carbon::parse($po['purchaseDate'])->locale('id')->isoFormat('D MMM Y') : '-' }}</p></div>
                            <div class="pl-3"><p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Dibutuhkan</p><p class="mt-0.5 text-xs font-semibold text-slate-700 dark:text-slate-200">{{ filled($po['requiredDate'] ?? null) ? \Illuminate\Support\Carbon::parse($po['requiredDate'])->locale('id')->isoFormat('D MMM Y') : '-' }}</p></div>
                        </div>
                    </article>
                @empty
                    <div class="rounded-2xl border border-dashed p-8 text-center text-sm text-slate-500">Tidak ada PO Authorized/Receiving.</div>
                @endforelse
                @if ($purchaseOrderTotal > 0)
                    <nav aria-label="Pagination Purchase Order" class="flex items-center justify-center gap-4 py-2">
                        <button wire:click="goToPurchaseOrderPage({{ $purchaseOrderPage - 1 }})" wire:loading.attr="disabled" type="button" @disabled($purchaseOrderPage <= 1) aria-label="Halaman Sebelumnya" class="flex h-10 w-10 items-center justify-center rounded-full border border-gray-200 bg-white text-slate-500 shadow-sm transition hover:border-blue-300 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-30 dark:border-gray-700 dark:bg-gray-900 dark:text-slate-300">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $this->iconPath('arrow-left') }}"/></svg>
                        </button>
                        <div class="min-w-24 text-center">
                            <p class="text-sm font-bold text-slate-700 dark:text-slate-200">{{ $purchaseOrderPage }} / {{ $purchaseOrderLastPage }}</p>
                            <p class="text-[11px] text-slate-400">{{ $purchaseOrderTotal }} PO</p>
                            <span class="sr-only">Halaman {{ $purchaseOrderPage }} dari {{ $purchaseOrderLastPage }}. Menampilkan {{ (($purchaseOrderPage - 1) * 10) + 1 }}–{{ min($purchaseOrderPage * 10, $purchaseOrderTotal) }} dari {{ $purchaseOrderTotal }} PO</span>
                        </div>
                        <button wire:click="goToPurchaseOrderPage({{ $purchaseOrderPage + 1 }})" wire:loading.attr="disabled" type="button" @disabled($purchaseOrderPage >= $purchaseOrderLastPage) aria-label="Halaman Berikutnya" class="flex h-10 w-10 items-center justify-center rounded-full bg-blue-600 text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-30">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $this->iconPath('arrow-right') }}"/></svg>
                        </button>
                    </nav>
                @endif
            @else
                <section class="overflow-hidden rounded-2xl border border-blue-100 bg-white shadow-sm dark:border-blue-900 dark:bg-gray-900">
                    <div class="bg-gradient-to-r from-blue-600 to-blue-500 px-5 py-4 text-white">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex min-w-0 items-center gap-3">
                                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white/15">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5V5.625a3.375 3.375 0 00-3.375-3.375H8.25m6.375 6-3.75-3.75M4.5 12.75h15m-15 3h15m-15 3h9.75A2.25 2.25 0 0016.5 16.5V6.75A2.25 2.25 0 0014.25 4.5h-7.5A2.25 2.25 0 004.5 6.75v12z"/></svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-[11px] font-bold uppercase tracking-wider text-blue-100">Nomor Purchase Order</p>
                                    <h2 class="truncate text-lg font-bold">{{ $purchaseOrder['purchaseNum'] }}</h2>
                                </div>
                            </div>
                            <span class="shrink-0 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold ring-1 ring-white/20">{{ $purchaseOrder['statusName'] ?? 'Authorized' }}</span>
                        </div>
                    </div>
                    <div class="divide-y divide-gray-100 px-5 dark:divide-gray-800">
                        <div class="flex items-start gap-3 py-3.5">
                            <svg class="mt-0.5 h-5 w-5 shrink-0 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m4.5-18v18m7.5-18v18m4.5-18v18M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75"/></svg>
                            <div><p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Vendor / Supplier</p><p class="mt-0.5 text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $purchaseOrder['supplierName'] ?? '-' }}</p></div>
                        </div>
                        <div class="flex items-start gap-3 py-3.5">
                            <svg class="mt-0.5 h-5 w-5 shrink-0 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3s-4.5 4.03-4.5 9 2.015 9 4.5 9zm0-18a9.004 9.004 0 018.716 6.747M12 3a9.004 9.004 0 00-8.716 6.747m17.432 0A17.98 17.98 0 0112 12c-3.183 0-6.172-.824-8.716-2.253m17.432 0a9.026 9.026 0 010 4.506m0 0A17.98 17.98 0 0012 12c-3.183 0-6.172.824-8.716 2.253"/></svg>
                            <div><p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Cabang Penerima</p><p class="mt-0.5 text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $purchaseOrder['branchName'] ?? $branch ?? '-' }}</p></div>
                        </div>
                        <div class="grid grid-cols-2 divide-x divide-gray-100 py-3.5 dark:divide-gray-800">
                            <div class="pr-3"><p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Tanggal PO</p><p class="mt-1 text-sm font-semibold text-slate-800 dark:text-slate-100">{{ filled($purchaseOrder['purchaseDate'] ?? null) ? \Illuminate\Support\Carbon::parse($purchaseOrder['purchaseDate'])->locale('id')->isoFormat('D MMM Y') : '-' }}</p></div>
                            <div class="pl-3"><p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Tanggal Dibutuhkan</p><p class="mt-1 text-sm font-semibold text-slate-800 dark:text-slate-100">{{ filled($purchaseOrder['requiredDate'] ?? null) ? \Illuminate\Support\Carbon::parse($purchaseOrder['requiredDate'])->locale('id')->isoFormat('D MMM Y') : '-' }}</p></div>
                        </div>
                        <div class="flex items-center justify-between py-3.5">
                            <div><p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Produk Untuk Diperiksa</p><p class="mt-0.5 text-xs text-slate-500">Item dengan sisa penerimaan</p></div>
                            <span class="rounded-full bg-blue-50 px-3 py-1.5 text-sm font-bold text-blue-700 dark:bg-blue-950/40 dark:text-blue-300">{{ count($items) }} Produk</span>
                        </div>
                    </div>
                </section>

                <section class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
                    <h3 class="font-semibold text-slate-900 dark:text-white">1. Document Check</h3>
                    <div class="{{ $help }}">
                        <span class="{{ $infoHeading }}">Informasi Pengisian</span>
                        Isi data berdasarkan dokumen fisik dari vendor. Centang kesesuaian hanya setelah nomor, tanggal, qty, dan harga dibandingkan dengan PO. Jika ada dokumen belum tersedia atau tidak sesuai, lampirkan foto bukti; seluruh qty barang harus ditempatkan sebagai Hold atau Rejected.
                    </div>
                    <div class="mt-4 grid gap-4">
                        <label class="{{ $label }}">Tanggal penerimaan<input wire:model.live="goodsReceiptDate" type="date" class="{{ $field }}"></label>
                        <label class="{{ $label }}">Lokasi ESB<select wire:model="locationId" class="{{ $field }}"><option value="">Pilih lokasi</option>@foreach ($locations as $location)<option value="{{ $location['locationID'] }}">{{ $location['locationName'] }}</option>@endforeach</select></label>
                        <label class="{{ $label }}">Nomor surat jalan / DO<input wire:model="deliveryNumber" type="text" class="{{ $field }}"></label>
                        <label class="{{ $label }}">Tanggal surat jalan<input wire:model="deliveryDate" type="date" class="{{ $field }}"></label>
                        <label class="{{ $label }}">Status invoice<select wire:model.live="invoiceStatus" class="{{ $field }}"><option value="received">Diterima</option><option value="not_received">Belum diterima</option></select></label>
                        @if ($invoiceStatus === 'received')
                            <label class="{{ $label }}">Nomor invoice<input wire:model="invoiceNumber" type="text" class="{{ $field }}"></label>
                            <label class="{{ $label }}">Tanggal invoice<input wire:model="invoiceDate" type="date" class="{{ $field }}"></label>
                        @endif
                        <div class="grid gap-3 rounded-xl border border-gray-200 p-4 text-sm text-slate-700 dark:border-gray-700 dark:text-slate-300">
                            <label class="flex items-center gap-2 capitalize"><input wire:model.live="poDocumentMatch" type="checkbox"> PO Sesuai</label>
                            <label class="flex items-center gap-2 capitalize"><input wire:model.live="deliveryDocumentMatch" type="checkbox"> Surat Jalan Sesuai</label>
                            <label class="flex items-center gap-2 capitalize"><input wire:model.live="invoiceDocumentMatch" type="checkbox"> Invoice Sesuai</label>
                            <label class="flex items-center gap-2 capitalize"><input wire:model.live="priceMatch" type="checkbox"> Harga Sesuai Kontrak</label>
                        </div>
                        <label class="{{ $label }}">Catatan dokumen<textarea wire:model="documentNotes" rows="2" class="{{ $field }}"></textarea></label>
                        @if ($invoiceStatus === 'not_received' || ! $poDocumentMatch || ! $deliveryDocumentMatch || ! $invoiceDocumentMatch || ! $priceMatch)
                        <div>
                            <label class="{{ $label }}">Foto bukti dokumen <span class="ml-1 font-normal normal-case text-slate-400">(maks. 5 MB/foto)</span></label>
                            @if (count($documentEvidencePhotos) > 0)
                                <div class="mb-2 mt-1.5 space-y-2">
                                    @foreach ($documentEvidencePhotos as $photoIndex => $photo)
                                        <div class="flex items-center justify-between gap-2 rounded-xl border border-gray-200 px-3 py-2 dark:border-gray-700">
                                            <div class="flex min-w-0 items-center gap-2">
                                                <svg class="h-4 w-4 shrink-0 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941-7.81 7.81a1.5 1.5 0 002.112 2.13"/></svg>
                                                <span class="truncate text-xs text-slate-600 dark:text-slate-300">{{ $photo->getClientOriginalName() }}</span>
                                            </div>
                                            <button type="button" wire:click="removeDocumentEvidencePhoto({{ $photoIndex }})" class="shrink-0 text-red-400 transition hover:text-red-600" aria-label="Hapus foto"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg></button>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                            <label class="mt-1.5 flex cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-gray-300 py-4 transition hover:border-blue-300 hover:bg-blue-50 dark:border-gray-700 dark:hover:border-blue-600 dark:hover:bg-blue-900/20">
                                <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/></svg>
                                <span class="text-sm text-gray-400">Tambah Foto / Screenshot</span>
                                <input wire:model="documentEvidencePhotos" type="file" multiple accept="image/jpeg,image/png,image/webp" class="hidden">
                            </label>
                            <p wire:loading wire:target="documentEvidencePhotos" class="mt-1.5 text-xs text-blue-500">Mengunggah foto...</p>
                        </div>
                        @endif
                    </div>
                    @foreach (['goodsReceiptDate','locationId','deliveryNumber','deliveryDate','invoiceNumber','invoiceDate','documentEvidencePhotos'] as $key) @error($key)<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror @endforeach
                </section>

                <section class="flex flex-col gap-4">
                    @foreach ($items as $index => $item)
                        @php($qcPreview = $this->itemQcPreview($index))
                        <article wire:key="qc-item-{{ $index }}" class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
                            <div class="mb-4 rounded-xl border border-blue-100 bg-blue-50 px-4 py-3 dark:border-blue-900 dark:bg-blue-950/30">
                                <span class="{{ $infoHeading }} text-blue-700 dark:text-blue-300">Informasi Pengisian</span>
                                <p class="mt-1 text-xs leading-5 text-blue-800 dark:text-blue-300">Periksa setiap produk secara terpisah. Isi qty fisik hasil hitung, timbang, atau ukur, lalu bagi seluruhnya ke Accepted, Hold, dan Rejected. Jumlah ketiganya harus sama dengan qty fisik. Hanya qty Accepted yang dikirim ke ESB.</p>
                            </div>
                            <label class="flex items-start gap-3"><input wire:model="items.{{ $index }}.selected" type="checkbox" class="mt-1"><span><strong class="block text-sm text-slate-900 dark:text-white">{{ $item['productName'] }}</strong><span class="text-xs text-slate-500">{{ $item['productCode'] ?: 'Tanpa kode' }} · Sisa {{ number_format($item['outstandingQty'], 4, ',', '.') }} {{ $item['uomName'] }}</span></span></label>
                            <div class="mt-4 rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                                <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">2. Quantity Check</p>
                                <span class="mt-2 {{ $infoHeading }} text-slate-500">Informasi Pengisian</span>
                                <p class="mt-1 text-xs leading-5 text-slate-500">Masukkan qty yang benar-benar ditemukan. Toleransi variance adalah batas selisih terhadap sisa qty PO; nilai di atas batas akan gagal QC.</p>
                                <div class="mt-3 grid gap-4">
                                <label class="{{ $label }}">Qty fisik<input wire:model="items.{{ $index }}.physicalQty" type="number" step="0.0001" class="{{ $field }}"></label>
                                <label class="{{ $label }}">Accepted<input wire:model="items.{{ $index }}.acceptedQty" type="number" step="0.0001" class="{{ $field }}"></label>
                                <label class="{{ $label }}">Hold<input wire:model.live.debounce.300ms="items.{{ $index }}.holdQty" type="number" step="0.0001" class="{{ $field }}"></label>
                                <label class="{{ $label }}">Rejected<input wire:model.live.debounce.300ms="items.{{ $index }}.rejectedQty" type="number" step="0.0001" class="{{ $field }}"></label>
                                <label class="{{ $label }}">Metode<select wire:model="items.{{ $index }}.measurementMethod" class="{{ $field }}"><option value="count">Hitung</option><option value="weigh">Timbang</option><option value="measure">Ukur</option></select></label>
                                <label class="{{ $label }}">Toleransi variance %<input wire:model="items.{{ $index }}.tolerancePercentage" type="number" step="0.01" class="{{ $field }}"></label>
                                </div>
                            </div>
                            <div class="mt-4 rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                                <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">3. Quality Check</p>
                                <span class="mt-2 {{ $infoHeading }} text-slate-500">Informasi Pengisian</span>
                                <p class="mt-1 text-xs leading-5 text-slate-500">Pilih Tidak sesuai jika warna, tekstur, kemasan, atau kondisi kebersihan tidak memenuhi standar. Gunakan N/A hanya bila pemeriksaan tersebut memang tidak berlaku untuk produknya.</p>
                                <div class="mt-3 grid gap-4">
                                @foreach (['colorResult'=>'Warna','textureResult'=>'Tekstur','packagingResult'=>'Kemasan','contaminationResult'=>'Kontaminasi'] as $key => $title)
                                    <label class="{{ $label }}">{{ $title }}<select wire:model="items.{{ $index }}.{{ $key }}" class="{{ $field }}"><option value="pass">Sesuai</option><option value="fail">Tidak sesuai</option><option value="not_applicable">N/A</option></select></label>
                                @endforeach
                                </div>
                            </div>
                            <div class="mt-4 rounded-xl bg-blue-50 p-3 dark:bg-blue-950/20">
                                <p class="text-sm font-semibold text-blue-700 dark:text-blue-300">3.1 Cold Chain Check</p>
                                <span class="mt-2 {{ $infoHeading }} text-blue-700 dark:text-blue-300">Informasi Pengisian</span>
                                <p class="mt-1 text-xs leading-5 text-blue-700 dark:text-blue-300">Untuk produk chilled atau frozen, isi suhu aktual saat diterima dan rentang suhu yang diperbolehkan. Suhu di luar rentang otomatis gagal QC.</p>
                                <div class="mt-3 grid gap-4">
                                    <label class="{{ $label }}">Kategori<select wire:model.live="items.{{ $index }}.temperatureCategory" class="{{ $field }}"><option value="ambient">Ambient</option><option value="chilled">Chilled</option><option value="frozen">Frozen</option></select></label>
                                    @if ($item['temperatureCategory'] !== 'ambient')
                                        <label class="{{ $label }}">Suhu aktual °C<input wire:model="items.{{ $index }}.actualTemperature" type="number" step="0.1" class="{{ $field }}"></label>
                                        <label class="{{ $label }}">Min °C<input wire:model="items.{{ $index }}.minTemperature" type="number" step="0.1" class="{{ $field }}"></label>
                                        <label class="{{ $label }}">Maks °C<input wire:model="items.{{ $index }}.maxTemperature" type="number" step="0.1" class="{{ $field }}"></label>
                                    @endif
                                </div>
                            </div>
                            <div class="mt-4 rounded-xl border border-gray-200 p-3 dark:border-gray-700">
                                <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">3.2 Shelf Life & Batch Check</p>
                                <span class="mt-2 {{ $infoHeading }} text-slate-500">Informasi Pengisian</span>
                                <p class="mt-2 text-xs leading-5 text-slate-500">Aktifkan untuk produk yang memiliki batch atau tanggal kedaluwarsa. Isi tanggal produksi dan expired agar sistem menghitung persentase sisa shelf life; standar awalnya minimum 80%.</p>
                                <label class="mt-3 flex items-center gap-2 text-sm font-semibold capitalize text-slate-700 dark:text-slate-200"><input wire:model.live="items.{{ $index }}.shelfLifeRequired" type="checkbox"> Produk Memakai Batch/Expiry</label>
                                @if ($item['shelfLifeRequired'])
                                <label class="mt-3 block {{ $label }}">Minimum sisa shelf life %<input wire:model.live.debounce.300ms="items.{{ $index }}.minimumShelfLifePercentage" type="number" class="{{ $field }}"></label>
                                @foreach ($item['batches'] as $batchIndex => $batch)
                                    <div class="mt-3 grid gap-3 rounded-xl bg-gray-50 p-3 dark:bg-gray-800">
                                        <p class="text-xs font-semibold text-slate-600 dark:text-slate-300">Batch {{ $batchIndex + 1 }}</p>
                                        <label class="{{ $label }}">Nomor batch<input wire:model="items.{{ $index }}.batches.{{ $batchIndex }}.batchNumber" class="{{ $field }}"></label>
                                        <label class="{{ $label }}">Tanggal produksi<input wire:model.live="items.{{ $index }}.batches.{{ $batchIndex }}.manufacturedDate" type="date" class="{{ $field }}"></label>
                                        <label class="{{ $label }}">Tanggal kedaluwarsa<input wire:model.live="items.{{ $index }}.batches.{{ $batchIndex }}.expiredDate" type="date" class="{{ $field }}"></label>
                                        <label class="{{ $label }}">Qty fisik batch<input wire:model="items.{{ $index }}.batches.{{ $batchIndex }}.quantity" type="number" step="0.0001" class="{{ $field }}"></label>
                                        <label class="{{ $label }}">Qty Accepted batch<input wire:model="items.{{ $index }}.batches.{{ $batchIndex }}.acceptedQty" type="number" step="0.0001" class="{{ $field }}"></label>
                                        <label class="{{ $label }}">Qty Hold batch<input wire:model="items.{{ $index }}.batches.{{ $batchIndex }}.holdQty" type="number" step="0.0001" class="{{ $field }}"></label>
                                        <label class="{{ $label }}">Qty Rejected batch<input wire:model="items.{{ $index }}.batches.{{ $batchIndex }}.rejectedQty" type="number" step="0.0001" class="{{ $field }}"></label>
                                        @php($batchPreview = $qcPreview['batches'][$batchIndex] ?? [])
                                        <div class="rounded-xl border px-3 py-3 {{ ($batchPreview['shelfLifePercentage'] ?? null) !== null && $batchPreview['shelfLifePercentage'] >= (float) $item['minimumShelfLifePercentage'] ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/20 dark:text-emerald-300' : 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950/20 dark:text-amber-300' }}">
                                            <span class="block text-[11px] font-bold uppercase tracking-wide">Hasil Perhitungan Shelf Life</span>
                                            @if (($batchPreview['shelfLifePercentage'] ?? null) === null)
                                                <span class="mt-1 block text-sm font-semibold">Lengkapi Tanggal Produksi dan Kedaluwarsa</span>
                                            @else
                                                <span class="mt-1 block text-lg font-bold">{{ number_format($batchPreview['shelfLifePercentage'], 2, ',', '.') }}%</span>
                                                <span class="text-xs">{{ $batchPreview['shelfLifePercentage'] >= (float) $item['minimumShelfLifePercentage'] ? 'Lulus' : 'Gagal' }} · Minimum {{ number_format((float) $item['minimumShelfLifePercentage'], 2, ',', '.') }}%</span>
                                            @endif
                                        </div>
                                        <button wire:click="removeBatch({{ $index }}, {{ $batchIndex }})" type="button" class="text-xs font-semibold text-red-600">Hapus batch</button>
                                    </div>
                                @endforeach
                                <button wire:click="addBatch({{ $index }})" type="button" class="mt-3 text-xs font-semibold text-blue-600">+ Tambah batch</button>
                                @if (count($item['batches']) > 0)
                                    <div class="mt-3 flex items-center justify-between rounded-xl border border-gray-200 px-3 py-3 dark:border-gray-700">
                                        <span class="text-xs font-semibold text-slate-600 dark:text-slate-300">Status Shelf Life Item</span>
                                        <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $qcPreview['shelfLifeResult'] === 'pass' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' : 'bg-red-100 text-red-700 dark:bg-red-950/40 dark:text-red-300' }}">{{ $qcPreview['shelfLifeResult'] === 'pass' ? 'Lulus' : 'Gagal' }}</span>
                                    </div>
                                @endif
                                @endif
                            </div>
                            <div class="mt-4 rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                                <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">3.3 Sampling Test</p>
                                <span class="mt-2 {{ $infoHeading }} text-slate-500">Informasi Pengisian</span>
                                <p class="mt-1 text-xs leading-5 text-slate-500">Aktifkan untuk bahan baku kritis atau sensitif. Qty tidak dapat Accepted selama hasil masih Pending atau jika hasilnya Fail.</p>
                                <div class="mt-3 grid gap-4">
                                <label class="text-sm font-semibold capitalize text-slate-700 dark:text-slate-200"><input wire:model.live="items.{{ $index }}.samplingRequired" type="checkbox"> Sampling Test Diperlukan</label>
                                @if ($item['samplingRequired'])
                                    <label class="{{ $label }}">Hasil sampling<select wire:model="items.{{ $index }}.samplingResult" class="{{ $field }}"><option value="pass">Pass</option><option value="fail">Fail</option><option value="pending">Pending</option></select></label>
                                    <label class="{{ $label }}">Metode sampling<input wire:model="items.{{ $index }}.samplingMethod" class="{{ $field }}"></label>
                                    <label class="{{ $label }}">Catatan sampling<input wire:model="items.{{ $index }}.samplingNotes" class="{{ $field }}"></label>
                                @endif
                                </div>
                            </div>
                            @if ((float) $item['holdQty'] > 0 || (float) $item['rejectedQty'] > 0)
                            <div class="mt-4 rounded-xl bg-amber-50 p-3 dark:bg-amber-950/20">
                                <p class="text-sm font-semibold text-amber-800 dark:text-amber-300">4. Rejection & Hold Process</p>
                                <span class="mt-2 {{ $infoHeading }} text-amber-700 dark:text-amber-300">Informasi Pengisian</span>
                                <p class="mt-1 text-xs leading-5 text-amber-800 dark:text-amber-300">Pindahkan barang ke area karantina terlebih dahulu. Isi lokasi, kategori penyebab, alasan rinci, dan unggah foto. Qty Rejected otomatis menjadi demerit vendor dan memberi notifikasi kepada Purchasing.</p>
                                <div class="mt-3 grid gap-4">
                                    <label class="{{ $label }}">Lokasi quarantine<input wire:model="items.{{ $index }}.quarantineLocation" class="{{ $field }}"></label>
                                    <label class="{{ $label }}">Kategori<select wire:model="items.{{ $index }}.rejectionCategory" class="{{ $field }}"><option value="">Pilih</option>@foreach (['document'=>'Dokumen','quantity'=>'Quantity','quality'=>'Quality','packaging'=>'Kemasan','cold_chain'=>'Cold chain','shelf_life'=>'Shelf life','sampling'=>'Sampling','other'=>'Lainnya'] as $value => $text)<option value="{{ $value }}">{{ $text }}</option>@endforeach</select></label>
                                    <label class="{{ $label }}">Alasan<textarea wire:model="items.{{ $index }}.rejectionReason" class="{{ $field }}"></textarea></label>
                                    <div>
                                        <label class="{{ $label }}">Foto bukti <span class="ml-1 font-normal normal-case text-slate-400">(maks. 5 MB/foto)</span></label>
                                        @if (count($item['evidencePhotos']) > 0)
                                            <div class="mb-2 mt-1.5 space-y-2">
                                                @foreach ($item['evidencePhotos'] as $photoIndex => $photo)
                                                    <div class="flex items-center justify-between gap-2 rounded-xl border border-gray-200 px-3 py-2 dark:border-gray-700">
                                                        <div class="flex min-w-0 items-center gap-2">
                                                            <svg class="h-4 w-4 shrink-0 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941-7.81 7.81a1.5 1.5 0 002.112 2.13"/></svg>
                                                            <span class="truncate text-xs text-slate-600 dark:text-slate-300">{{ $photo->getClientOriginalName() }}</span>
                                                        </div>
                                                        <button type="button" wire:click="removeItemEvidencePhoto({{ $index }}, {{ $photoIndex }})" class="shrink-0 text-red-400 transition hover:text-red-600" aria-label="Hapus foto"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg></button>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                        <label class="mt-1.5 flex cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-gray-300 py-4 transition hover:border-blue-300 hover:bg-blue-50 dark:border-gray-700 dark:hover:border-blue-600 dark:hover:bg-blue-900/20">
                                            <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/></svg>
                                            <span class="text-sm text-gray-400">Tambah Foto / Screenshot</span>
                                            <input wire:model="items.{{ $index }}.evidencePhotos" type="file" multiple accept="image/jpeg,image/png,image/webp" class="hidden">
                                        </label>
                                        <p wire:loading wire:target="items.{{ $index }}.evidencePhotos" class="mt-1.5 text-xs text-blue-500">Mengunggah foto...</p>
                                    </div>
                                </div>
                            </div>
                            @endif
                            <div class="mt-4 rounded-xl border border-violet-100 bg-violet-50 p-4 dark:border-violet-900 dark:bg-violet-950/20">
                                <p class="text-sm font-semibold text-violet-800 dark:text-violet-300">5. Feedback Loop ke Purchasing</p>
                                <span class="mt-2 {{ $infoHeading }} text-violet-700 dark:text-violet-300">Informasi Proses Otomatis</span>
                                <p class="mt-1 text-xs leading-5 text-violet-700 dark:text-violet-300">Tidak perlu diisi manual. Saat qty Rejected disimpan, sistem otomatis membuat Vendor Compliance Incident, menghitung poin demerit sesuai kategori masalah, dan mengirim notifikasi kepada tim Purchasing untuk proses klaim atau retur.</p>
                            </div>
                            @error("items.$index.physicalQty")<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
                            @error("items.$index.acceptedQty")<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
                            @error("items.$index.rejectionReason")<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
                            @error("items.$index.actualTemperature")<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
                            @error("items.$index.batches")<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
                        </article>
                    @endforeach
                </section>
                <section class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
                    <h3 class="font-semibold text-slate-900 dark:text-white">6. Konfirmasi Penerimaan</h3>
                    <div class="{{ $help }}"><span class="{{ $infoHeading }}">Informasi Pengisian</span>Tambahkan informasi operasional jika diperlukan. Aktifkan Tutup PO otomatis hanya jika tidak ada sisa barang yang masih akan diterima dari PO ini.</div>
                    <label class="mt-4 block {{ $label }}">Informasi tambahan<textarea wire:model="additionalInfo" rows="2" class="{{ $field }}"></textarea></label>
                    <div class="mt-4 rounded-xl border border-amber-100 bg-amber-50 px-4 py-3 text-xs leading-5 text-amber-800 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200">
                        <span class="{{ $infoHeading }}">Informasi Tutup PO</span>
                        Aktifkan hanya jika penerimaan ini merupakan pengiriman terakhir, seluruh qty sudah selesai diterima, dan tidak ada barang Hold, Rejected, pengganti, atau pengiriman susulan. Jika masih ada proses lanjutan, biarkan tidak aktif agar PO tetap dapat diterima kembali.
                    </div>
                    <label class="mt-3 flex items-center gap-2 text-sm capitalize text-slate-700 dark:text-slate-300"><input wire:model="autoClosePo" type="checkbox"> Tutup PO Otomatis</label>
                </section>
                @error('items')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                <button wire:click="submit" wire:confirm="Simpan hasil QC dan kirim qty Accepted ke ESB?" wire:loading.attr="disabled" type="button" class="w-full rounded-2xl bg-blue-600 py-4 text-sm font-semibold text-white disabled:opacity-60">
                    <span wire:loading.remove wire:target="submit">Simpan QC & Proses Goods Receipt</span><span wire:loading wire:target="submit">Memproses...</span>
                </button>
            @endif
        </div>
    </main>
    <x-filament-actions::modals />
</div>
