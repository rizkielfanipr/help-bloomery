<section class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
    <div class="flex flex-col justify-between gap-3 border-b border-gray-200 p-5 dark:border-gray-700 sm:flex-row sm:items-center">
        <div><h3 class="text-lg font-bold text-gray-900 dark:text-white">Menu Partnership</h3><p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Menu yang digunakan pada {{ $project->partnership_event_name }}.</p></div>
        @if($canManage)<button type="button" wire:click="openPartnershipMenuPicker" class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-blue-700"><x-heroicon-o-plus class="h-4 w-4" /> Tambah Menu</button>@endif
    </div>
    <div class="grid gap-3 p-5 md:grid-cols-2 xl:grid-cols-3">
        @forelse($project->partnershipMenus as $menu)
            <article class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                <p class="font-mono text-xs font-bold text-blue-600">{{ $menu->menu_code ?: 'Tanpa kode' }}</p>
                <h4 class="mt-1 font-bold text-gray-900 dark:text-white">{{ $menu->menu_name }}</h4>
                <p class="mt-1 text-sm text-gray-500">{{ $menu->category_name ?: 'Tanpa kategori' }} · {{ $menu->company_code }}</p>
                @if($canManage)<button type="button" wire:click="removePartnershipMenu({{ $menu->id }})" wire:confirm="Lepas Menu ini dari Partnership?" class="mt-4 inline-flex items-center gap-1.5 rounded-lg border border-red-200 px-3 py-2 text-xs font-bold text-red-600 hover:bg-red-50 dark:border-red-900"><x-heroicon-o-trash class="h-4 w-4" /> Lepas</button>@endif
            </article>
        @empty
            <div class="col-span-full rounded-xl border border-dashed border-gray-300 py-12 text-center dark:border-gray-700"><x-heroicon-o-cake class="mx-auto h-10 w-10 text-gray-300" /><h4 class="mt-3 font-bold text-gray-700 dark:text-gray-200">Belum ada Menu Partnership</h4><p class="mt-1 text-sm text-gray-500">Tambahkan Menu dari katalog lokal.</p></div>
        @endforelse
    </div>
</section>

