@php
    $field = 'mt-1.5 w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-slate-700 focus:border-blue-400 focus:outline-none disabled:cursor-not-allowed disabled:bg-gray-100 dark:border-gray-700 dark:bg-gray-900 dark:text-slate-200 dark:disabled:bg-gray-800';
    $label = 'text-xs font-semibold capitalize text-slate-600 dark:text-slate-300';
    $help = 'mt-3 rounded-xl border border-blue-100 bg-blue-50 px-4 py-3 text-xs leading-5 text-blue-800 dark:border-blue-900 dark:bg-blue-950/30 dark:text-blue-200';
    $infoHeading = 'mb-1 block text-[11px] font-bold uppercase tracking-wide';
    $error = 'mt-1.5 text-xs text-red-600';
    $withoutPurchaseOrder = true;
    $photoGroups = [
        ['title' => 'Foto Dokumen', 'hint' => 'Surat Jalan/Invoice', 'stored' => $storedDocumentPhotos, 'uploads' => $documentPhotos, 'model' => 'documentPhotos', 'removeStored' => 'removeStoredDocumentPhoto', 'removeUpload' => 'removeDocumentPhoto'],
        ['title' => 'Foto Barang', 'hint' => 'Kondisi barang saat diterima', 'stored' => $storedGoodsPhotos, 'uploads' => $goodsPhotos, 'model' => 'goodsPhotos', 'removeStored' => 'removeStoredGoodsPhoto', 'removeUpload' => 'removeGoodsPhoto'],
    ];
@endphp

