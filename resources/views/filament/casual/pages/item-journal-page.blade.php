@php
    $statusLabels = ['submitting' => 'Mengirim', 'succeeded' => 'Berhasil', 'attachment_failed' => 'Attachment Gagal', 'verification_required' => 'Perlu Verifikasi'];
@endphp
<div class="flex min-h-dvh flex-col bg-blue-600 dark:bg-blue-900">
    <header class="flex-shrink-0 px-5 pb-8 pt-14 text-white">
        <div class="mb-4 flex items-center gap-3">
            <a href="{{ \App\Filament\Casual\Pages\LauncherPage::getUrl() }}" class="flex h-9 w-9 items-center justify-center rounded-full bg-white/20 transition active:bg-white/30"><x-heroicon-o-arrow-left class="h-5 w-5" /></a>
            <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/20"><x-heroicon-o-arrows-right-left class="h-5 w-5" /></div>
            <span class="text-base font-semibold">Item Journal</span>
        </div>
        <p class="text-blue-200">Quality Control</p>
        <p class="text-xl font-semibold">Penyesuaian Stok ESB</p>
    </header>
    <main class="flex-1 overflow-y-auto rounded-t-3xl bg-gray-50 px-5 pb-28 pt-6 dark:bg-gray-950">
        <div class="space-y-5">
        @can('create quality control item journals')<button type="button" wire:click="openForm" class="flex w-full items-center justify-center gap-2 rounded-2xl bg-blue-600 py-3.5 text-sm font-semibold text-white shadow-sm transition active:scale-[0.98]"><x-heroicon-o-plus class="h-5 w-5" /> Buat Item Journal</button>@endcan
        <section class="overflow-hidden rounded-2xl bg-white ring-1 ring-black/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="border-b border-gray-100 px-4 py-3 dark:border-gray-800"><h2 class="font-semibold text-gray-900 dark:text-white">Riwayat Item Journal</h2><p class="text-xs text-gray-400">50 transaksi terbaru</p></div>
            <div class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse($this->journals as $journal)
                    <article class="space-y-3 p-4" wire:key="journal-{{ $journal->id }}"><div class="flex items-start justify-between gap-3"><div><p class="font-semibold text-gray-900 dark:text-white">{{ $journal->item_journal_number ?: 'Menunggu nomor ESB' }}</p><p class="text-xs text-gray-500">{{ $journal->esb_branch_name ?: $journal->esb_branch_code }} · {{ $journal->esb_comcode }} · {{ $journal->location_name }}</p></div><span class="rounded-full bg-gray-100 px-2 py-1 text-[10px] font-bold text-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ $statusLabels[$journal->status] ?? $journal->status }}</span></div><div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500"><span>{{ $journal->journal_date->format('d M Y') }}</span><span>{{ $journal->details->count() }} produk</span><span>{{ $journal->attachments->count() }} attachment</span></div>@if($journal->last_error)<p class="rounded-lg bg-red-50 p-2 text-xs text-red-700">{{ $journal->last_error }}</p>@endif @if($journal->status === 'attachment_failed')<button wire:click="retryAttachments({{ $journal->id }})" class="text-xs font-bold text-blue-600">Coba Upload Lagi</button>@endif @if($journal->attachments->isNotEmpty() && $journal->item_journal_number && auth()->user()?->can('delete quality control item journal attachments'))<button wire:click="deleteAllAttachments({{ $journal->id }})" wire:confirm="Hapus seluruh attachment jurnal ini dari ESB?" class="ml-3 text-xs font-bold text-red-600">Hapus Semua Attachment</button>@endif</article>
                @empty<div class="px-5 py-12 text-center text-sm text-gray-400">Belum ada Item Journal.</div>@endforelse
            </div>
        </section>
        </div>
    </main>
    @if($formOpen)
        @php
            $fieldClass = 'w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm text-slate-700 placeholder-slate-300 focus:border-blue-400 focus:outline-none focus:ring-0 dark:border-gray-700 dark:bg-gray-900 dark:text-slate-200';
            $labelClass = 'mb-1.5 block text-xs font-semibold text-slate-600 dark:text-slate-300';
        @endphp
        <div class="fixed inset-0 z-50 flex flex-col overflow-y-auto bg-blue-600 dark:bg-blue-900">
            <div class="flex-shrink-0 px-5 pb-8 pt-10">
                <div class="mb-4 flex items-center gap-3">
                    <button type="button" wire:click="closeForm" class="flex h-9 w-9 items-center justify-center rounded-full bg-white/20 text-white transition active:bg-white/30"><x-heroicon-o-arrow-left class="h-5 w-5" /></button>
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/20 text-white"><x-heroicon-o-arrows-right-left class="h-5 w-5" /></div>
                    <span class="text-base font-semibold text-white">Buat Item Journal</span>
                </div>
                <p class="text-blue-200">Quality Control · Non-Template</p>
                <p class="text-xl font-semibold text-white">Penyesuaian Stok ESB</p>
            </div>

            <form wire:submit="submit" class="flex-1 rounded-t-3xl bg-gray-50 px-5 pb-10 pt-6 dark:bg-gray-950">
                <div class="space-y-5 rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
                    <div class="rounded-xl border border-blue-100 bg-blue-50 px-4 py-3 dark:border-blue-900 dark:bg-blue-950/30">
                        <div class="flex items-start gap-2"><x-heroicon-o-information-circle class="mt-0.5 h-4 w-4 shrink-0 text-blue-500" /><div class="text-xs leading-relaxed text-blue-700 dark:text-blue-300"><p class="font-semibold">Informasi Pengisian</p><p class="mt-1">Pilih Comcode untuk memuat seluruh cabang yang tersedia pada akun ESB. Qty positif menambah stok dan qty negatif mengurangi stok.</p></div></div>
                    </div>
                    @if($loadError)<p class="rounded-xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-300">{{ $loadError }}</p>@endif

                    <div><label class="{{ $labelClass }}">Tanggal Item Journal <span class="text-red-400">*</span></label><input type="date" wire:model="journalDate" class="{{ $fieldClass }}">@error('journalDate')<p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>@enderror</div>
                    <div><label class="{{ $labelClass }}">Company Code <span class="text-red-400">*</span></label><select wire:model.live="companyCode" class="{{ $fieldClass }}"><option value="">-- Pilih Company Code --</option>@foreach($this->companyOptions() as $code => $label)<option value="{{ $code }}">{{ $label }}</option>@endforeach</select>@error('companyCode')<p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>@enderror</div>
                    <div><label class="{{ $labelClass }}">Cabang <span class="text-red-400">*</span></label><select wire:model.live="branchId" wire:key="branch-{{ $companyCode }}" @disabled(blank($companyCode)) class="{{ $fieldClass }} disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-400 dark:disabled:bg-gray-800"><option value="">{{ blank($companyCode) ? '-- Pilih Company Code terlebih dahulu --' : '-- Pilih cabang --' }}</option>@foreach($esbBranches as $branch)<option value="{{ $branch['branchID'] }}">{{ $branch['branchCode'] }} · {{ $branch['branchName'] }}</option>@endforeach</select>@error('branchId')<p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>@enderror</div>
                    <div><label class="{{ $labelClass }}">Lokasi <span class="text-red-400">*</span></label><select wire:model="locationId" wire:key="location-{{ $companyCode }}-{{ $branchId }}" @disabled(blank($branchId)) class="{{ $fieldClass }} disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-400 dark:disabled:bg-gray-800"><option value="">{{ blank($branchId) ? '-- Pilih cabang terlebih dahulu --' : '-- Pilih lokasi --' }}</option>@foreach($locations as $location)<option value="{{ $location['locationID'] }}">{{ $location['locationName'] ?? 'Location '.$location['locationID'] }}</option>@endforeach</select>@error('locationId')<p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>@enderror</div>

                    <div class="border-t border-gray-100 pt-5 dark:border-gray-800">
                        <div class="mb-3"><p class="text-sm font-semibold text-gray-900 dark:text-white">Detail Produk</p><p class="text-xs text-gray-400">Pilih produk melalui daftar Master Product ESB, lalu tentukan purpose, qty, dan HPP.</p></div>
                        <div class="space-y-3">
                            @foreach($items as $index => $item)
                                <div class="space-y-3 rounded-xl border border-gray-200 p-4 dark:border-gray-700" wire:key="item-{{ $index }}">
                                    <div>
                                        <div class="mb-1.5 flex items-center justify-between gap-3">
                                            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300">Produk <span class="text-red-400">*</span></label>
                                            @if(count($items)>1)<button type="button" wire:click="removeItem({{ $index }})" class="inline-flex items-center gap-1 text-xs font-semibold text-red-500"><x-heroicon-o-trash class="h-3.5 w-3.5" /> Hapus</button>@endif
                                        </div>
                                        <button type="button" wire:click="openProductPicker({{ $index }})" @disabled(blank($companyCode)) class="flex w-full items-center justify-between gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 text-left transition hover:border-blue-300 hover:bg-blue-50 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:hover:border-gray-200 disabled:hover:bg-gray-100 dark:border-gray-700 dark:bg-gray-900 dark:hover:border-blue-700 dark:hover:bg-blue-950/20 dark:disabled:bg-gray-800">
                                            @if(filled($item['productDetailID']))
                                                <span class="min-w-0"><span class="block truncate text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $item['productName'] }}</span><span class="mt-0.5 block truncate text-xs text-slate-400">{{ $item['productCode'] }} · {{ $item['unit'] }}</span></span>
                                            @else
                                                <span class="text-sm text-slate-400">{{ blank($companyCode) ? 'Pilih Comcode terlebih dahulu' : 'Pilih produk dari Master Product ESB' }}</span>
                                            @endif
                                            <x-heroicon-o-chevron-right class="h-4 w-4 shrink-0 text-slate-400" />
                                        </button>
                                        @error("items.$index.productDetailID")<p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>@enderror
                                    </div>
                                    <div><label class="{{ $labelClass }}">Purpose <span class="text-red-400">*</span></label><select wire:model="items.{{ $index }}.purposeID" @disabled(blank($companyCode)) class="{{ $fieldClass }} disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-400 dark:disabled:bg-gray-800"><option value="">{{ blank($companyCode) ? '-- Pilih Comcode terlebih dahulu --' : '-- Pilih purpose --' }}</option>@foreach($purposes as $purpose)<option value="{{ $purpose['purposeID'] }}">{{ $purpose['purposeName'] }}{{ filled($purpose['purposeAccount'] ?? null) ? ' · '.$purpose['purposeAccount'] : '' }}</option>@endforeach</select>@error("items.$index.purposeID")<p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>@enderror</div>
                                    <div class="grid grid-cols-2 gap-3"><div><label class="{{ $labelClass }}">Qty <span class="text-red-400">*</span></label><input type="number" step="0.0001" wire:model="items.{{ $index }}.qty" class="{{ $fieldClass }}" placeholder="Contoh: -2">@error("items.$index.qty")<p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>@enderror</div><div><label class="{{ $labelClass }}">HPP</label><input type="number" min="0" step="0.0001" wire:model="items.{{ $index }}.hpp" class="{{ $fieldClass }}">@error("items.$index.hpp")<p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>@enderror</div></div>
                                </div>
                            @endforeach
                        </div>
                        <button type="button" wire:click="addItem" class="mt-3 inline-flex items-center gap-2 text-sm font-semibold text-blue-600"><x-heroicon-o-plus class="h-4 w-4" /> Tambah Produk</button>
                    </div>

                    <div><label class="{{ $labelClass }}">Informasi Tambahan <span class="ml-1 font-normal text-slate-400">(opsional · maks. 1.000 karakter)</span></label><textarea wire:model="additionalInfo" maxlength="1000" rows="4" class="{{ $fieldClass }} resize-none leading-relaxed" placeholder="Jelaskan alasan penyesuaian stok atau hasil pemeriksaan QC..."></textarea>@error('additionalInfo')<p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>@enderror</div>

                    <div x-data="{ uploading: false, progress: 0 }" x-on:livewire-upload-start="uploading = true" x-on:livewire-upload-finish="uploading = false" x-on:livewire-upload-error="uploading = false" x-on:livewire-upload-progress="progress = $event.detail.progress">
                        <label class="{{ $labelClass }}">Attachment <span class="ml-1 font-normal text-slate-400">(opsional · maks. 5 file)</span></label>
                        @if(count($attachments))<div class="mb-2 space-y-2">@foreach($attachments as $index => $file)<div class="flex items-center justify-between gap-2 rounded-xl border border-gray-200 px-3 py-2 dark:border-gray-700"><div class="flex min-w-0 items-center gap-2"><x-heroicon-o-paper-clip class="h-4 w-4 shrink-0 text-blue-400" /><span class="truncate text-xs text-slate-600 dark:text-slate-300">{{ $file->getClientOriginalName() }}</span></div><button type="button" wire:click="removeAttachment({{ $index }})" class="shrink-0 text-red-400"><x-heroicon-o-x-mark class="h-4 w-4" /></button></div>@endforeach</div>@endif
                        <label class="flex cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-gray-300 py-4 transition hover:border-blue-300 hover:bg-blue-50 dark:border-gray-700 dark:hover:border-blue-600 dark:hover:bg-blue-900/20"><x-heroicon-o-arrow-up-tray class="h-5 w-5 text-gray-400" /><span class="text-sm text-gray-400">Tambah Foto / PDF</span><input type="file" wire:model="attachments" multiple accept=".jpg,.jpeg,.png,.webp,.pdf" class="hidden"></label>
                        <div x-show="uploading" class="mt-2"><div class="h-1.5 overflow-hidden rounded-full bg-gray-100"><div class="h-full bg-blue-500" :style="`width: ${progress}%`"></div></div><p class="mt-1 text-xs text-blue-500" x-text="`Mengunggah ${progress}%`"></p></div>
                        <p class="mt-1.5 text-xs text-slate-400">JPG, PNG, WebP, atau PDF · maksimal 5 MB per file.</p>@error('attachments.*')<p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div class="mt-5 space-y-3"><button type="submit" wire:loading.attr="disabled" wire:target="submit,attachments" class="w-full rounded-2xl bg-blue-600 py-3.5 text-sm font-semibold text-white transition active:scale-95 disabled:opacity-60"><span wire:loading.remove wire:target="submit">Kirim Item Journal ke ESB</span><span wire:loading wire:target="submit">Memproses...</span></button><button type="button" wire:click="closeForm" class="w-full py-2 text-sm font-semibold text-slate-500">Batal</button></div>
            </form>
        </div>

        @if($productPickerOpen)
            <div wire:init="loadProducts" class="fixed inset-0 z-[70] flex items-end justify-center sm:items-center sm:p-6">
                <button type="button" aria-label="Tutup pemilih produk" class="absolute inset-0 bg-slate-950/55" wire:click="closeProductPicker"></button>
                <div class="relative flex max-h-[92vh] w-full max-w-5xl flex-col overflow-hidden rounded-t-3xl border border-gray-200 bg-white sm:rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                    <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                        <div><h3 class="font-bold text-gray-900 dark:text-white">Pilih Produk</h3><p class="mt-0.5 text-xs text-gray-500">Master Product ESB aktif untuk Item Journal.</p></div>
                        <button type="button" wire:click="closeProductPicker" class="rounded-xl border border-gray-200 p-2 text-gray-500 dark:border-gray-700"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                    </div>
                    <div class="grid gap-3 border-b border-gray-100 p-4 sm:grid-cols-2 dark:border-gray-800">
                        <label class="relative"><x-heroicon-o-magnifying-glass class="absolute left-3 top-3 h-4 w-4 text-gray-400" /><input type="search" wire:model.live.debounce.700ms="productSearch" class="w-full rounded-xl border border-gray-200 py-2.5 pl-9 pr-3 text-sm dark:border-gray-700 dark:bg-gray-950" placeholder="Cari nama produk"></label>
                        <label class="relative"><x-heroicon-o-qr-code class="absolute left-3 top-3 h-4 w-4 text-gray-400" /><input type="search" wire:model.live.debounce.700ms="productCodeSearch" class="w-full rounded-xl border border-gray-200 py-2.5 pl-9 pr-3 text-sm dark:border-gray-700 dark:bg-gray-950" placeholder="Cari kode produk"></label>
                    </div>
                    <div class="relative min-h-[300px] flex-1 overflow-y-auto">
                        <div wire:loading.flex wire:target="loadProducts,productSearch,productCodeSearch,previousProductPage,nextProductPage" class="absolute inset-0 z-20 items-center justify-center bg-white/85 dark:bg-gray-900/85" role="status">
                            <div class="text-center"><div class="mx-auto h-11 w-11 animate-spin rounded-full border-4 border-blue-100 border-r-blue-500 border-t-blue-600 dark:border-blue-950"></div><p class="mt-3 text-xs font-semibold text-blue-600">Memuat produk ESB...</p></div>
                        </div>
                        <div class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse($productOptions as $product)
                                <button type="button" wire:key="journal-product-{{ $product['productDetailID'] }}" wire:click="selectProduct({{ $product['productDetailID'] }})" class="flex w-full items-center justify-between gap-4 px-5 py-4 text-left transition hover:bg-blue-50 dark:hover:bg-blue-950/20">
                                    <span class="min-w-0"><span class="block truncate text-sm font-semibold text-gray-900 dark:text-white">{{ $product['productName'] }}</span><span class="mt-1 block text-xs text-gray-500"><span class="font-mono text-blue-600">{{ $product['productCode'] }}</span> · {{ $product['unit'] ?: '-' }}</span></span>
                                    <x-heroicon-o-plus-circle class="h-5 w-5 shrink-0 text-blue-500" />
                                </button>
                            @empty
                                <div class="px-5 py-16 text-center"><x-heroicon-o-cube-transparent class="mx-auto h-9 w-9 text-gray-300" /><p class="mt-3 text-sm font-semibold text-gray-600 dark:text-gray-300">Produk tidak ditemukan</p><p class="mt-1 text-xs text-gray-400">Coba gunakan nama atau kode yang berbeda.</p></div>
                            @endforelse
                        </div>
                    </div>
                    @php
                        $productLastPage = max(1, (int) ceil($productTotal / max(1, $productPerPage)));
                    @endphp
                    <div class="flex items-center justify-between border-t border-gray-100 px-5 py-3 dark:border-gray-800">
                        <p class="text-xs font-medium text-gray-500">Halaman {{ $productPage }} dari {{ $productLastPage }} · {{ number_format($productTotal) }} produk</p>
                        <div class="flex gap-2"><button type="button" wire:click="previousProductPage" @disabled($productPage <= 1) class="rounded-xl border border-gray-200 p-2 text-gray-600 disabled:opacity-30 dark:border-gray-700"><x-heroicon-o-chevron-left class="h-4 w-4" /></button><button type="button" wire:click="nextProductPage" @disabled(!$productHasNext) class="rounded-xl border border-gray-200 p-2 text-gray-600 disabled:opacity-30 dark:border-gray-700"><x-heroicon-o-chevron-right class="h-4 w-4" /></button></div>
                    </div>
                </div>
            </div>
        @endif
    @endif
    <x-quality-control.bottom-nav active="item-journal" />
</div>