<section class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
    <div class="flex flex-col justify-between gap-3 border-b border-gray-200 p-5 dark:border-gray-700 sm:flex-row sm:items-center">
        <div>
            <h3 class="text-lg font-bold text-gray-900 dark:text-white">Menu Release</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Menu baru yang dibuat khusus untuk partnership ini.</p>
        </div>
        @if($canManage)
            <button type="button" wire:click="openCreateProduct" class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-blue-700"><x-heroicon-o-plus class="h-4 w-4" /> Tambah Menu Release</button>
        @endif
    </div>

    <div class="grid gap-4 p-5 md:grid-cols-2 xl:grid-cols-3">
        @forelse($project->products as $product)
            @php
                $imageUrl = $product->imageUrl();
                $statusStyle = match($product->status) {
                    'released' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300',
                    'ready' => 'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300',
                    'trial' => 'bg-violet-50 text-violet-700 dark:bg-violet-950/40 dark:text-violet-300',
                    'development' => 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300',
                    'cancelled' => 'bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-300',
                    default => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
                };
            @endphp
            <article class="flex flex-col rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                <div class="flex items-start justify-between gap-3">
                    @if($imageUrl)
                        <img src="{{ $imageUrl }}" alt="{{ $product->name }}" class="h-12 w-12 rounded-lg border border-gray-200 object-cover dark:border-gray-700">
                    @else
                        <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-violet-50 text-violet-600 dark:bg-violet-950/50 dark:text-violet-300"><x-heroicon-o-cake class="h-6 w-6" /></div>
                    @endif
                    <span class="rounded-full px-2.5 py-1 text-[11px] font-bold {{ $statusStyle }}">{{ \App\Models\RndProjectProduct::STATUSES[$product->status] ?? ucfirst($product->status) }}</span>
                </div>
                <p class="mt-3 font-mono text-xs font-bold text-blue-600">{{ $product->product_code ?: 'Belum ada kode' }}</p>
                <h4 class="mt-1 text-base font-bold text-gray-900 dark:text-white">{{ $product->name }}</h4>
                <div class="mt-3 rounded-lg bg-gray-50 p-3 dark:bg-gray-800/60">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Informasi Tambahan</p>
                    <p class="mt-1 whitespace-pre-line text-sm leading-5 text-gray-600 dark:text-gray-300">{{ $product->description ?: 'Belum ada informasi tambahan.' }}</p>
                </div>
                <div class="mt-3 grid grid-cols-2 gap-2 text-xs">
                    <div><p class="text-gray-400">Harga Offline</p><p class="mt-0.5 font-bold text-gray-800 dark:text-gray-100">Rp {{ number_format((float) $product->offline_price, 0, ',', '.') }}</p></div>
                    <div><p class="text-gray-400">Harga Online</p><p class="mt-0.5 font-bold text-gray-800 dark:text-gray-100">Rp {{ number_format((float) $product->online_price, 0, ',', '.') }}</p></div>
                </div>
                <p class="mt-3 text-xs text-gray-500">{{ $product->release_date ? 'Rilis '.$product->release_date->format('d M Y') : 'Tanggal rilis belum diatur' }}</p>
                @if($canManage)
                    <div class="mt-auto flex gap-2 pt-4">
                        <button type="button" wire:click="editProduct({{ $product->id }})" class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-xs font-bold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800">Edit</button>
                        <button type="button" wire:click="deleteProduct({{ $product->id }})" wire:confirm="Hapus Menu Release ini?" class="rounded-lg border border-red-200 px-3 py-2 text-xs font-bold text-red-600 hover:bg-red-50 dark:border-red-900">Hapus</button>
                    </div>
                @endif
            </article>
        @empty
            <div class="col-span-full rounded-xl border border-dashed border-gray-300 py-12 text-center dark:border-gray-700"><x-heroicon-o-cake class="mx-auto h-10 w-10 text-gray-300" /><h4 class="mt-3 font-bold text-gray-700 dark:text-gray-200">Belum ada Menu Release</h4><p class="mt-1 text-sm text-gray-500">Tambahkan menu baru untuk partnership ini.</p></div>
        @endforelse
    </div>
</section>

