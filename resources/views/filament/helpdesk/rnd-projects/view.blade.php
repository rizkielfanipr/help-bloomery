<x-filament-panels::page>
    @php
        $project = $this->record;
        $status = today()->lt($project->start_date) ? 'Upcoming' : (today()->gt($project->end_date) ? 'Completed' : 'Active');
        $canManage = \App\Filament\Helpdesk\Resources\Projects\ProjectResource::canEdit($project);
        $canExportBom = auth()->user()?->hasRole('SUPERADMIN') || auth()->user()?->can('view bill of materials');
        $input = 'w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white';
        $label = 'mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200';
    @endphp

    <div class="space-y-6" x-data="{ productFormOpen: false }"
         @open-product-form.window="productFormOpen = true"
         @close-product-form.window="productFormOpen = false"
         @keydown.escape.window="productFormOpen = false">
        <section class="overflow-hidden rounded-2xl bg-gradient-to-r from-blue-600 to-indigo-600 p-6 text-white">
            <div class="flex flex-col justify-between gap-6 lg:flex-row lg:items-end">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <a href="{{ \App\Filament\Helpdesk\Resources\Projects\ProjectResource::getUrl('index') }}" class="text-sm font-semibold text-blue-100 hover:text-white">Project</a>
                        <span class="text-blue-300">/</span>
                        <span class="rounded-full bg-white/15 px-2.5 py-1 text-xs font-bold">{{ $status }}</span>
                    </div>
                    <h2 class="mt-3 text-3xl font-bold">{{ $project->name }}</h2>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-blue-100">{{ $project->description ?: 'Tidak ada deskripsi project.' }}</p>
                </div>
                <div class="grid min-w-72 grid-cols-2 gap-3">
                    <div class="rounded-xl bg-white/10 p-3 backdrop-blur">
                        <p class="text-xs text-blue-100">Periode</p>
                        <p class="mt-1 text-sm font-bold">{{ $project->start_date->format('d M') }} – {{ $project->end_date->format('d M Y') }}</p>
                    </div>
                    <div class="rounded-xl bg-white/10 p-3 backdrop-blur">
                        <p class="text-xs text-blue-100">Product Release</p>
                        <p class="mt-1 text-sm font-bold">{{ $project->products->count() }} Product</p>
                    </div>
                </div>
            </div>
        </section>

        <div>
            <section class="rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                <div class="flex flex-col justify-between gap-3 border-b border-gray-200 p-5 dark:border-gray-700 sm:flex-row sm:items-center">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">Product Release</h3>
                        <p class="text-sm text-gray-500">Daftar produk yang dikembangkan dan akan dirilis dalam project ini.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @if($canExportBom)
                            <button type="button" wire:click="openProjectBomExport('kitchen')" class="inline-flex items-center justify-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-3.5 py-2.5 text-sm font-bold text-emerald-700 hover:bg-emerald-100">
                                <x-heroicon-o-document-arrow-down class="h-4 w-4" /> Export Kitchen PDF
                            </button>
                            <button type="button" wire:click="openProjectBomExport('store')" class="inline-flex items-center justify-center gap-2 rounded-lg border border-blue-200 bg-blue-50 px-3.5 py-2.5 text-sm font-bold text-blue-700 hover:bg-blue-100">
                                <x-heroicon-o-document-arrow-down class="h-4 w-4" /> Export Store PDF
                            </button>
                        @endif
                        @if($canManage)
                            <button type="button" wire:click="openCreateProduct" class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-blue-700">
                                <x-heroicon-o-plus class="h-4 w-4" /> Tambah Product
                            </button>
                        @endif
                    </div>
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
                            $activePrices = $product->currentRegionalPrices->unique('sales_region_id');
                            $offlineMin = $activePrices->min('offline_price');
                            $offlineMax = $activePrices->max('offline_price');
                            $onlineMin = $activePrices->min('online_price');
                            $onlineMax = $activePrices->max('online_price');
                            $projectionQuantity = $product->salesProjections->sum('target_quantity');
                            $projectionRevenue = $product->salesProjections->sum('target_revenue');
                        @endphp
                        <article class="flex flex-col rounded-xl border border-gray-200 p-4 transition hover:border-blue-300 dark:border-gray-700 dark:hover:border-blue-700">
                            <div class="flex items-start justify-between gap-3">
                                @if($imageUrl)
                                    <img src="{{ $imageUrl }}" alt="{{ $product->name }}" class="h-12 w-12 rounded-lg border border-gray-200 object-cover dark:border-gray-700">
                                @else
                                    <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-950/50 dark:text-blue-300">
                                        <x-heroicon-o-cake class="h-6 w-6" />
                                    </div>
                                @endif
                                <span class="rounded-full px-2.5 py-1 text-[11px] font-bold {{ $statusStyle }}">{{ \App\Models\RndProjectProduct::STATUSES[$product->status] ?? ucfirst($product->status) }}</span>
                            </div>
                            <p class="mt-3 font-mono text-xs font-bold text-blue-600">{{ $product->product_code ?: 'Belum ada kode' }}</p>
                            <h4 class="mt-1 text-base font-bold text-gray-900 dark:text-white">{{ $product->name }}</h4>
                            <p class="mt-1 line-clamp-2 min-h-10 text-sm leading-5 text-gray-500">{{ $product->description ?: 'Tidak ada deskripsi produk.' }}</p>

                            <div class="mt-3 grid grid-cols-2 gap-2">
                                <div class="rounded-lg bg-gray-50 p-2.5 dark:bg-gray-800/60">
                                    <p class="text-[11px] text-gray-400">Harga Offline</p>
                                    <p class="mt-0.5 text-sm font-bold text-gray-800 dark:text-gray-100">
                                        @if($offlineMin === null)Belum diatur
                                        @elseif((float) $offlineMin === (float) $offlineMax)Rp {{ number_format((float) $offlineMin, 0, ',', '.') }}
                                        @else Rp {{ number_format((float) $offlineMin, 0, ',', '.') }}–{{ number_format((float) $offlineMax, 0, ',', '.') }}
                                        @endif
                                    </p>
                                </div>
                                <div class="rounded-lg bg-gray-50 p-2.5 dark:bg-gray-800/60">
                                    <p class="text-[11px] text-gray-400">Harga Online</p>
                                    <p class="mt-0.5 text-sm font-bold text-gray-800 dark:text-gray-100">
                                        @if($onlineMin === null)Belum diatur
                                        @elseif((float) $onlineMin === (float) $onlineMax)Rp {{ number_format((float) $onlineMin, 0, ',', '.') }}
                                        @else Rp {{ number_format((float) $onlineMin, 0, ',', '.') }}–{{ number_format((float) $onlineMax, 0, ',', '.') }}
                                        @endif
                                    </p>
                                </div>
                            </div>

                            <div class="mt-2 grid grid-cols-2 gap-2">
                                <div class="rounded-lg bg-blue-50 p-2.5 dark:bg-blue-950/30">
                                    <p class="text-[11px] text-blue-500">Shelf Life</p>
                                    <p class="mt-0.5 text-sm font-bold text-blue-800 dark:text-blue-200">
                                        {{ $product->shelf_life_value ? $product->shelf_life_value.' '.(\App\Models\RndProjectProduct::SHELF_LIFE_UNITS[$product->shelf_life_unit] ?? $product->shelf_life_unit) : 'Belum diatur' }}
                                    </p>
                                </div>
                                <div class="rounded-lg bg-emerald-50 p-2.5 dark:bg-emerald-950/30">
                                    <p class="text-[11px] text-emerald-500">Sales Projection</p>
                                    <p class="mt-0.5 text-sm font-bold text-emerald-800 dark:text-emerald-200">{{ number_format((float) $projectionQuantity, 0, ',', '.') }} unit</p>
                                    <p class="text-[10px] text-emerald-600">Rp {{ number_format((float) $projectionRevenue, 0, ',', '.') }}</p>
                                </div>
                            </div>

                            <div class="mt-3 flex items-center justify-between text-xs text-gray-500">
                                <span>{{ $product->release_date ? 'Rilis '.$product->release_date->format('d M Y') : 'Tanggal rilis belum diatur' }} · {{ $activePrices->count() }} Region</span>
                                <span class="font-bold text-blue-600">{{ $product->boms->count() }} BOM</span>
                            </div>

                            <div class="mt-auto flex items-center gap-2 pt-4">
                                <a href="{{ \App\Filament\Helpdesk\Pages\ViewProjectProductPage::getUrl(['project' => $project->id, 'product' => $product->id]) }}" class="inline-flex flex-1 items-center justify-center gap-2 rounded-lg bg-blue-600 px-3 py-2 text-xs font-bold text-white hover:bg-blue-700">
                                    Buka Product
                                    <x-heroicon-o-arrow-right class="h-4 w-4" />
                                </a>
                                @if($canManage)
                                    <button type="button" wire:click="editProduct({{ $product->id }})" class="rounded-lg border border-gray-300 p-2 text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800" title="Edit Product">
                                        <x-heroicon-o-pencil-square class="h-4 w-4" />
                                    </button>
                                    <button type="button" wire:click="deleteProduct({{ $product->id }})" wire:confirm="Hapus product ini?" class="rounded-lg border border-red-200 p-2 text-red-600 hover:bg-red-50 dark:border-red-900 dark:hover:bg-red-950/30" title="Hapus Product">
                                        <x-heroicon-o-trash class="h-4 w-4" />
                                    </button>
                                @endif
                            </div>
                        </article>
                    @empty
                        <div class="col-span-full rounded-xl border border-dashed border-gray-300 py-14 text-center dark:border-gray-700">
                            <x-heroicon-o-cake class="mx-auto h-11 w-11 text-gray-300" />
                            <h4 class="mt-3 font-bold text-gray-700 dark:text-gray-200">Belum ada Product Release</h4>
                            <p class="mt-1 text-sm text-gray-500">Tambahkan produk sebelum membuat atau mengimport BOM.</p>
                        </div>
                    @endforelse
                </div>
            </section>

        </div>

        <template x-teleport="body">
            <div x-show="productFormOpen" x-cloak class="fixed inset-0 z-[120] flex items-center justify-center p-3 sm:p-6">
                <div class="absolute inset-0 bg-slate-950/50" @click="productFormOpen = false"></div>
                <div x-show="productFormOpen" x-transition class="relative max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                    <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                        <div>
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ $editingProductId ? 'Edit Product Release' : 'Tambah Product Release' }}</h3>
                            <p class="text-sm text-gray-500">Lengkapi informasi produk dan harga penjualan.</p>
                        </div>
                        <button type="button" @click="productFormOpen = false" class="rounded-lg border border-gray-200 p-2 text-gray-500 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                    </div>
                    <form wire:submit="saveProduct" class="space-y-4 p-5">
                        <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                            <label class="{{ $label }}">Foto Product</label>
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                                <div class="flex h-28 w-28 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-dashed border-gray-300 bg-gray-50 dark:border-gray-600 dark:bg-gray-800">
                                    @if($productPhoto)
                                        <img src="{{ $productPhoto->temporaryUrl() }}" alt="Preview foto product" class="h-full w-full object-cover">
                                    @elseif($this->productImageUrl())
                                        <img src="{{ $this->productImageUrl() }}" alt="Foto product" class="h-full w-full object-cover">
                                    @else
                                        <x-heroicon-o-photo class="h-9 w-9 text-gray-300" />
                                    @endif
                                </div>
                                <div class="flex-1">
                                    <input wire:model="productPhoto" type="file" accept="image/jpeg,image/png,image/webp"
                                           class="block w-full rounded-lg border border-gray-300 bg-white text-sm text-gray-600 file:mr-3 file:border-0 file:bg-blue-50 file:px-3 file:py-2 file:font-bold file:text-blue-700 hover:file:bg-blue-100 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300">
                                    <p class="mt-2 text-xs text-gray-500">JPG, PNG, atau WebP. Maksimal 5 MB. Foto disimpan langsung ke Cloudflare R2.</p>
                                    <div wire:loading wire:target="productPhoto" class="mt-2 text-xs font-semibold text-blue-600">Menyiapkan preview foto...</div>
                                    @error('productPhoto')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                </div>
                            </div>
                        </div>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="{{ $label }}">Nama Product *</label>
                                <input wire:model="productName" class="{{ $input }}" placeholder="Contoh: Strawberry Croissant">
                                @error('productName')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="{{ $label }}">Product Code / SKU</label>
                                <input wire:model="productCode" class="{{ $input }}" placeholder="Contoh: PRD-STB-001">
                                @error('productCode')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="{{ $label }}">Target Tanggal Rilis</label>
                                <input wire:model="releaseDate" type="date" class="{{ $input }}">
                                @error('releaseDate')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="{{ $label }}">Status *</label>
                                <select wire:model="productStatus" class="{{ $input }}">
                                    @foreach(\App\Models\RndProjectProduct::STATUSES as $value => $statusLabel)
                                        <option value="{{ $value }}">{{ $statusLabel }}</option>
                                    @endforeach
                                </select>
                                @error('productStatus')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div class="md:col-span-2">
                                <label class="{{ $label }}">Deskripsi Product</label>
                                <textarea wire:model="productDescription" rows="4" class="{{ $input }}" placeholder="Deskripsi, positioning, atau catatan pengembangan product..."></textarea>
                                @error('productDescription')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                        </div>
                        <section class="rounded-xl border border-gray-200 dark:border-gray-700">
                            <div class="border-b border-gray-200 p-4 dark:border-gray-700">
                                <h4 class="font-bold text-gray-900 dark:text-white">Shelf Life & Storage</h4>
                                <p class="text-xs text-gray-500">Informasi ketahanan dan kondisi penyimpanan produk.</p>
                            </div>
                            <div class="grid gap-4 p-4 md:grid-cols-3">
                                <div>
                                    <label class="{{ $label }}">Shelf Life</label>
                                    <input wire:model="shelfLifeValue" type="number" min="1" class="{{ $input }}" placeholder="Contoh: 6">
                                    @error('shelfLifeValue')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="{{ $label }}">Satuan</label>
                                    <select wire:model="shelfLifeUnit" class="{{ $input }}">
                                        @foreach(\App\Models\RndProjectProduct::SHELF_LIFE_UNITS as $value => $unitLabel)
                                            <option value="{{ $value }}">{{ $unitLabel }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="{{ $label }}">Kondisi Penyimpanan</label>
                                    <select wire:model="storageCondition" class="{{ $input }}">
                                        @foreach(\App\Models\RndProjectProduct::STORAGE_CONDITIONS as $value => $conditionLabel)
                                            <option value="{{ $value }}">{{ $conditionLabel }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="{{ $label }}">Target Outlet</label>
                                    <input wire:model="targetOutlets" type="number" min="1" class="{{ $input }}" placeholder="Contoh: 50">
                                    @error('targetOutlets')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                </div>
                                <div class="md:col-span-2">
                                    <label class="{{ $label }}">Catatan Penyimpanan</label>
                                    <input wire:model="storageNotes" class="{{ $input }}" placeholder="Contoh: Simpan tertutup pada suhu 2–5°C">
                                    @error('storageNotes')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                </div>
                            </div>
                        </section>
                        <section class="rounded-xl border border-gray-200 dark:border-gray-700">
                            <div class="flex flex-col justify-between gap-3 border-b border-gray-200 p-4 dark:border-gray-700 sm:flex-row sm:items-center">
                                <div>
                                    <h4 class="font-bold text-gray-900 dark:text-white">Sales Projection</h4>
                                    <p class="text-xs text-gray-500">Target bulanan per region dan channel.</p>
                                </div>
                                <button type="button" wire:click="addSalesProjection" class="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-bold text-white hover:bg-emerald-700">+ Tambah Projection</button>
                            </div>
                            <div class="space-y-3 p-4">
                                @forelse($salesProjections as $index => $projection)
                                    <div wire:key="sales-projection-{{ $projection['id'] ?? 'new-'.$index }}" class="rounded-xl border border-gray-200 p-3 dark:border-gray-700">
                                        <input wire:model="salesProjections.{{ $index }}.id" type="hidden">
                                        <div class="grid gap-3 md:grid-cols-3">
                                            <div>
                                                <label class="{{ $label }}">Periode *</label>
                                                <input wire:model="salesProjections.{{ $index }}.projection_month" type="month" class="{{ $input }}">
                                            </div>
                                            <div>
                                                <label class="{{ $label }}">Region *</label>
                                                <select wire:model="salesProjections.{{ $index }}.sales_region_id" class="{{ $input }}">
                                                    @foreach($this->activeSalesRegions as $region)
                                                        <option value="{{ $region->id }}">{{ $region->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div>
                                                <label class="{{ $label }}">Channel *</label>
                                                <select wire:model="salesProjections.{{ $index }}.channel" class="{{ $input }}">
                                                    @foreach(\App\Models\RndProductSalesProjection::CHANNELS as $value => $channelLabel)
                                                        <option value="{{ $value }}">{{ $channelLabel }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div>
                                                <label class="{{ $label }}">Target Quantity *</label>
                                                <input wire:model="salesProjections.{{ $index }}.target_quantity" type="number" min="0.01" step="0.01" class="{{ $input }}" placeholder="0">
                                            </div>
                                            <div>
                                                <label class="{{ $label }}">Target Revenue *</label>
                                                <input wire:model="salesProjections.{{ $index }}.target_revenue" type="number" min="0" step="1" class="{{ $input }}" placeholder="0">
                                            </div>
                                            <div>
                                                <label class="{{ $label }}">Target Outlet</label>
                                                <input wire:model="salesProjections.{{ $index }}.target_outlets" type="number" min="1" class="{{ $input }}" placeholder="Opsional">
                                            </div>
                                            <div class="md:col-span-2">
                                                <label class="{{ $label }}">Asumsi / Catatan</label>
                                                <input wire:model="salesProjections.{{ $index }}.notes" class="{{ $input }}" placeholder="Dasar perhitungan projection">
                                            </div>
                                            <div class="flex items-end justify-end">
                                                <button type="button" wire:click="removeSalesProjection({{ $index }})" class="rounded-lg border border-red-200 px-3 py-2 text-xs font-bold text-red-600 hover:bg-red-50 dark:border-red-900">Hapus</button>
                                            </div>
                                        </div>
                                        @error("salesProjections.$index.projection_month")<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
                                        @error("salesProjections.$index.sales_region_id")<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
                                        @error("salesProjections.$index.target_quantity")<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
                                        @error("salesProjections.$index.target_revenue")<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
                                    </div>
                                @empty
                                    <p class="py-6 text-center text-sm text-gray-500">Belum ada projection. Wajib diisi sebelum status Ready/Released.</p>
                                @endforelse
                                @error('salesProjections')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                        </section>
                        <section class="rounded-xl border border-gray-200 dark:border-gray-700">
                            <div class="flex flex-col justify-between gap-3 border-b border-gray-200 p-4 dark:border-gray-700 sm:flex-row sm:items-center">
                                <div>
                                    <h4 class="font-bold text-gray-900 dark:text-white">Regional Pricing</h4>
                                    <p class="text-xs text-gray-500">Harga online dan offline dapat berbeda untuk setiap region.</p>
                                </div>
                                <div class="w-full sm:w-48">
                                    <label class="{{ $label }}">Berlaku Mulai *</label>
                                    <input wire:model="priceEffectiveFrom" type="date" class="{{ $input }}">
                                    @error('priceEffectiveFrom')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                </div>
                            </div>
                            <div class="divide-y divide-gray-100 dark:divide-gray-800">
                                @forelse($regionalPrices as $index => $price)
                                    <div class="grid gap-3 p-4 md:grid-cols-[minmax(160px,1fr)_minmax(0,1fr)_minmax(0,1fr)] md:items-end">
                                        <div>
                                            <p class="text-sm font-bold text-gray-900 dark:text-white">{{ $price['region_name'] }}</p>
                                            <p class="font-mono text-xs text-gray-400">{{ $price['region_code'] }}</p>
                                            <input wire:model="regionalPrices.{{ $index }}.region_id" type="hidden">
                                        </div>
                                        <div>
                                            <label class="{{ $label }}">Harga Offline *</label>
                                            <input wire:model="regionalPrices.{{ $index }}.offline_price" type="number" min="0" step="1" class="{{ $input }}" placeholder="0">
                                            @error("regionalPrices.$index.offline_price")<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                        </div>
                                        <div>
                                            <label class="{{ $label }}">Harga Online *</label>
                                            <input wire:model="regionalPrices.{{ $index }}.online_price" type="number" min="0" step="1" class="{{ $input }}" placeholder="0">
                                            @error("regionalPrices.$index.online_price")<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                        </div>
                                    </div>
                                @empty
                                    <p class="p-5 text-center text-sm text-gray-500">Belum ada region aktif. Tambahkan melalui Master Region Penjualan.</p>
                                @endforelse
                            </div>
                            @error('regionalPrices')<p class="px-4 pb-4 text-xs text-red-600">{{ $message }}</p>@enderror
                        </section>
                        <div class="flex justify-end gap-2 border-t border-gray-200 pt-4 dark:border-gray-700">
                            <button type="button" @click="productFormOpen = false" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-bold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200">Batal</button>
                            <button type="submit" wire:loading.attr="disabled" wire:target="saveProduct" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-50">
                                <span wire:loading.remove wire:target="saveProduct">{{ $editingProductId ? 'Simpan Perubahan' : 'Tambah Product' }}</span>
                                <span wire:loading wire:target="saveProduct">Menyimpan...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </template>

        @if($projectExportPinModalOpen)
            <div class="fixed inset-0 z-[130] flex items-center justify-center p-4">
                <button type="button" aria-label="Tutup modal" class="absolute inset-0 bg-slate-950/55" wire:click="closeProjectBomExport"></button>
                <div class="relative w-full max-w-md rounded-2xl border border-gray-200 bg-white p-6 text-center dark:border-gray-700 dark:bg-gray-900">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600"><x-heroicon-o-lock-closed class="h-7 w-7" /></div>
                    <h3 class="mt-4 text-xl font-bold text-gray-900 dark:text-white">Export {{ ucfirst($projectExportScope) }} Project</h3>
                    <p class="mt-2 text-sm leading-6 text-gray-500">Semua product dengan BOM {{ ucfirst($projectExportScope) }} akan digabung dalam satu dokumen PDF.</p>
                    <form wire:submit="exportProjectBomPdf" class="mt-5">
                        <input wire:model="projectExportPin" type="password" inputmode="numeric" autocomplete="one-time-code" placeholder="Masukkan PIN" class="w-full rounded-xl border border-gray-300 px-4 py-3 text-center text-lg font-bold tracking-[0.3em] dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                        @error('projectExportPin')<p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>@enderror
                        <div class="mt-4 grid grid-cols-2 gap-2">
                            <button type="button" wire:click="closeProjectBomExport" class="rounded-xl border border-gray-300 px-4 py-3 text-sm font-bold text-gray-700 dark:border-gray-600 dark:text-gray-200">Batal</button>
                            <button type="submit" wire:loading.attr="disabled" wire:target="exportProjectBomPdf" class="rounded-xl bg-emerald-600 px-4 py-3 text-sm font-bold text-white hover:bg-emerald-700 disabled:opacity-50">
                                <span wire:loading.remove wire:target="exportProjectBomPdf">Download PDF</span><span wire:loading wire:target="exportProjectBomPdf">Menyiapkan...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

    </div>
</x-filament-panels::page>