<div x-data="{ uploadingCount: 0, cancelId: null, cancelReason: '' }"
     x-on:livewire-upload-start.window="uploadingCount++"
     x-on:livewire-upload-finish.window="uploadingCount--"
     x-on:livewire-upload-error.window="uploadingCount--"
     x-on:livewire-upload-cancel.window="uploadingCount--"
     class="flex min-h-dvh flex-col bg-blue-600 dark:bg-blue-900">
    <header class="px-5 pb-8 pt-14">
        <div class="flex items-center gap-3">
            @if ($formOpen)
                <button wire:click="closeForm" type="button" aria-label="Kembali ke daftar penerimaan" class="flex h-9 w-9 items-center justify-center rounded-full bg-white/20 text-white transition hover:bg-white/30 active:scale-95"><x-heroicon-o-arrow-left class="h-5 w-5" /></button>
            @else
                <a href="{{ \App\Filament\Casual\Pages\LauncherPage::getUrl() }}" aria-label="Kembali ke halaman aplikasi" class="flex h-9 w-9 items-center justify-center rounded-full bg-white/20 text-white transition hover:bg-white/30 active:scale-95"><x-heroicon-o-arrow-left class="h-5 w-5" /></a>
            @endif
            <div><h1 class="font-semibold text-white">Penerimaan Tanpa PO</h1><p class="text-sm text-blue-200">Company {{ 'BLSS' }}</p></div>
        </div>
    </header>

    <main class="flex-1 rounded-t-3xl bg-gray-50 pb-24 pt-6 dark:bg-gray-950">
        <div class="flex flex-col gap-5 px-5">
            @if ($loadError)<div class="rounded-xl bg-red-50 p-3 text-xs text-red-700">{{ $loadError }}</div>@endif

            @if (! $formOpen)
                @include('filament.casual.pages.partials.receiving-mode-switch', ['activeMode' => 'non_po'])
                <section class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <h2 class="text-sm font-bold text-slate-800 dark:text-white">Barang datang sebelum PO tersedia</h2>
                    <div class="{{ $help }}">
                        <span class="{{ $infoHeading }}">Informasi Proses Otomatis</span>
                        Data disimpan lokal dan diteruskan ke Purchasing. Stok ESB baru bertambah setelah PO dibuat dan Goods Receipt berhasil, jadi barang tetap hold dan tidak boleh dipakai sebelum itu.
                    </div>
                    <button wire:click="openForm" wire:loading.attr="disabled" wire:target="openForm" type="button" class="mt-3 w-full rounded-xl bg-blue-600 py-3 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:opacity-60">Catat Penerimaan Tanpa PO</button>
                </section>

                @forelse ($this->receipts as $receipt)
                    @php($latestNote = $receipt->statusHistories->first()?->notes)
                    <article wire:key="receipt-{{ $receipt->id }}" class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h3 class="break-words text-sm font-bold text-slate-900 dark:text-white">{{ $receipt->receipt_number ?? 'Draft belum dikirim' }}</h3>
                                <p class="mt-0.5 text-xs text-slate-500">{{ $receipt->supplier_text ?: 'Supplier belum diisi' }} &middot; {{ $receipt->receipt_date->locale('id')->isoFormat('D MMM Y') }}</p>
                            </div>
                            <x-filament::badge :color="$receipt->status->getColor()" class="shrink-0">{{ $receipt->status->getLabel() }}</x-filament::badge>
                        </div>
                        <dl class="mt-3 space-y-1 text-xs">
                            <div class="flex gap-2"><dt class="w-16 shrink-0 text-slate-400">Cabang</dt><dd class="min-w-0 break-words text-slate-600 dark:text-slate-300">{{ $receipt->branch_name ?: '-' }}</dd></div>
                            <div class="flex gap-2"><dt class="w-16 shrink-0 text-slate-400">Produk</dt><dd class="text-slate-600 dark:text-slate-300">{{ $receipt->items_count }} item</dd></div>
                            @if ($receipt->po_number)<div class="flex gap-2"><dt class="w-16 shrink-0 text-slate-400">PO</dt><dd class="min-w-0 break-words text-slate-600 dark:text-slate-300">{{ $receipt->po_number }}</dd></div>@endif
                            @if ($receipt->goodsReceipt?->esb_goods_receipt_number)<div class="flex gap-2"><dt class="w-16 shrink-0 text-slate-400">GR</dt><dd class="min-w-0 break-words text-slate-600 dark:text-slate-300">{{ $receipt->goodsReceipt->esb_goods_receipt_number }}</dd></div>@endif
                            @if ($latestNote)<div class="flex gap-2"><dt class="w-16 shrink-0 text-slate-400">Catatan</dt><dd class="min-w-0 break-words text-slate-600 dark:text-slate-300">{{ $latestNote }}</dd></div>@endif
                        </dl>
                        @if ($receipt->status->canBeEditedByInventory())
                            <div class="mt-3 flex gap-2">
                                <button wire:click="editReceipt({{ $receipt->id }})" type="button" class="flex-1 rounded-xl bg-blue-600 py-2 text-xs font-semibold text-white">{{ $receipt->status === \App\Enums\NonPoReceiptStatus::Draft ? 'Lanjutkan Draft' : 'Perbaiki' }}</button>
                                <button x-on:click="cancelId = {{ $receipt->id }}; cancelReason = ''" type="button" class="rounded-xl border border-red-200 px-3 py-2 text-xs font-semibold text-red-600">Batalkan</button>
                            </div>
                        @endif
                    </article>
                @empty
                    <div class="rounded-2xl border border-dashed p-8 text-center text-sm text-slate-500">Belum ada penerimaan tanpa PO.</div>
                @endforelse

                <div x-show="cancelId !== null" x-cloak role="dialog" aria-modal="true" aria-label="Batalkan penerimaan" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div x-on:click="cancelId = null" class="absolute inset-0 bg-slate-950/55"></div>
                    <div class="relative w-full max-w-md rounded-2xl bg-white p-5 shadow-xl dark:bg-gray-900">
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">Batalkan Penerimaan</h3>
                        <label class="mt-3 block {{ $label }}">Alasan Atau Bukti Retur <span class="text-red-500">*</span><textarea x-model="cancelReason" rows="3" class="{{ $field }}"></textarea></label>
                        <div class="mt-4 flex gap-2">
                            <button x-on:click="cancelId = null" type="button" class="flex-1 rounded-xl border border-gray-200 py-2.5 text-sm font-semibold text-slate-600 dark:border-gray-700 dark:text-slate-300">Tutup</button>
                            <button x-on:click="$wire.cancelReceipt(cancelId, cancelReason); cancelId = null" :disabled="cancelReason.trim() === ''" type="button" class="flex-1 rounded-xl bg-red-600 py-2.5 text-sm font-semibold text-white disabled:opacity-50">Batalkan</button>
                        </div>
                    </div>
                </div>
            @else
                @if ($revisionNote)
                    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950/20 dark:text-amber-300">
                        <span class="{{ $infoHeading }}">Perbaikan Diminta Purchasing</span>{{ $revisionNote }}
                    </div>
                @endif

                <section class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
                    <h3 class="font-semibold text-slate-900 dark:text-white">1. Informasi Penerimaan</h3>
                    <div class="{{ $help }}"><span class="{{ $infoHeading }}">Informasi Pengisian</span>Company terkunci BLSS. Supplier ditulis sesuai dokumen; supplier resmi akan mengikuti PO yang dibuat Purchasing.</div>
                    <div class="mt-4 grid gap-4">
                        <label class="{{ $label }}">Company Code<input type="text" value="BLSS" disabled class="{{ $field }}"></label>
                        <label class="{{ $label }}">Tanggal Penerimaan <span class="text-red-500">*</span><input wire:model.live="goodsReceiptDate" type="date" class="{{ $field }}"></label>
                        <div>
                            <label class="{{ $label }}">Cabang ESB <span class="text-red-500">*</span><select wire:model.live="branchId" class="{{ $field }}"><option value="">Pilih cabang</option>@foreach ($branches as $branch)<option value="{{ $branch['branchID'] }}">{{ $branch['branchCode'] ?? '' }} · {{ $branch['branchName'] ?? '' }}</option>@endforeach</select></label>
                            @error('branchId')<p class="{{ $error }}">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="{{ $label }}">Lokasi ESB <span class="text-red-500">*</span><select wire:model="locationId" @disabled(blank($branchId)) class="{{ $field }}"><option value="">{{ blank($branchId) ? 'Pilih cabang terlebih dahulu' : 'Pilih lokasi' }}</option>@foreach ($locations as $location)<option value="{{ $location['locationID'] }}">{{ $location['locationName'] }}</option>@endforeach</select></label>
                            @error('locationId')<p class="{{ $error }}">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="{{ $label }}">Supplier <span class="text-red-500">*</span><input wire:model="supplierText" type="text" class="{{ $field }}"></label>
                            @error('supplierText')<p class="{{ $error }}">{{ $message }}</p>@enderror
                        </div>
                        <label class="{{ $label }}">Kontak Supplier<input wire:model="supplierContact" type="text" class="{{ $field }}"></label>
                        <div>
                            <label class="{{ $label }}">Alasan Tanpa PO <span class="text-red-500">*</span><textarea wire:model="reason" rows="2" class="{{ $field }}"></textarea></label>
                            @error('reason')<p class="{{ $error }}">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </section>

                <section class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
                    <h3 class="font-semibold text-slate-900 dark:text-white">2. Dokumen Dan Foto</h3>
                    <div class="mt-4 grid gap-4">
                        <label class="{{ $label }}">Jenis Dokumen<select wire:model.live="documentType" class="{{ $field }}"><option value="delivery_note">Surat Jalan</option><option value="invoice">Invoice</option></select></label>
                        <div>
                            <label class="{{ $label }}">Nomor Dokumen <span class="text-red-500">*</span><input wire:model="documentNumber" type="text" class="{{ $field }}"></label>
                            @error('documentNumber')<p class="{{ $error }}">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="{{ $label }}">Tanggal Dokumen <span class="text-red-500">*</span><input wire:model="documentDate" type="date" class="{{ $field }}"></label>
                            @error('documentDate')<p class="{{ $error }}">{{ $message }}</p>@enderror
                        </div>
                        <label class="flex items-center gap-2 rounded-xl border border-gray-200 p-3 text-sm text-slate-700 dark:border-gray-700 dark:text-slate-300"><input wire:model.live="documentMatch" type="checkbox"> Dokumen Sesuai Barang</label>
                        <label class="{{ $label }}">Catatan Dokumen<textarea wire:model="documentNotes" rows="2" class="{{ $field }}"></textarea></label>
                        @foreach ($photoGroups as $group)
                            <div wire:key="photo-group-{{ $group['model'] }}">
                                <p class="{{ $label }}">{{ $group['title'] }} <span class="text-red-500">*</span> <span class="ml-1 font-normal normal-case text-slate-400">({{ $group['hint'] }} &middot; JPEG/PNG/WebP, maks. 5 MB/foto)</span></p>
                                <div class="mt-1.5 space-y-2">
                                    @foreach ($group['stored'] as $photoIndex => $path)
                                        <div wire:key="{{ $group['model'] }}-stored-{{ $photoIndex }}" class="flex items-center justify-between gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 dark:border-emerald-900 dark:bg-emerald-950/20">
                                            <span class="min-w-0 truncate text-xs font-medium text-slate-700 dark:text-slate-200">Foto tersimpan {{ $photoIndex + 1 }}</span>
                                            <button type="button" wire:click="{{ $group['removeStored'] }}({{ $photoIndex }})" aria-label="Hapus foto tersimpan {{ $photoIndex + 1 }}" class="shrink-0 text-red-400 hover:text-red-600"><x-heroicon-o-x-mark class="h-4 w-4" /></button>
                                        </div>
                                    @endforeach
                                    @foreach ($group['uploads'] as $photoIndex => $photo)
                                        <div wire:key="{{ $group['model'] }}-upload-{{ $photoIndex }}" class="flex items-center justify-between gap-2 rounded-xl border border-gray-200 px-3 py-2 dark:border-gray-700">
                                            <span class="min-w-0 truncate text-xs text-slate-600 dark:text-slate-300">{{ $photo->getClientOriginalName() }}</span>
                                            <button type="button" wire:click="{{ $group['removeUpload'] }}({{ $photoIndex }})" aria-label="Hapus {{ $photo->getClientOriginalName() }}" class="shrink-0 text-red-400 hover:text-red-600"><x-heroicon-o-x-mark class="h-4 w-4" /></button>
                                        </div>
                                    @endforeach
                                </div>
                                <label class="mt-1.5 flex cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-gray-300 py-4 text-sm text-gray-400 transition hover:border-blue-300 hover:bg-blue-50 dark:border-gray-700 dark:hover:border-blue-600 dark:hover:bg-blue-900/20">
                                    <x-heroicon-o-arrow-up-tray class="h-5 w-5" /> Tambah {{ $group['title'] }}
                                    <input wire:model="{{ $group['model'] }}" type="file" multiple accept="image/jpeg,image/png,image/webp" class="hidden">
                                </label>
                                <p wire:loading wire:target="{{ $group['model'] }}" class="mt-1.5 text-xs text-blue-500">Mengunggah foto...</p>
                                @error($group['model'])<p class="{{ $error }}">{{ $message }}</p>@enderror
                                @error($group['model'].'.*')<p class="{{ $error }}">{{ $message }}</p>@enderror
                            </div>
                        @endforeach
                    </div>
                </section>

                <section class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                    <h3 class="font-semibold text-slate-900 dark:text-white">3. Produk Dan QC</h3>
                    <div class="{{ $help }}"><span class="{{ $infoHeading }}">Informasi Pengisian</span>Produk dipilih dari Master Product agar cocok dengan PO. Tandai Bermasalah bila ada qty Hold atau Rejected. Purchasing hanya perlu membuat PO untuk qty Accepted.</div>
                    <button wire:click="openProductPicker" type="button" class="mt-3 flex w-full items-center justify-center gap-2 rounded-xl border border-blue-200 bg-blue-50 py-2.5 text-sm font-semibold text-blue-700 transition hover:bg-blue-100 dark:border-blue-900 dark:bg-blue-950/30 dark:text-blue-300"><x-heroicon-o-plus class="h-4 w-4" /> Tambah Produk</button>
                    @error('items')<p class="{{ $error }}">{{ $message }}</p>@enderror
                </section>

                <section class="flex flex-col gap-2.5">
                    @foreach ($items as $index => $item)
                        <div wire:key="item-row-{{ $item['productDetailID'] }}" class="rounded-2xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="break-words text-sm font-semibold text-slate-900 dark:text-white">{{ $item['productName'] }}</p>
                                    <p class="text-xs text-slate-500">{{ $item['productCode'] ?: 'Tanpa kode' }} &middot; {{ $item['uomName'] }}</p>
                                </div>
                                <button type="button" wire:click="removeItem({{ $index }})" aria-label="Hapus {{ $item['productName'] }}" class="shrink-0 text-red-400 hover:text-red-600"><x-heroicon-o-trash class="h-4 w-4" /></button>
                            </div>
                            <div class="mt-2 grid grid-cols-2 gap-2">
                                <label class="text-xs text-slate-500">Qty Diterima<input wire:model="items.{{ $index }}.physicalQty" type="number" step="0.0001" min="0" class="mt-1 w-full rounded-lg border border-gray-200 px-2 py-1.5 text-xs text-slate-700 dark:border-gray-700 dark:bg-gray-800 dark:text-slate-200"></label>
                                <label class="text-xs text-slate-500">ED<input wire:model.live="items.{{ $index }}.expiryDate" type="date" class="mt-1 w-full rounded-lg border border-gray-200 px-2 py-1.5 text-xs text-slate-700 dark:border-gray-700 dark:bg-gray-800 dark:text-slate-200"></label>
                                <label class="col-span-2 text-xs text-slate-500">Harga Invoice <span class="text-slate-400">(opsional)</span><input wire:model="items.{{ $index }}.invoicePrice" type="number" step="0.0001" min="0" class="mt-1 w-full rounded-lg border border-gray-200 px-2 py-1.5 text-xs text-slate-700 dark:border-gray-700 dark:bg-gray-800 dark:text-slate-200"></label>
                            </div>
                            @foreach (['productDetailID', 'physicalQty', 'acceptedQty', 'rejectionReason', 'actualTemperature', 'batches'] as $itemField)
                                @error("items.{$index}.{$itemField}")<p class="{{ $error }}">{{ $message }}</p>@enderror
                            @endforeach
                            <div class="mt-2 flex items-center justify-between gap-2">
                                <div class="flex overflow-hidden rounded-lg border border-gray-200 text-xs font-semibold dark:border-gray-700">
                                    <button type="button" wire:click="setItemCondition({{ $index }}, 'ok')" class="px-2.5 py-1 {{ $item['condition'] === 'ok' ? 'bg-emerald-500 text-white' : 'bg-white text-slate-500 dark:bg-gray-900 dark:text-slate-400' }}">Sesuai</button>
                                    <button type="button" wire:click="openItemDetail({{ $index }})" class="px-2.5 py-1 {{ $item['condition'] === 'problem' ? 'bg-amber-500 text-white' : 'bg-white text-slate-500 dark:bg-gray-900 dark:text-slate-400' }}">Bermasalah</button>
                                </div>
                                @if ($item['condition'] === 'problem')
                                    <button type="button" wire:click="openItemDetail({{ $index }})" class="text-[11px] font-semibold text-blue-600">Detail QC &rarr;</button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </section>

                @include('filament.casual.pages.partials.goods-receipt-item-detail-modal')

                <section class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
                    <label class="block {{ $label }}">Informasi Tambahan<textarea wire:model="additionalInfo" rows="2" class="{{ $field }}"></textarea></label>
                </section>

                <p x-show="uploadingCount > 0" x-cloak class="text-center text-xs font-semibold text-amber-600">Menunggu foto selesai diunggah...</p>
                <div class="grid gap-2">
                    <button wire:click="saveDraft" wire:loading.attr="disabled" wire:target="saveDraft,submit" type="button" :disabled="uploadingCount > 0" class="w-full rounded-2xl border border-blue-200 bg-white py-3.5 text-sm font-semibold text-blue-700 disabled:opacity-60 dark:border-blue-900 dark:bg-gray-900 dark:text-blue-300">Simpan Draft</button>
                    <button wire:click="submit" wire:confirm="Kirim penerimaan ini? Data dikunci sampai Purchasing meminta perbaikan, dan barang tetap hold sampai Goods Receipt berhasil." wire:loading.attr="disabled" wire:target="saveDraft,submit" type="button" :disabled="uploadingCount > 0" class="w-full rounded-2xl bg-blue-600 py-4 text-sm font-semibold text-white disabled:opacity-60">
                        <span wire:loading.remove wire:target="submit">Kirim Ke Purchasing</span>
                        <span wire:loading wire:target="submit">Mengirim...</span>
                    </button>
                </div>

                @include('filament.helpdesk.partials.esb-product-picker-modal', [
                    'pickerTitle' => 'Tambah Produk',
                    'pickerSelectMethod' => 'addProduct',
                    'pickerCloseAction' => 'closeProductPicker',
                    'pickerSelectedProductIds' => collect($items)->pluck('productDetailID')->map(fn ($id) => (int) $id)->all(),
                ])
            @endif
        </div>
    </main>
    <x-filament-actions::modals />
</div>