<template x-teleport="body">
    <div x-show="productFormOpen" x-cloak class="fixed inset-0 z-[160] flex items-center justify-center p-3 sm:p-6">
        <button type="button" class="absolute inset-0 bg-slate-950/55" @click="productFormOpen = false" aria-label="Tutup form Menu Release"></button>
        <div x-show="productFormOpen" x-transition class="relative max-h-[92vh] w-full max-w-3xl overflow-y-auto rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <div class="flex items-start justify-between gap-4 border-b border-gray-200 p-5 dark:border-gray-700">
                <div><h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ $editingProductId ? 'Edit Menu Release' : 'Tambah Menu Release' }}</h3><p class="mt-1 text-sm text-gray-500">Lengkapi informasi menu yang dibuat untuk partnership.</p></div>
                <button type="button" @click="productFormOpen = false" class="rounded-lg border border-gray-200 p-2 text-gray-500 dark:border-gray-700"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
            </div>
            <form wire:submit="savePartnershipReleaseMenu" class="grid gap-4 p-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="{{ $label }}">Foto Menu</label>
                    <input wire:model="productPhoto" type="file" accept="image/jpeg,image/png,image/webp" class="block w-full rounded-lg border border-gray-300 bg-white text-sm text-gray-600 file:mr-3 file:border-0 file:bg-blue-50 file:px-3 file:py-2 file:font-bold file:text-blue-700 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300">
                    @error('productPhoto')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div><label class="{{ $label }}">Nama Menu *</label><input wire:model="productName" class="{{ $input }}" placeholder="Nama Menu Release">@error('productName')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                <div><label class="{{ $label }}">Kode Menu</label><input wire:model="productCode" class="{{ $input }}" placeholder="Kode internal (opsional)">@error('productCode')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                <div class="sm:col-span-2"><label class="{{ $label }}">Informasi Tambahan</label><textarea wire:model="productDescription" rows="5" class="{{ $input }} resize-none" placeholder="Deskripsi menu, konsep, penyajian, atau catatan tambahan..."></textarea>@error('productDescription')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                <div><label class="{{ $label }}">Harga Offline</label><input wire:model="offlinePrice" type="number" min="0" step="1" class="{{ $input }}">@error('offlinePrice')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                <div><label class="{{ $label }}">Harga Online</label><input wire:model="onlinePrice" type="number" min="0" step="1" class="{{ $input }}">@error('onlinePrice')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                <div><label class="{{ $label }}">Tanggal Release</label><input wire:model="releaseDate" type="date" class="{{ $input }}">@error('releaseDate')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                <div><label class="{{ $label }}">Status *</label><select wire:model="productStatus" class="{{ $input }}">@foreach(\App\Models\RndProjectProduct::STATUSES as $statusValue => $statusLabel)<option value="{{ $statusValue }}">{{ $statusLabel }}</option>@endforeach</select>@error('productStatus')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                <div class="flex justify-end gap-2 border-t border-gray-200 pt-4 dark:border-gray-700 sm:col-span-2">
                    <button type="button" @click="productFormOpen = false" class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-bold text-gray-700 dark:border-gray-600 dark:text-gray-200">Batal</button>
                    <button type="submit" wire:loading.attr="disabled" wire:target="savePartnershipReleaseMenu,productPhoto" class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-bold text-white disabled:opacity-50"><span wire:loading.remove wire:target="savePartnershipReleaseMenu">{{ $editingProductId ? 'Simpan Perubahan' : 'Tambah Menu' }}</span><span wire:loading wire:target="savePartnershipReleaseMenu">Menyimpan...</span></button>
                </div>
            </form>
        </div>
    </div>
</template>

<section class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
    <div class="flex flex-col justify-between gap-3 border-b border-gray-200 p-5 dark:border-gray-700 sm:flex-row sm:items-center">
        <div><h3 class="text-lg font-bold text-gray-900 dark:text-white">Peralatan Event</h3><p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Daftar peralatan atau produk pendukung yang digunakan saat event.</p></div>
        @if($canManage)<button type="button" wire:click="openPartnershipEquipmentPicker" class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-blue-700"><x-heroicon-o-plus class="h-4 w-4" /> Tambah Peralatan</button>@endif
    </div>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[760px] text-sm">
            <thead class="bg-gray-50 text-left text-xs font-bold uppercase tracking-wide text-gray-500 dark:bg-gray-800 dark:text-gray-400"><tr><th class="px-5 py-3">Peralatan</th><th class="px-5 py-3">UOM</th><th class="px-5 py-3">Jumlah</th><th class="px-5 py-3">Catatan</th><th class="px-5 py-3 text-right">Aksi</th></tr></thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse($project->partnershipEquipment as $equipment)
                    <tr wire:key="partnership-equipment-{{ $equipment->id }}">
                        <td class="px-5 py-3"><p class="font-mono text-xs font-bold text-blue-600">{{ $equipment->product_code ?: 'PD-'.$equipment->product_detail_id }}</p><p class="mt-0.5 font-semibold text-gray-900 dark:text-white">{{ $equipment->product_name }}</p></td>
                        <td class="px-5 py-3 font-semibold text-gray-600 dark:text-gray-300">{{ $equipment->uom_name ?: '-' }}</td>
                        <td class="px-5 py-3"><input wire:model="partnershipEquipmentDrafts.{{ $equipment->id }}.quantity" type="number" min="0.0001" step="0.0001" @disabled(!$canManage) class="w-28 rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">@error("partnershipEquipmentDrafts.{$equipment->id}.quantity")<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</td>
                        <td class="px-5 py-3"><input wire:model="partnershipEquipmentDrafts.{{ $equipment->id }}.notes" @disabled(!$canManage) class="w-full min-w-56 rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800" placeholder="Catatan penggunaan">@error("partnershipEquipmentDrafts.{$equipment->id}.notes")<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</td>
                        <td class="px-5 py-3"><div class="flex justify-end gap-2">@if($canManage)<button type="button" wire:click="savePartnershipEquipment({{ $equipment->id }})" class="rounded-lg bg-blue-50 px-3 py-2 text-xs font-bold text-blue-700 hover:bg-blue-100 dark:bg-blue-950/40 dark:text-blue-300">Simpan</button><button type="button" wire:click="removePartnershipEquipment({{ $equipment->id }})" wire:confirm="Lepas peralatan ini dari event?" class="rounded-lg border border-red-200 px-3 py-2 text-xs font-bold text-red-600 hover:bg-red-50 dark:border-red-900">Lepas</button>@endif</div></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-12 text-center text-gray-500"><x-heroicon-o-wrench-screwdriver class="mx-auto h-9 w-9 text-gray-300" /><p class="mt-3 font-semibold">Belum ada peralatan event.</p><p class="mt-1 text-xs">Tambahkan dari Product List ESB.</p></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

