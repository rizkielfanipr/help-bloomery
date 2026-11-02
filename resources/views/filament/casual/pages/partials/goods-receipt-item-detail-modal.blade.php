<div
    x-data="{ show: @js($activeItemIndex !== null) }"
    x-on:open-modal.window="$event.detail == 'item-detail' ? show = true : null"
    x-on:close-modal.window="$event.detail == 'item-detail' ? show = false : null"
    x-on:keydown.escape.window="show && $wire.closeItemDetail()"
    x-show="show"
    x-cloak
    role="dialog"
    aria-modal="true"
    aria-label="Detail Pemeriksaan Produk"
    class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0"
>
    <div x-show="show" x-on:click="$wire.closeItemDetail()" class="fixed inset-0 bg-gray-500/75"></div>
    <div x-show="show" class="relative mx-auto mb-6 max-h-[90vh] overflow-y-auto rounded-2xl bg-white shadow-xl sm:max-w-lg dark:bg-gray-900">
    @if ($activeItemIndex !== null)
        @php($index = $activeItemIndex)
        @php($item = $items[$index] ?? null)
        @if ($item)
        @php($qcPreview = $this->itemQcPreview($index))
        <article wire:key="qc-item-{{ $index }}" class="p-5">
            <div class="mb-3 flex items-start justify-between gap-3">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">{{ $item['productName'] }}</h3>
                <button type="button" wire:click="closeItemDetail" aria-label="Tutup" class="shrink-0 text-slate-400 hover:text-slate-600"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg></button>
            </div>
            <div class="mb-4 rounded-xl border border-blue-100 bg-blue-50 px-4 py-3 dark:border-blue-900 dark:bg-blue-950/30">
                <span class="{{ $infoHeading }} text-blue-700 dark:text-blue-300">Informasi Pengisian</span>
                <p class="mt-1 text-xs leading-5 text-blue-800 dark:text-blue-300">Isi qty fisik hasil hitung, timbang, atau ukur, lalu bagi seluruhnya ke Accepted, Hold, dan Rejected. Jumlah ketiganya harus sama dengan qty fisik. Hanya qty Accepted yang dikirim ke ESB.</p>
            </div>
            <div class="mt-4 rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">2. Quantity Check</p>
                <span class="mt-2 {{ $infoHeading }} text-slate-500">Informasi Pengisian</span>
                <p class="mt-1 text-xs leading-5 text-slate-500">Masukkan qty yang benar-benar ditemukan.@unless($withoutPurchaseOrder ?? false) Toleransi variance adalah batas selisih terhadap sisa qty PO; nilai di atas batas akan gagal QC.@endunless</p>
                <div class="mt-3 grid gap-4">
                <label class="{{ $label }}">Qty fisik<input wire:model="items.{{ $index }}.physicalQty" type="number" step="0.0001" class="{{ $field }}"></label>
                <label class="{{ $label }}">Accepted<input wire:model="items.{{ $index }}.acceptedQty" type="number" step="0.0001" class="{{ $field }}"></label>
                <label class="{{ $label }}">Hold<input wire:model.live.debounce.300ms="items.{{ $index }}.holdQty" type="number" step="0.0001" class="{{ $field }}"></label>
                <label class="{{ $label }}">Rejected<input wire:model.live.debounce.300ms="items.{{ $index }}.rejectedQty" type="number" step="0.0001" class="{{ $field }}"></label>
                <label class="{{ $label }}">Metode<select wire:model="items.{{ $index }}.measurementMethod" class="{{ $field }}"><option value="count">Hitung</option><option value="weigh">Timbang</option><option value="measure">Ukur</option></select></label>
                @unless($withoutPurchaseOrder ?? false)<label class="{{ $label }}">Toleransi variance %<input wire:model="items.{{ $index }}.tolerancePercentage" type="number" step="0.01" class="{{ $field }}"></label>@endunless
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
                    {{-- docs/products/inventory/receiving-simplification/prd.md §9 "Untuk satu batch, form cukup berisi: Nomor
                         Batch, Tanggal ED, Qty Batch" — Tanggal Produksi and the Accepted/Hold/Rejected split
                         live behind "Detail Batch", collapsed by default. --}}
                    <div class="mt-3 grid gap-3 rounded-xl bg-gray-50 p-3 dark:bg-gray-800" x-data="{ detailOpen: false }">
                        <p class="text-xs font-semibold text-slate-600 dark:text-slate-300">Batch {{ $batchIndex + 1 }}</p>
                        <label class="{{ $label }}">Nomor Batch <span class="ml-1 font-normal normal-case text-slate-400">(opsional)</span><input wire:model="items.{{ $index }}.batches.{{ $batchIndex }}.batchNumber" class="{{ $field }}"></label>
                        <label class="{{ $label }}">Tanggal ED<input wire:model.live="items.{{ $index }}.batches.{{ $batchIndex }}.expiredDate" type="date" class="{{ $field }}"></label>
                        <label class="{{ $label }}">Qty Batch<input wire:model="items.{{ $index }}.batches.{{ $batchIndex }}.quantity" type="number" step="0.0001" class="{{ $field }}"></label>
                        <button type="button" x-on:click="detailOpen = ! detailOpen" class="text-left text-xs font-semibold text-blue-600" x-text="detailOpen ? 'Sembunyikan Detail Batch' : 'Detail Batch'"></button>
                        <div x-show="detailOpen" x-cloak class="grid gap-3 border-t border-gray-200 pt-3 dark:border-gray-700">
                            <label class="{{ $label }}">Tanggal produksi<input wire:model.live="items.{{ $index }}.batches.{{ $batchIndex }}.manufacturedDate" type="date" class="{{ $field }}"></label>
                            <label class="{{ $label }}">Qty Accepted batch<input wire:model="items.{{ $index }}.batches.{{ $batchIndex }}.acceptedQty" type="number" step="0.0001" class="{{ $field }}"></label>
                            <label class="{{ $label }}">Qty Hold batch<input wire:model="items.{{ $index }}.batches.{{ $batchIndex }}.holdQty" type="number" step="0.0001" class="{{ $field }}"></label>
                            <label class="{{ $label }}">Qty Rejected batch<input wire:model="items.{{ $index }}.batches.{{ $batchIndex }}.rejectedQty" type="number" step="0.0001" class="{{ $field }}"></label>
                        </div>
                        @php($batchPreview = $qcPreview['batches'][$batchIndex] ?? [])
                        <div class="rounded-xl border px-3 py-3 {{ ($batchPreview['shelfLifePercentage'] ?? null) !== null && $batchPreview['shelfLifePercentage'] >= (float) $item['minimumShelfLifePercentage'] ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/20 dark:text-emerald-300' : 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950/20 dark:text-amber-300' }}">
                            <span class="block text-[11px] font-bold uppercase tracking-wide">Hasil Perhitungan Shelf Life</span>
                            @if (($batchPreview['shelfLifePercentage'] ?? null) === null)
                                <span class="mt-1 block text-sm font-semibold">Lengkapi Tanggal ED dan Tanggal Produksi di Detail Batch</span>
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
                        @if (! empty($item['storedEvidencePhotos']))
                                <p class="mt-1.5 text-xs font-medium text-emerald-600 dark:text-emerald-400">{{ count($item['storedEvidencePhotos']) }} foto sudah tersimpan</p>
                            @endif
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
                <p class="mt-1 text-xs leading-5 text-violet-700 dark:text-violet-300">@if ($withoutPurchaseOrder ?? false)Tidak perlu diisi manual. Vendor Compliance Incident dan poin demerit dibuat saat Goods Receipt diproses dengan supplier resmi dari PO; penerimaan yang seluruhnya Rejected tetap tercatat lokal.@else Tidak perlu diisi manual. Saat qty Rejected disimpan, sistem otomatis membuat Vendor Compliance Incident, menghitung poin demerit sesuai kategori masalah, dan mengirim notifikasi kepada tim Purchasing untuk proses klaim atau retur.@endif</p>
            </div>
            @error("items.$index.physicalQty")<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
            @error("items.$index.acceptedQty")<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
            @error("items.$index.rejectionReason")<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
            @error("items.$index.actualTemperature")<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
            @error("items.$index.batches")<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
            <button type="button" wire:click="closeItemDetail" class="mt-4 w-full rounded-xl bg-blue-600 py-2.5 text-sm font-semibold text-white">Selesai</button>
        </article>
        @endif
    @endif
    </div>
</div>