@include('filament.helpdesk.partials.esb-product-picker-modal', [
    'pickerTitle' => 'Pilih Peralatan Event',
    'pickerSelectMethod' => 'selectPartnershipEquipment',
    'pickerCloseAction' => 'closePartnershipEquipmentPicker',
    'pickerLockedCategoryName' => 'Peralatan',
])

@if($partnershipMenuPickerOpen)
    @php
        $catalog = $this->partnershipMenuCatalog;
        $catalogStatus = $this->partnershipMenuCatalogStatus();
        $selectedExistingIds = $project->partnershipMenus->pluck('esb_menu_id')->map(fn ($id) => (int) $id)->all();
        $menuPickerPageStart = max(1, $catalog->currentPage() - 4);
        $menuPickerPageEnd = min($catalog->lastPage(), $menuPickerPageStart + 8);
        $menuPickerPageStart = max(1, $menuPickerPageEnd - 8);
    @endphp
    <div wire:key="partnership-menu-picker-modal" class="fixed inset-0 z-[160] flex items-center justify-center p-3 sm:p-6" role="dialog" aria-modal="true" aria-label="Pilih Menu ESB">
        <button type="button" wire:click="closePartnershipMenuPicker" class="absolute inset-0 bg-gray-950/60" aria-label="Tutup pilih Menu"></button>
        <x-rnd.picker-modal title="Pilih Menu ESB" description="Sumber data: katalog lokal ESB BLSS. Menu tanpa BOM atau yang sudah ada pada Partnership tidak dapat dipilih." max-width="6xl">
            <x-slot:close>
                <button type="button" wire:click="closePartnershipMenuPicker" class="rounded-lg border border-gray-200 p-2 text-gray-500 hover:bg-gray-50 dark:border-gray-700" aria-label="Tutup"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
            </x-slot:close>

            <div class="relative flex min-h-0 flex-1 flex-col overflow-hidden">
                <div class="flex shrink-0 flex-col gap-2 border-b border-gray-200 px-4 py-3 text-xs dark:border-gray-700 sm:flex-row sm:items-center sm:justify-between" role="status">
                    <p class="flex items-center gap-1.5 text-gray-600 dark:text-gray-300">
                        @if($catalogStatus['syncing'])
                            <span class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-blue-200 border-t-blue-600" aria-hidden="true"></span> Katalog sedang diperbarui. Daftar terakhir tetap dapat dipakai.
                        @elseif($catalogStatus['failed'])
                            <x-heroicon-o-exclamation-triangle class="h-4 w-4 text-amber-500" aria-hidden="true" /> Pembaruan terakhir gagal. Menampilkan snapshot yang tersedia.
                        @elseif($catalogStatus['last_synced_at'])
                            <x-heroicon-o-clock class="h-4 w-4" aria-hidden="true" /> Katalog diperbarui {{ $catalogStatus['last_synced_at']->diffForHumans() }}.
                        @else
                            <x-heroicon-o-clock class="h-4 w-4" aria-hidden="true" /> Katalog belum pernah disinkronkan.
                        @endif
                    </p>
                    <span class="font-semibold text-blue-700 dark:text-blue-300">{{ count($selectedPartnershipMenuIds) }} Menu dipilih</span>
                </div>

                <div class="min-h-0 flex-1 overflow-auto overscroll-contain">
                    <table class="w-full min-w-[900px] text-left text-sm">
                        <thead class="sticky top-0 z-10 bg-white dark:bg-gray-900">
                            <tr class="border-b border-gray-200 text-xs font-bold uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                <th class="px-4 pb-3 pt-4">Menu Code</th>
                                <th class="px-4 pb-3 pt-4">Menu Name</th>
                                <th class="px-4 pb-3 pt-4">Category</th>
                                <th class="px-4 pb-3 pt-4">Status BOM</th>
                                <th class="px-4 pb-3 pt-4 text-right">Aksi</th>
                            </tr>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="px-4 pb-3 pt-2"><input wire:model.live.debounce.700ms="partnershipMenuSearchCode" type="search" aria-label="Cari kode Menu" placeholder="Cari kode..." class="w-full rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-xs font-normal normal-case text-gray-900 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"></th>
                                <th class="px-4 pb-3 pt-2"><input wire:model.live.debounce.700ms="partnershipMenuSearchName" type="search" aria-label="Cari nama Menu" placeholder="Cari nama..." class="w-full rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-xs font-normal normal-case text-gray-900 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"></th>
                                <th class="px-4 pb-3 pt-2"></th><th class="px-4 pb-3 pt-2"></th><th class="px-4 pb-3 pt-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse($catalog as $row)
                                @php
                                    $splitCategory = $this->splitPartnershipMenuCategory($row->category_detail);
                                    $alreadySelected = in_array((int) $row->menu_id, $selectedExistingIds, true);
                                    $selectable = $row->bom_id > 0 && ! $alreadySelected;
                                @endphp
                                <tr wire:key="partnership-menu-picker-{{ $row->id }}" class="{{ $selectable ? 'hover:bg-blue-50 dark:hover:bg-blue-950/30' : 'opacity-60' }} text-gray-700 transition dark:text-gray-200">
                                    <td class="px-4 py-3"><p class="truncate font-mono font-semibold text-blue-700 dark:text-blue-300">{{ $row->menu_code ?: '-' }}</p></td>
                                    <td class="px-4 py-3"><p class="truncate font-semibold text-gray-900 dark:text-white">{{ $row->menu_name ?: 'Menu #'.$row->menu_id }}</p></td>
                                    <td class="px-4 py-3"><p class="truncate">{{ collect([$splitCategory['category'], $splitCategory['detail']])->filter()->implode(' · ') ?: '-' }}</p></td>
                                    <td class="px-4 py-3">
                                        @if($row->bom_id > 0)
                                            <span class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-700 dark:text-emerald-300"><x-heroicon-o-check-circle class="h-3.5 w-3.5" aria-hidden="true" /> {{ $row->bom_name ?: 'Memiliki BOM' }}</span>
                                        @else
                                            <span class="inline-flex items-center gap-1 text-xs font-semibold text-gray-500 dark:text-gray-400"><x-heroicon-o-minus-circle class="h-3.5 w-3.5" aria-hidden="true" /> Belum Memiliki BOM</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        @if($alreadySelected)
                                            <span class="inline-flex items-center gap-1 rounded-md border border-gray-200 px-2 py-1 text-xs font-semibold text-gray-500 dark:border-gray-700 dark:text-gray-400"><x-heroicon-o-check class="h-3.5 w-3.5" aria-hidden="true" /> Sudah Dipilih</span>
                                        @elseif($row->bom_id > 0)
                                            <label class="inline-flex cursor-pointer items-center gap-2 rounded-md bg-blue-50 px-2 py-1 text-xs font-semibold text-blue-700 hover:bg-blue-100 dark:bg-blue-950/50 dark:text-blue-300"><input wire:model="selectedPartnershipMenuIds" type="checkbox" value="{{ $row->id }}" class="rounded border-blue-300 text-blue-600 focus:ring-blue-500"> Pilih</label>
                                        @else
                                            <span class="inline-flex rounded-md border border-gray-200 px-2 py-1 text-xs font-semibold text-gray-400 dark:border-gray-700">Tidak Dapat Dipilih</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-5 py-14 text-center text-gray-500 dark:text-gray-400">{{ trim($partnershipMenuSearchName.$partnershipMenuSearchCode) !== '' ? 'Tidak ada Menu yang cocok dengan pencarian.' : 'Katalog Menu BLSS masih kosong.' }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div wire:loading.flex wire:target="partnershipMenuSearchName,partnershipMenuSearchCode,previousPartnershipMenuPage,nextPartnershipMenuPage,goToPartnershipMenuPage,addPartnershipMenus" class="absolute inset-0 z-20 hidden items-center justify-center bg-white/70 dark:bg-gray-900/70"><span class="h-8 w-8 animate-spin rounded-full border-4 border-blue-200 border-t-blue-600"></span></div>
            </div>

            <div class="flex shrink-0 flex-col gap-3 border-t border-gray-200 px-4 py-3 dark:border-gray-700 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex flex-wrap items-center gap-3"><p class="text-xs font-medium text-gray-600 dark:text-gray-300">Halaman {{ $catalog->currentPage() }} dari {{ max(1, $catalog->lastPage()) }}</p><button type="button" wire:click="addPartnershipMenus" wire:loading.attr="disabled" @disabled($selectedPartnershipMenuIds === []) class="rounded-lg bg-blue-600 px-3 py-2 text-xs font-bold text-white hover:bg-blue-700 disabled:opacity-40">Tambahkan {{ count($selectedPartnershipMenuIds) }} Menu</button></div>
                <div class="max-w-full overflow-x-auto"><div class="inline-flex min-w-max overflow-hidden rounded-lg border border-gray-300 dark:border-gray-600">
                    <button type="button" wire:click="goToPartnershipMenuPage(1)" @disabled($catalog->onFirstPage()) class="border-r border-gray-300 px-3 py-2 text-xs font-semibold disabled:opacity-40 dark:border-gray-600">First</button>
                    <button type="button" wire:click="previousPartnershipMenuPage" @disabled($catalog->onFirstPage()) class="border-r border-gray-300 px-3 py-2 text-sm font-bold disabled:opacity-40 dark:border-gray-600">&laquo;</button>
                    @foreach(range($menuPickerPageStart, $menuPickerPageEnd) as $pageNumber)
                        <button type="button" wire:click="goToPartnershipMenuPage({{ $pageNumber }})" @disabled($pageNumber === $catalog->currentPage()) class="border-r border-gray-300 px-3.5 py-2 text-xs font-semibold dark:border-gray-600 {{ $pageNumber === $catalog->currentPage() ? 'bg-blue-600 text-white disabled:opacity-100' : '' }}">{{ $pageNumber }}</button>
                    @endforeach
                    <button type="button" wire:click="nextPartnershipMenuPage" @disabled(! $catalog->hasMorePages()) class="border-r border-gray-300 px-3 py-2 text-sm font-bold disabled:opacity-40 dark:border-gray-600">&raquo;</button>
                    <button type="button" wire:click="goToPartnershipMenuPage({{ max(1, $catalog->lastPage()) }})" @disabled(! $catalog->hasMorePages()) class="px-3 py-2 text-xs font-semibold disabled:opacity-40">Last</button>
                </div></div>
            </div>
        </x-rnd.picker-modal>
    </div>
@endif
