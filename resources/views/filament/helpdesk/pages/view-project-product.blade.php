<x-filament-panels::page>
    @php
        $product = $productRecord;
        $project = $projectRecord;
        $productImageUrl = $product->imageUrl();
        $canManageBom = auth()->user()?->hasRole('SUPERADMIN') || (auth()->user()?->can('edit rnd projects') && auth()->user()?->can('create bill of materials'));
        $canUpdateBomInline = auth()->user()?->hasRole('SUPERADMIN') || (auth()->user()?->can('edit rnd projects') && auth()->user()?->can('edit bill of materials'));
        $canExportBom = auth()->user()?->hasRole('SUPERADMIN') || auth()->user()?->can('view bill of materials');
        $canManageProject = auth()->user()?->hasRole('SUPERADMIN') || auth()->user()?->can('edit rnd projects');
        $statusStyle = match($product->status) {
            'released' => 'bg-emerald-100 text-emerald-700',
            'ready' => 'bg-blue-100 text-blue-700',
            'trial' => 'bg-violet-100 text-violet-700',
            'development' => 'bg-amber-100 text-amber-700',
            'cancelled' => 'bg-red-100 text-red-700',
            default => 'bg-gray-100 text-gray-700',
        };
    @endphp

    <div
        class="space-y-6"
        x-data
        x-on:run-rnd-bom-mapping.window="$nextTick(() => $wire.refreshWipComponentRecipes())"
    >
        <section class="rounded-2xl border border-blue-200 bg-gradient-to-r from-blue-50 to-indigo-50 p-6 dark:border-blue-900/50 dark:from-blue-950/40 dark:to-indigo-950/40">
            <div class="flex flex-col justify-between gap-5 lg:flex-row lg:items-center">
                <div class="flex min-w-0 flex-1 flex-col gap-5 sm:flex-row sm:items-center">
                    @if($productImageUrl)
                        <img src="{{ $productImageUrl }}" alt="{{ $product->name }}" class="h-36 w-36 shrink-0 rounded-2xl border border-blue-200 object-cover dark:border-blue-800">
                    @else
                        <div class="flex h-36 w-36 shrink-0 items-center justify-center rounded-2xl border border-blue-200 bg-white/70 text-blue-400 dark:border-blue-800 dark:bg-gray-900/60">
                            <x-heroicon-o-cake class="h-14 w-14" />
                        </div>
                    @endif
                    <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2 text-sm font-semibold text-blue-700 dark:text-blue-300">
                        <a href="{{ \App\Filament\Helpdesk\Resources\Projects\ProjectResource::getUrl('view', ['record' => $project->id]) }}" class="hover:underline">{{ $project->name }}</a>
                        <span>/</span>
                        <span>Product Release</span>
                    </div>
                    <div class="mt-3 flex flex-wrap items-center gap-3">
                        <h2 class="text-3xl font-bold text-gray-900 dark:text-white">{{ $product->name }}</h2>
                        <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $statusStyle }}">{{ \App\Models\RndProjectProduct::STATUSES[$product->status] ?? ucfirst($product->status) }}</span>
                    </div>
                    <p class="mt-1 font-mono text-sm font-bold text-blue-600">{{ $product->product_code ?: 'Belum ada Product Code' }}</p>
                    <p class="mt-3 max-w-3xl text-sm leading-6 text-gray-600 dark:text-gray-300">{{ $product->description ?: 'Tidak ada deskripsi produk.' }}</p>
                    </div>
                </div>
                <div class="grid min-w-72 gap-3">
                    <div class="rounded-xl border border-blue-100 bg-white/80 p-3 dark:border-blue-900 dark:bg-gray-900/70">
                        <p class="text-xs text-gray-400">Target Rilis</p>
                        <p class="mt-1 font-bold text-gray-900 dark:text-white">{{ $product->release_date?->format('d M Y') ?? 'Belum ditentukan' }}</p>
                    </div>
                    <div class="rounded-xl border border-blue-100 bg-white/80 p-3 dark:border-blue-900 dark:bg-gray-900/70">
                        <p class="text-xs text-gray-400">Cakupan Harga</p>
                        <p class="mt-1 font-bold text-gray-900 dark:text-white">{{ $product->currentRegionalPrices->unique('sales_region_id')->count() }} Region Aktif</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <div class="border-b border-gray-200 p-5 dark:border-gray-700">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white">Regional Pricing</h3>
                <p class="text-sm text-gray-500">Harga aktif online dan offline untuk setiap wilayah penjualan.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[680px] text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-800">
                        <tr><th class="px-5 py-3 text-left">Region</th><th class="px-5 py-3 text-right">Offline</th><th class="px-5 py-3 text-right">Online</th><th class="px-5 py-3 text-left">Berlaku Mulai</th><th class="px-5 py-3 text-left">Status</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($product->currentRegionalPrices->unique('sales_region_id')->sortBy('region.sort_order') as $price)
                            <tr>
                                <td class="px-5 py-3"><p class="font-bold text-gray-900 dark:text-white">{{ $price->region->name }}</p><p class="font-mono text-xs text-gray-400">{{ $price->region->code }}</p></td>
                                <td class="px-5 py-3 text-right font-bold">Rp {{ number_format((float) $price->offline_price, 0, ',', '.') }}</td>
                                <td class="px-5 py-3 text-right font-bold">Rp {{ number_format((float) $price->online_price, 0, ',', '.') }}</td>
                                <td class="px-5 py-3">{{ $price->effective_from->format('d M Y') }}</td>
                                <td class="px-5 py-3"><span class="rounded-full bg-emerald-50 px-2 py-1 text-xs font-bold text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">Active</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-10 text-center text-gray-500">Belum ada harga regional yang aktif.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <div class="flex flex-col justify-between gap-3 border-b border-gray-200 p-5 dark:border-gray-700 lg:flex-row lg:items-center">
                <div>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Master Bahan ESB</h3>
                    <p class="text-sm text-gray-500">Daftar bahan baru yang perlu dibuat sebagai Master Product ESB untuk product release ini.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('helpdesk.rnd-products.esb-materials-export', ['project' => $project->id, 'product' => $product->id, 'format' => 'xlsx']) }}" class="inline-flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-3.5 py-2 text-sm font-bold text-emerald-700 hover:bg-emerald-100">
                        <x-heroicon-o-table-cells class="h-4 w-4" /> Excel
                    </a>
                    <a href="{{ route('helpdesk.rnd-products.esb-materials-export', ['project' => $project->id, 'product' => $product->id, 'format' => 'pdf']) }}" class="inline-flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-3.5 py-2 text-sm font-bold text-red-700 hover:bg-red-100">
                        <x-heroicon-o-document-arrow-down class="h-4 w-4" /> PDF
                    </a>
                    @if($canManageProject)
                        <button type="button" wire:click="openEsbMaterialForm" wire:loading.attr="disabled" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-3.5 py-2 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-50">
                            <x-heroicon-o-plus class="h-4 w-4" /> Tambah Bahan
                        </button>
                    @endif
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1050px] text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-800">
                    <tr>
                        <th class="px-4 py-3 text-left">Kode & Nama</th>
                        <th class="px-4 py-3 text-left">Kategori</th>
                        <th class="px-4 py-3 text-left">Unit</th>
                        <th class="px-4 py-3 text-left">SKU</th>
                        <th class="px-4 py-3 text-left">Status ESB</th>
                        <th class="px-4 py-3 text-left">Keterangan</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse($product->esbMaterials as $esbMaterial)
                        @php
                            $esbStatusStyle = match($esbMaterial->status) {
                                'synced' => 'bg-emerald-50 text-emerald-700',
                                'failed' => 'bg-red-50 text-red-700',
                                'syncing' => 'bg-blue-50 text-blue-700',
                                default => 'bg-gray-100 text-gray-700',
                            };
                        @endphp
                        <tr class="align-top">
                            <td class="px-4 py-3">
                                <p class="font-mono text-xs font-bold text-blue-600">{{ $esbMaterial->product_code }}</p>
                                <p class="mt-1 font-bold text-gray-900 dark:text-white">{{ $esbMaterial->product_name }}</p>
                                @if($esbMaterial->esb_product_id)<p class="mt-1 text-xs text-gray-400">ESB ID: {{ $esbMaterial->esb_product_id }}</p>@endif
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-800 dark:text-gray-200">{{ $esbMaterial->category_name ?: 'ID '.$esbMaterial->category_id }}</p>
                                <p class="mt-1 text-xs text-gray-500">{{ $esbMaterial->sub_category_name ?: 'Sub ID '.$esbMaterial->sub_category_id }}</p>
                            </td>
                            <td class="px-4 py-3"><p class="font-bold">{{ $esbMaterial->uom_name }}</p><p class="text-xs text-gray-400">ID {{ $esbMaterial->uom_id }} · Base {{ rtrim(rtrim($esbMaterial->conversion_factor, '0'), '.') }}</p></td>
                            <td class="px-4 py-3 font-mono text-xs">{{ $esbMaterial->sku }}</td>
                            <td class="px-4 py-3"><span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $esbStatusStyle }}">{{ \App\Models\RndProductEsbMaterial::STATUSES[$esbMaterial->status] ?? ucfirst($esbMaterial->status) }}</span></td>
                            <td class="max-w-72 px-4 py-3 text-xs">
                                @if($esbMaterial->sync_error)<p class="line-clamp-3 text-red-600" title="{{ $esbMaterial->sync_error }}">{{ $esbMaterial->sync_error }}</p>
                                @else<p class="line-clamp-3 text-gray-500">{{ $esbMaterial->notes ?: '-' }}</p>@endif
                            </td>
                            <td class="px-4 py-3">
                                @if($canManageProject)
                                    <div class="flex justify-end gap-1.5">
                                        @if($esbMaterial->status !== 'synced')
                                            <button type="button" wire:click="openEsbMaterialForm({{ $esbMaterial->id }})" class="rounded-lg border border-gray-300 p-2 text-gray-600 hover:bg-gray-50" title="Edit"><x-heroicon-o-pencil-square class="h-4 w-4" /></button>
                                            <button type="button" wire:click="syncEsbMaterial({{ $esbMaterial->id }})" wire:loading.attr="disabled" wire:target="syncEsbMaterial({{ $esbMaterial->id }})" class="rounded-lg bg-blue-600 px-3 py-2 text-xs font-bold text-white disabled:opacity-50">
                                                {{ $esbMaterial->status === 'failed' ? 'Retry' : 'Create to ESB' }}
                                            </button>
                                            <button type="button" wire:click="deleteEsbMaterial({{ $esbMaterial->id }})" wire:confirm="Hapus draft bahan ini?" class="rounded-lg border border-red-200 p-2 text-red-600 hover:bg-red-50" title="Hapus"><x-heroicon-o-trash class="h-4 w-4" /></button>
                                        @else
                                            <span class="inline-flex items-center gap-1 text-xs font-bold text-emerald-600"><x-heroicon-o-check-circle class="h-4 w-4" /> Selesai</span>
                                        @endif
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-5 py-12 text-center"><x-heroicon-o-cube-transparent class="mx-auto h-10 w-10 text-gray-300" /><p class="mt-3 font-bold text-gray-700 dark:text-gray-200">Belum ada bahan baru</p><p class="mt-1 text-sm text-gray-500">Tambahkan daftar bahan yang perlu dibuat ke Master Product ESB.</p></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <div class="flex flex-col justify-between gap-3 border-b border-gray-200 p-5 dark:border-gray-700 sm:flex-row sm:items-center">
                <div>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Marketing Materials</h3>
                    <p class="text-sm text-gray-500">Design packaging, sticker, foto produk, katalog, dan aset promosi lainnya.</p>
                </div>
                @if($canManageProject)
                    <button type="button" wire:click="openMaterialForm" class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-3.5 py-2 text-sm font-bold text-white hover:bg-blue-700">
                        <x-heroicon-o-arrow-up-tray class="h-4 w-4" /> Upload Material
                    </button>
                @endif
            </div>
            <div class="grid gap-4 p-5 sm:grid-cols-2 xl:grid-cols-3">
                @forelse($product->marketingMaterials as $material)
                    <article class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
                        @if($material->isImage())
                            <a href="{{ $material->fileUrl() }}" target="_blank"><img src="{{ $material->fileUrl() }}" alt="{{ $material->title }}" class="h-44 w-full object-cover"></a>
                        @else
                            <a href="{{ $material->fileUrl() }}" target="_blank" class="flex h-44 items-center justify-center bg-indigo-50 text-indigo-500 dark:bg-indigo-950/30">
                                <x-heroicon-o-document class="h-14 w-14" />
                            </a>
                        @endif
                        <div class="p-4">
                            <span class="rounded-full bg-blue-50 px-2 py-1 text-[11px] font-bold text-blue-700 dark:bg-blue-950/50 dark:text-blue-300">{{ \App\Models\RndProjectMarketingMaterial::TYPES[$material->type] ?? $material->type }}</span>
                            <a href="{{ $material->fileUrl() }}" target="_blank" class="mt-2 block truncate font-bold text-gray-900 hover:text-blue-600 dark:text-white">{{ $material->title }}</a>
                            <p class="mt-1 truncate text-xs text-gray-500">{{ $material->original_name }} · {{ number_format($material->file_size / 1024, 0, ',', '.') }} KB</p>
                            @if($material->notes)<p class="mt-2 line-clamp-2 text-xs leading-5 text-gray-500">{{ $material->notes }}</p>@endif
                            <div class="mt-3 flex gap-2">
                                <a href="{{ $material->fileUrl() }}" target="_blank" class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-center text-xs font-bold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200">Lihat File</a>
                                <a href="{{ $material->downloadUrl() }}" download="{{ $material->original_name }}" class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-lg bg-blue-600 px-3 py-2 text-xs font-bold text-white hover:bg-blue-700">
                                    <x-heroicon-o-arrow-down-tray class="h-4 w-4" /> Download
                                </a>
                                @if($canManageProject)
                                    <button wire:click="deleteMaterial({{ $material->id }})" wire:confirm="Hapus marketing material ini?" class="rounded-lg border border-red-200 p-2 text-red-600 hover:bg-red-50 dark:border-red-900"><x-heroicon-o-trash class="h-4 w-4" /></button>
                                @endif
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="col-span-full rounded-xl border border-dashed border-gray-300 py-12 text-center dark:border-gray-700">
                        <x-heroicon-o-photo class="mx-auto h-10 w-10 text-gray-300" />
                        <h4 class="mt-3 font-bold text-gray-700 dark:text-gray-200">Belum ada Marketing Material</h4>
                        <p class="mt-1 text-sm text-gray-500">Upload aset pendukung untuk product ini.</p>
                    </div>
                @endforelse
            </div>
        </section>

        <section wire:init="loadAllBomComponents" class="rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <div class="flex flex-col justify-between gap-3 border-b border-gray-200 p-5 dark:border-gray-700 sm:flex-row sm:items-center">
                <div>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Bill of Material</h3>
                    <p class="text-sm text-gray-500">{{ $product->boms->count() }} BOM digunakan untuk membuat produk ini.</p>
                </div>
                @if($canExportBom)
                    <div class="flex flex-wrap gap-2">
                        <button type="button" wire:click="refreshWipComponentRecipes" wire:loading.attr="disabled" wire:target="refreshWipComponentRecipes,loadAllBomComponents" class="inline-flex items-center gap-2 rounded-lg border border-violet-200 bg-violet-50 px-3.5 py-2 text-sm font-bold text-violet-700 hover:bg-violet-100 disabled:opacity-50 dark:border-violet-900 dark:bg-violet-950/40 dark:text-violet-300">
                            <x-heroicon-o-arrow-path class="h-4 w-4" />
                            <span wire:loading.remove wire:target="refreshWipComponentRecipes,loadAllBomComponents">Refresh Mapping</span>
                            <span wire:loading wire:target="refreshWipComponentRecipes,loadAllBomComponents">Memetakan...</span>
                        </button>
                        <button type="button" wire:click="openExportPdf" class="inline-flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-3.5 py-2 text-sm font-bold text-emerald-700 hover:bg-emerald-100 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300">
                            <x-heroicon-o-document-arrow-down class="h-4 w-4" /> Export PDF
                        </button>
                        @if($canManageBom)
                            <button type="button" wire:click="openBomPicker('main')" class="inline-flex items-center gap-2 rounded-lg border border-blue-200 bg-blue-50 px-3.5 py-2 text-sm font-bold text-blue-700 hover:bg-blue-100 dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-300">
                                <x-heroicon-o-arrow-down-tray class="h-4 w-4" /> Add Existing Main
                            </button>
                            <a href="{{ \App\Filament\Helpdesk\Pages\CreateBomRecipePage::getUrl(['project' => $project->id, 'product' => $product->id]) }}" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-3.5 py-2 text-sm font-bold text-white hover:bg-blue-700">
                                <x-heroicon-o-plus class="h-4 w-4" /> Create Main Recipe
                            </a>
                        @endif
                    </div>
                @endif
            </div>
            @if($autoWipComponentError)
                <div class="flex items-center justify-between gap-3 border-b border-red-200 bg-red-50 px-5 py-3 text-xs text-red-700 dark:border-red-900 dark:bg-red-950/20 dark:text-red-300">
                    <span>Mapping WIP belum selesai: {{ $autoWipComponentError }}</span>
                    <button type="button" wire:click="refreshWipComponentRecipes" class="shrink-0 font-bold underline">Coba lagi</button>
                </div>
            @endif

            @php
                $bomGroups = [
                    'main' => ['title' => 'Main Recipe', 'description' => 'Resep utama yang menghasilkan produk ini.', 'button' => 'Add Main Recipe', 'optional' => false],
                    'component' => ['title' => 'Components', 'description' => 'Resep turunan seperti sponge, filling, cream, atau sauce.', 'button' => 'Add Component', 'optional' => false],
                    'packaging' => ['title' => 'Packaging', 'description' => 'BOM kebutuhan box, cup, label, dan kemasan produk.', 'button' => 'Add Packaging', 'optional' => false],
                ];
                $childGroups = array_filter($bomGroups, fn ($key) => $key !== 'main', ARRAY_FILTER_USE_KEY);
                $mainBoms = $product->boms->filter(fn ($bom) => $bom->pivot->usage_type === 'main');
                $mainIds = $mainBoms->pluck('id');
                $unassignedBoms = $product->boms->filter(fn ($bom) =>
                    $bom->pivot->usage_type !== 'main'
                    && (! $bom->pivot->parent_rnd_project_bom_id || ! $mainIds->contains($bom->pivot->parent_rnd_project_bom_id))
                );
            @endphp
            <div class="space-y-4 p-5">
                @forelse($mainBoms as $mainBom)
                    <section class="overflow-hidden rounded-xl border border-blue-200 bg-blue-50/20 dark:border-blue-900 dark:bg-blue-950/10">
                        <div class="flex flex-col gap-3 border-b border-blue-200 bg-blue-50 px-4 py-4 dark:border-blue-900 dark:bg-blue-950/30 lg:flex-row lg:items-center">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-600 text-white"><x-heroicon-o-beaker class="h-5 w-5" /></div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2"><span class="rounded-full bg-blue-600 px-2 py-0.5 text-[10px] font-bold text-white">MAIN RECIPE</span><span class="font-mono text-xs font-bold text-blue-700 dark:text-blue-300">{{ $mainBom->bom_code ?: 'BOM-'.$mainBom->esb_bom_id }}</span></div>
                                <h4 class="mt-1 truncate font-bold text-gray-900 dark:text-white">{{ $mainBom->bom_name }}</h4>
                                <p class="truncate text-xs text-gray-500">{{ $mainBom->product_name ?: '-' }} · {{ $mainBom->uom_name ?: '-' }}</p>
                            </div>
                            <div class="flex shrink-0 gap-2">
                                <a href="{{ \App\Filament\Helpdesk\Pages\ViewBomPage::getUrl(['project' => $project->id, 'product' => $product->id, 'bom' => $mainBom->esb_bom_id]) }}" class="rounded-lg border border-blue-200 bg-white px-3 py-2 text-xs font-bold text-blue-700 dark:border-blue-800 dark:bg-gray-900">View Recipe</a>
                                @if($canManageBom)
                                    <a href="{{ \App\Filament\Helpdesk\Pages\EditBomRecipePage::getUrl(['project' => $project->id, 'product' => $product->id, 'bom' => $mainBom->esb_bom_id]) }}" class="rounded-lg bg-blue-600 px-3 py-2 text-xs font-bold text-white">Update</a>
                                    <button wire:click="detachBom({{ $mainBom->esb_bom_id }})" wire:confirm="Lepas Main Recipe ini? Item di bawahnya akan masuk kelompok Belum Ditentukan." class="rounded-lg border border-red-200 bg-white p-2 text-red-600 dark:border-red-900 dark:bg-gray-900"><x-heroicon-o-link-slash class="h-4 w-4" /></button>
                                @endif
                            </div>
                        </div>

                        @include('filament.helpdesk.rnd-projects.partials.inline-bom-components', ['bom' => $mainBom])

                        <div class="grid grid-cols-1 gap-3 p-4">
                            @foreach($childGroups as $usageType => $group)
                                @php
                                    $autoRecipes = $usageType === 'component'
                                        ? ($autoWipComponentRecipes[$mainBom->id] ?? [])
                                        : [];
                                    $autoPackaging = $usageType === 'packaging'
                                        ? ($autoPackagingItems[$mainBom->id] ?? [])
                                        : [];
                                    $autoRecipeBomIds = collect($autoRecipes)->pluck('bomID')->map(fn ($id) => (int) $id);
                                    $children = $product->boms->filter(
                                        fn ($bom) => $bom->pivot->usage_type === $usageType
                                            && (int) $bom->pivot->parent_rnd_project_bom_id === $mainBom->id
                                            && ! $autoRecipeBomIds->contains((int) $bom->esb_bom_id)
                                    );
                                @endphp
                                <section class="overflow-hidden rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                                    <div class="flex items-center justify-between gap-2 border-b border-gray-200 px-3 py-2.5 dark:border-gray-700">
                                        <div>
                                            <div class="flex items-center gap-1.5"><h5 class="text-xs font-bold text-gray-800 dark:text-gray-100">{{ $group['title'] }}</h5>@if($group['optional'])<span class="text-[9px] font-bold text-gray-400">OPTIONAL</span>@endif</div>
                                            <p class="text-[10px] text-gray-400">{{ $children->count() + count($autoRecipes) + count($autoPackaging) }} item</p>
                                        </div>
                                        @if($canManageBom)
                                            <button type="button" wire:click="openBomPicker('{{ $usageType }}', {{ $mainBom->id }})" class="inline-flex items-center gap-1 rounded-md bg-blue-50 px-2 py-1.5 text-[11px] font-bold text-blue-700 hover:bg-blue-100 dark:bg-blue-950/40 dark:text-blue-300"><x-heroicon-o-plus class="h-3.5 w-3.5" /> {{ $group['button'] }}</button>
                                        @endif
                                    </div>
                                    <div class="divide-y divide-gray-100 dark:divide-gray-800">
                                        @foreach($autoRecipes as $autoRecipe)
                                            @php
                                                $managedAutoBom = $product->boms->first(
                                                    fn ($item) => (int) $item->esb_bom_id === (int) $autoRecipe['bomID']
                                                        && (int) $item->pivot->parent_rnd_project_bom_id === $mainBom->id
                                                );
                                                $managedAutoEditing = $managedAutoBom
                                                    ? (bool) ($bomComponentEditing[$managedAutoBom->id] ?? false)
                                                    : false;
                                            @endphp
                                            <div class="bg-blue-50/30 p-3 dark:bg-blue-950/10">
                                                @if($managedAutoEditing)
                                                    @include('filament.helpdesk.rnd-projects.partials.inline-bom-components', ['bom' => $managedAutoBom])
                                                @else
                                                <div class="flex items-start justify-between gap-2">
                                                    <div class="min-w-0">
                                                        <div class="flex flex-wrap items-center gap-1.5">
                                                            <span class="rounded bg-blue-100 px-1.5 py-0.5 text-[9px] font-bold text-blue-700 dark:bg-blue-950 dark:text-blue-300">AUTO · BARANG WIP</span>
                                                            <span class="font-mono text-[9px] text-gray-400">{{ $autoRecipe['bomCode'] ?: 'BOM-'.$autoRecipe['bomID'] }}</span>
                                                        </div>
                                                        <p class="mt-1 truncate text-xs font-bold text-gray-900 dark:text-white">{{ $autoRecipe['bomName'] }}</p>
                                                        <p class="truncate text-[10px] text-gray-500">{{ $autoRecipe['productCode'] }} | {{ $autoRecipe['productName'] }} · {{ $autoRecipe['uomName'] ?: '-' }}</p>
                                                    </div>
                                                    <div class="flex shrink-0 flex-col items-end gap-1.5">
                                                        <span class="rounded-md bg-white px-2 py-1 text-[9px] font-bold text-blue-700 ring-1 ring-blue-100 dark:bg-gray-900 dark:ring-blue-900">
                                                            Dipakai {{ rtrim(rtrim(number_format($autoRecipe['sourceQty'], 4, '.', ''), '0'), '.') }} {{ $autoRecipe['sourceUnit'] }}
                                                        </span>
                                                        @if($canUpdateBomInline)
                                                            <button
                                                                type="button"
                                                                wire:click="{{ $managedAutoBom ? 'editBomComponents('.$managedAutoBom->id.')' : 'editMappedComponentBom('.$autoRecipe['bomID'].', '.$mainBom->id.')' }}"
                                                                wire:loading.attr="disabled"
                                                                class="rounded-md bg-blue-600 px-2.5 py-1.5 text-[10px] font-bold text-white hover:bg-blue-700 disabled:opacity-50"
                                                            >
                                                                Edit BOM
                                                            </button>
                                                        @endif
                                                    </div>
                                                </div>
                                                <div class="mt-2 overflow-hidden rounded-md border border-blue-100 bg-white dark:border-blue-900 dark:bg-gray-900">
                                                    <div class="grid grid-cols-[minmax(0,1fr)_4rem_4.5rem] bg-blue-50 px-2 py-1.5 text-[9px] font-bold uppercase text-blue-700 dark:bg-blue-950/30 dark:text-blue-300">
                                                        <span>Bahan Resep</span><span>Unit</span><span class="text-right">Qty</span>
                                                    </div>
                                                    <div class="divide-y divide-gray-100 dark:divide-gray-800">
                                                        @forelse($autoRecipe['bomDetails'] as $recipeItem)
                                                            <div class="grid grid-cols-[minmax(0,1fr)_4rem_4.5rem] items-center px-2 py-1.5 text-[10px]">
                                                                <span class="min-w-0 truncate font-medium text-gray-800 dark:text-gray-200" title="{{ $recipeItem['productName'] }}">{{ $recipeItem['productName'] }}</span>
                                                                <span class="font-semibold text-gray-500">{{ $recipeItem['uomName'] ?: '-' }}</span>
                                                                <span class="text-right font-bold text-gray-800 dark:text-gray-200">{{ rtrim(rtrim(number_format((float) $recipeItem['qty'], 4, '.', ''), '0'), '.') }}</span>
                                                            </div>
                                                        @empty
                                                            <p class="px-2 py-3 text-center text-[10px] text-gray-400">Resep tidak memiliki bahan.</p>
                                                        @endforelse
                                                    </div>
                                                </div>
                                                @endif
                                            </div>
                                        @endforeach
                                        @foreach($autoPackaging as $packagingItem)
                                            <div class="bg-violet-50/40 p-3 dark:bg-violet-950/10">
                                                <div class="flex items-start justify-between gap-3">
                                                    <div class="min-w-0">
                                                        <div class="flex flex-wrap items-center gap-1.5">
                                                            <span class="rounded bg-violet-100 px-1.5 py-0.5 text-[9px] font-bold text-violet-700 dark:bg-violet-950 dark:text-violet-300">AUTO · PACKAGING</span>
                                                            <span class="font-mono text-[10px] font-bold text-violet-600">{{ $packagingItem['productCode'] ?: '-' }}</span>
                                                        </div>
                                                        <p class="mt-1 truncate text-xs font-bold text-gray-900 dark:text-white" title="{{ $packagingItem['productName'] }}">{{ $packagingItem['productName'] ?: 'Packaging Product' }}</p>
                                                    </div>
                                                    <span class="shrink-0 rounded-md bg-white px-2 py-1 text-[10px] font-bold text-violet-700 ring-1 ring-violet-100 dark:bg-gray-900 dark:ring-violet-900">
                                                        {{ rtrim(rtrim(number_format((float) $packagingItem['qty'], 4, '.', ''), '0'), '.') }} {{ $packagingItem['uomName'] ?: '-' }}
                                                    </span>
                                                </div>
                                            </div>
                                        @endforeach
                                        @foreach($children as $bom)
                                            <div class="p-3">
                                                <p class="truncate text-xs font-bold text-gray-900 dark:text-white">{{ $bom->bom_name }}</p>
                                                <p class="truncate font-mono text-[10px] text-gray-500">{{ $bom->bom_code ?: 'BOM-'.$bom->esb_bom_id }} · {{ $bom->uom_name ?: '-' }}</p>
                                                <div class="mt-2 flex gap-1.5">
                                                    <a href="{{ \App\Filament\Helpdesk\Pages\ViewBomPage::getUrl(['project' => $project->id, 'product' => $product->id, 'bom' => $bom->esb_bom_id]) }}" class="flex-1 rounded-md border border-gray-300 px-2 py-1.5 text-center text-[10px] font-bold text-gray-700 dark:border-gray-600 dark:text-gray-200">View</a>
                                                    @if($canManageBom)
                                                        <select wire:change="assignBomToMain({{ $bom->esb_bom_id }}, $event.target.value)" title="Pindahkan ke Main Recipe lain" class="min-w-0 max-w-24 rounded-md border border-gray-300 px-1 text-[10px] dark:border-gray-600 dark:bg-gray-800">
                                                            @foreach($mainBoms as $mainOption)<option value="{{ $mainOption->id }}" @selected($mainOption->id === $mainBom->id)>{{ $mainOption->bom_name }}</option>@endforeach
                                                        </select>
                                                        <button wire:click="detachBom({{ $bom->esb_bom_id }})" wire:confirm="Lepas item ini?" class="rounded-md border border-red-200 p-1.5 text-red-600 dark:border-red-900"><x-heroicon-o-x-mark class="h-3.5 w-3.5" /></button>
                                                    @endif
                                                </div>
                                                @include('filament.helpdesk.rnd-projects.partials.inline-bom-components', ['bom' => $bom])
                                            </div>
                                        @endforeach
                                        @if($children->isEmpty() && count($autoRecipes) === 0 && count($autoPackaging) === 0)
                                            <p class="px-3 py-5 text-center text-[11px] text-gray-400">Belum ada item.</p>
                                        @endif
                                    </div>
                                </section>
                            @endforeach
                        </div>
                    </section>
                @empty
                    <div class="rounded-xl border border-dashed border-blue-300 py-12 text-center dark:border-blue-800">
                        <x-heroicon-o-beaker class="mx-auto h-10 w-10 text-blue-300" />
                        <h4 class="mt-3 font-bold text-gray-700 dark:text-gray-200">Belum ada Main Recipe</h4>
                        <p class="mt-1 text-sm text-gray-500">Buat atau tambahkan Main Recipe terlebih dahulu sebelum mengisi Component, Packaging, dan Support.</p>
                    </div>
                @endforelse

                @if($unassignedBoms->isNotEmpty())
                    <section class="overflow-hidden rounded-xl border border-amber-200 bg-amber-50/30 dark:border-amber-900 dark:bg-amber-950/10">
                        <div class="border-b border-amber-200 px-4 py-3 dark:border-amber-900">
                            <h4 class="text-sm font-bold text-amber-800 dark:text-amber-300">Belum Ditentukan</h4>
                            <p class="text-xs text-amber-700/70 dark:text-amber-400">Data lama berikut belum terhubung ke Main Recipe. Pilih induknya agar masuk ke kelompok yang benar.</p>
                        </div>
                        <div class="divide-y divide-amber-100 dark:divide-amber-900/50">
                            @foreach($unassignedBoms as $bom)
                                <div class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center">
                                    <div class="min-w-0 flex-1"><p class="truncate text-sm font-bold text-gray-900 dark:text-white">{{ $bom->bom_name }}</p><p class="text-xs text-gray-500">{{ \App\Models\RndProjectProduct::BOM_USAGE_TYPES[$bom->pivot->usage_type] ?? $bom->pivot->usage_type }}</p></div>
                                    @if($canManageBom && $mainBoms->isNotEmpty())
                                        <select wire:change="assignBomToMain({{ $bom->esb_bom_id }}, $event.target.value)" class="rounded-lg border border-amber-300 bg-white px-3 py-2 text-xs dark:border-amber-800 dark:bg-gray-900">
                                            <option value="">Pilih Main Recipe...</option>
                                            @foreach($mainBoms as $mainOption)<option value="{{ $mainOption->id }}">{{ $mainOption->bom_name }}</option>@endforeach
                                        </select>
                                    @endif
                                </div>
                                @include('filament.helpdesk.rnd-projects.partials.inline-bom-components', ['bom' => $bom])
                            @endforeach
                        </div>
                    </section>
                @endif
            </div>
        </section>

        @if($inlineProductModalOpen)
            <div wire:init="loadInlineProducts" class="fixed inset-0 z-[160] flex items-center justify-center p-3 sm:p-6">
                <button type="button" aria-label="Tutup modal" class="absolute inset-0 bg-slate-950/55" wire:click="closeModal('inlineProduct')"></button>
                <x-rnd.picker-modal
                    :title="$inlineProductTarget === 'result' ? 'Pilih Product Hasil' : 'Tambah Komponen BOM'"
                    description="Pilih product aktif dari Master Product ESB."
                    max-width="7xl"
                    x-data="{ unitFilter: '', conversionFilter: '' }"
                >
                    <x-slot:close>
                        <button type="button" wire:click="closeModal('inlineProduct')" class="rounded-lg border border-gray-200 p-2 text-gray-500 hover:bg-gray-50 dark:border-gray-700"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                    </x-slot:close>

                    <div class="relative min-h-[280px] flex-1 overflow-auto">
                        <div wire:loading.flex wire:target="loadInlineProducts,inlineProductNameSearch,inlineProductCodeSearch,previousInlineProductPage,nextInlineProductPage,goToInlineProductPage" class="absolute inset-0 z-40 items-center justify-center bg-white/80 backdrop-blur-[2px] dark:bg-gray-900/80" role="status">
                            <div class="relative h-14 w-14">
                                <div class="absolute inset-0 rounded-full border-4 border-blue-100 dark:border-blue-950"></div>
                                <div class="absolute inset-0 animate-spin rounded-full border-4 border-transparent border-r-blue-400 border-t-blue-600"></div>
                                <div class="absolute inset-[10px] animate-[spin_1.2s_linear_infinite_reverse] rounded-full border-2 border-transparent border-b-blue-500"></div>
                            </div>
                            <span class="sr-only">Memuat daftar produk...</span>
                        </div>
                        <table class="w-full min-w-[1120px] table-fixed text-sm">
                            <thead class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800">
                                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    <th class="w-[16%] px-4 pt-3">Product Code</th>
                                    <th class="w-[23%] px-4 pt-3">Product Name</th>
                                    <th class="w-[18%] px-4 pt-3">Kategori</th>
                                    <th class="w-[19%] px-4 pt-3">Subkategori</th>
                                    <th class="w-[10%] px-4 pt-3">Unit</th>
                                    <th class="w-[14%] px-4 pt-3">Conversion Factor</th>
                                </tr>
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <th class="px-4 pb-3 pt-2"><input wire:model.live.debounce.700ms="inlineProductCodeSearch" type="search" placeholder="Cari semua kode..." class="w-full rounded-md border border-gray-300 px-2.5 py-2 text-xs font-normal normal-case tracking-normal dark:border-gray-600 dark:bg-gray-900"></th>
                                    <th class="px-4 pb-3 pt-2"><input wire:model.live.debounce.700ms="inlineProductNameSearch" type="search" placeholder="Cari semua nama..." class="w-full rounded-md border border-gray-300 px-2.5 py-2 text-xs font-normal normal-case tracking-normal dark:border-gray-600 dark:bg-gray-900"></th>
                                    <th class="px-4 pb-3 pt-2">
                                        <select wire:model.live="inlineProductCategoryId" class="w-full rounded-md border border-gray-300 bg-white px-2.5 py-2 text-xs font-normal normal-case dark:border-gray-600 dark:bg-gray-900">
                                            <option value="">- Semua Kategori -</option>
                                            @foreach($inlineProductCategoryOptions as $categoryId => $categoryName)<option value="{{ $categoryId }}">{{ $categoryName }}</option>@endforeach
                                        </select>
                                    </th>
                                    <th class="px-4 pb-3 pt-2">
                                        <select wire:model.live="inlineProductSubCategoryId" class="w-full rounded-md border border-gray-300 bg-white px-2.5 py-2 text-xs font-normal normal-case dark:border-gray-600 dark:bg-gray-900">
                                            <option value="">- Semua Subkategori -</option>
                                            @foreach($inlineProductSubCategoryOptions as $subCategoryId => $subCategoryName)<option value="{{ $subCategoryId }}">{{ $subCategoryName }}</option>@endforeach
                                        </select>
                                    </th>
                                    <th class="px-4 pb-3 pt-2">
                                        <select x-model="unitFilter" class="w-full rounded-md border border-gray-300 bg-white px-2.5 py-2 text-xs font-normal normal-case dark:border-gray-600 dark:bg-gray-900">
                                            <option value="">- Semua -</option>
                                            @foreach($inlineProductUnitOptions as $unit)<option value="{{ mb_strtolower($unit) }}">{{ $unit }}</option>@endforeach
                                        </select>
                                    </th>
                                    <th class="px-4 pb-3 pt-2"><input x-model.debounce.150ms="conversionFilter" type="search" placeholder="Cari konversi..." class="w-full rounded-md border border-gray-300 px-2.5 py-2 text-xs font-normal normal-case tracking-normal dark:border-gray-600 dark:bg-gray-900"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @forelse($inlineProductOptions as $productDetailId => $option)
                                    @php $conversionLabel = (rtrim(rtrim(number_format((float) ($option['conversionFactor'] ?? 1), 4, '.', ''), '0'), '.') ?: '0').' '.(($option['baseUnit'] ?? '') ?: ($option['unit'] ?? '')); @endphp
                                    <tr
                                        wire:key="inline-product-{{ $productDetailId }}"
                                        wire:click="selectInlineProduct({{ $productDetailId }})"
                                        data-unit="{{ mb_strtolower($option['unit'] ?? '') }}"
                                        data-conversion="{{ mb_strtolower($conversionLabel) }}"
                                        x-show="$el.dataset.unit.includes(unitFilter) && $el.dataset.conversion.includes(conversionFilter.toLowerCase())"
                                        class="cursor-pointer text-gray-700 transition hover:bg-blue-50 dark:text-gray-200 dark:hover:bg-blue-950/30"
                                    >
                                        <td class="px-4 py-3"><p class="truncate font-mono font-semibold text-blue-700 dark:text-blue-300">{{ $option['productCode'] ?: '-' }}</p></td>
                                        <td class="px-4 py-3"><p class="truncate font-semibold text-gray-900 dark:text-white">{{ $option['productName'] ?: '-' }}</p></td>
                                        <td class="px-4 py-3"><p class="truncate">{{ $option['categoryName'] ?: '-' }}</p></td>
                                        <td class="px-4 py-3"><p class="truncate">{{ $option['subCategoryName'] ?: '-' }}</p></td>
                                        <td class="px-4 py-3"><span class="rounded-md bg-blue-50 px-2 py-1 text-xs font-semibold text-blue-700 dark:bg-blue-950/50 dark:text-blue-300">{{ $option['unit'] ?: '-' }}</span></td>
                                        <td class="px-4 py-3 font-medium">{{ $conversionLabel }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="px-5 py-14 text-center text-gray-500">Product tidak ditemukan.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @php
                        $inlineProductLastPage = max(1, (int) ceil($inlineProductTotal / max(1, $inlineProductPerPage)));
                        $inlinePageStart = max(1, $inlineProductPage - 4);
                        $inlinePageEnd = min($inlineProductLastPage, $inlinePageStart + 8);
                        $inlinePageStart = max(1, $inlinePageEnd - 8);
                    @endphp
                    <div class="flex flex-col gap-3 border-t border-gray-200 px-4 py-3 dark:border-gray-700 lg:flex-row lg:items-center lg:justify-between">
                        <p class="text-xs font-medium text-gray-600 dark:text-gray-300">Halaman {{ $inlineProductPage }} dari {{ $inlineProductLastPage }}</p>
                        <div class="max-w-full overflow-x-auto">
                            <div class="inline-flex min-w-max overflow-hidden rounded-lg border border-gray-300 dark:border-gray-600">
                                <button type="button" wire:click="goToInlineProductPage(1)" @disabled($inlineProductPage <= 1) class="border-r border-gray-300 px-3 py-2 text-xs font-semibold disabled:opacity-40 dark:border-gray-600">First</button>
                                <button type="button" wire:click="goToInlineProductPage({{ max(1, $inlineProductPage - 1) }})" @disabled($inlineProductPage <= 1) class="border-r border-gray-300 px-3 py-2 text-sm font-bold disabled:opacity-40 dark:border-gray-600">&laquo;</button>
                                @foreach(range($inlinePageStart, $inlinePageEnd) as $pageNumber)
                                    <button type="button" wire:click="goToInlineProductPage({{ $pageNumber }})" @disabled($pageNumber === $inlineProductPage) class="border-r border-gray-300 px-3.5 py-2 text-xs font-semibold dark:border-gray-600 {{ $pageNumber === $inlineProductPage ? 'bg-blue-600 text-white disabled:opacity-100' : 'hover:bg-gray-50 dark:hover:bg-gray-800' }}">{{ $pageNumber }}</button>
                                @endforeach
                                <button type="button" wire:click="goToInlineProductPage({{ min($inlineProductLastPage, $inlineProductPage + 1) }})" @disabled($inlineProductPage >= $inlineProductLastPage) class="border-r border-gray-300 px-3 py-2 text-sm font-bold disabled:opacity-40 dark:border-gray-600">&raquo;</button>
                                <button type="button" wire:click="goToInlineProductPage({{ $inlineProductLastPage }})" @disabled($inlineProductPage >= $inlineProductLastPage) class="px-3 py-2 text-xs font-semibold disabled:opacity-40">Last</button>
                            </div>
                        </div>
                    </div>
                </x-rnd.picker-modal>
            </div>
        @endif

        @if($esbMaterialModalOpen)
            <div class="fixed inset-0 z-[140] flex items-center justify-center p-3 sm:p-6">
                <button type="button" aria-label="Tutup modal" class="absolute inset-0 bg-slate-950/55" wire:click="closeModal('esbMaterial')"></button>
                <div class="relative max-h-[92vh] w-full max-w-4xl overflow-y-auto rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                    <div class="sticky top-0 z-10 flex items-center justify-between border-b border-gray-200 bg-white px-5 py-4 dark:border-gray-700 dark:bg-gray-900">
                        <div><h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ $materialDraftId ? 'Edit Draft Bahan' : 'Tambah Bahan Baru' }}</h3><p class="text-sm text-gray-500">Simpan sebagai draft sebelum dibuat ke Master Product ESB.</p></div>
                        <button type="button" wire:click="closeModal('esbMaterial')" class="rounded-lg border border-gray-200 p-2 text-gray-500"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                    </div>
                    <form wire:submit="saveEsbMaterial" class="relative p-5">
                        <div wire:loading.flex wire:target="esbMaterialCategoryId" class="absolute inset-0 z-30 items-center justify-center rounded-b-xl bg-white/85 backdrop-blur-[2px] dark:bg-gray-900/85" role="status" aria-label="Menyiapkan data kategori">
                            <div class="text-center">
                                <div class="relative mx-auto h-14 w-14">
                                    <div class="absolute inset-0 rounded-full border-4 border-blue-100 dark:border-blue-950"></div>
                                    <div class="absolute inset-0 animate-spin rounded-full border-4 border-transparent border-r-blue-400 border-t-blue-600"></div>
                                    <div class="absolute inset-[10px] animate-[spin_1.2s_linear_infinite_reverse] rounded-full border-2 border-transparent border-b-blue-500"></div>
                                </div>
                                <p class="mt-3 text-xs font-bold text-blue-600">Menyiapkan aturan naming dan Product Code...</p>
                            </div>
                        </div>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-1.5 block text-sm font-semibold">Category *</label>
                                <select wire:model.live="esbMaterialCategoryId" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm dark:border-gray-600 dark:bg-gray-800">
                                    <option value="">Pilih Category</option>
                                    @foreach($esbCategoryOptions as $id => $name)<option value="{{ $id }}">{{ $name }} ({{ $id }})</option>@endforeach
                                </select>
                                <p class="mt-1 text-xs text-gray-500">Pilih kategori terlebih dahulu agar kode dan aturan naming dapat disiapkan.</p>
                                @error('esbMaterialCategoryId')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-semibold">Sub Category *</label>
                                <select wire:model="esbMaterialSubCategoryId" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm dark:border-gray-600 dark:bg-gray-800">
                                    <option value="">Pilih Sub Category</option>
                                    @foreach($esbSubCategoryOptions as $id => $name)<option value="{{ $id }}">{{ $name }} ({{ $id }})</option>@endforeach
                                </select>
                                @error('esbMaterialSubCategoryId')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                            @if($this->isEsbMaterialWipCategory())
                                <div>
                                    <label class="mb-1.5 block text-sm font-semibold">Prefix Name *</label>
                                    <select wire:model.live="esbMaterialNamePrefix" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm dark:border-gray-600 dark:bg-gray-800">
                                        <option value="">Pilih Prefix Name</option>
                                        @foreach($this->esbMaterialNamePrefixOptions() as $prefix => $label)<option value="{{ $prefix }}">{{ $label }}</option>@endforeach
                                    </select>
                                    @error('esbMaterialNamePrefix')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-sm font-semibold">Nama Dasar Product *</label>
                                    <input wire:model.live.debounce.250ms="esbMaterialProductBaseName" maxlength="90" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm dark:border-gray-600 dark:bg-gray-800" placeholder="Contoh: Adonan Croissant">
                                    @error('esbMaterialProductBaseName')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                </div>
                                <div class="md:col-span-2">
                                    <label class="mb-1.5 block text-sm font-semibold">Product Name Final *</label>
                                    <input wire:model="esbMaterialProductName" readonly class="w-full rounded-lg border border-blue-200 bg-blue-50 px-3 py-2.5 text-sm font-semibold text-blue-800 dark:border-blue-900 dark:bg-blue-950/30 dark:text-blue-200" placeholder="Prefix dan nama product akan digabung otomatis">
                                    <p class="mt-1 text-xs text-gray-500">Nama ini yang akan dikirim ke Master Product ESB.</p>
                                    @error('esbMaterialProductName')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                </div>
                            @else
                                <div>
                                    <label class="mb-1.5 block text-sm font-semibold">Product Name *</label>
                                    <input wire:model="esbMaterialProductName" maxlength="100" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm dark:border-gray-600 dark:bg-gray-800" placeholder="Contoh: Matcha Powder Premium">
                                    @error('esbMaterialProductName')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                </div>
                            @endif
                            <div>
                                <label class="mb-1.5 block text-sm font-semibold">Product Code *</label>
                                <input wire:model="esbMaterialProductCode" readonly maxlength="50" class="w-full rounded-lg border border-blue-200 bg-blue-50 px-3 py-2.5 font-mono text-sm font-semibold uppercase text-blue-800 dark:border-blue-900 dark:bg-blue-950/30 dark:text-blue-200" placeholder="Pilih Category untuk membuat kode otomatis">
                                <p wire:loading.remove wire:target="generateEsbMaterialProductCode" class="mt-1 text-xs text-gray-500">Otomatis melanjutkan angka kode terakhir pada Category terpilih.</p>
                                <p wire:loading wire:target="generateEsbMaterialProductCode" class="mt-1 text-xs font-semibold text-blue-600">Mencari kode terakhir dan membuat Product Code...</p>
                                @error('esbMaterialProductCode')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div class="md:col-span-2">
                                <div class="mb-3 flex items-center justify-between gap-3">
                                    <div><h4 class="font-bold text-gray-900 dark:text-white">Daftar Unit</h4><p class="text-xs text-gray-500">Baris pertama adalah Base Unit. Tambahkan unit konversi bila diperlukan.</p></div>
                                    <button type="button" wire:click="addEsbMaterialUnit" class="inline-flex items-center gap-1.5 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-xs font-bold text-blue-700 hover:bg-blue-100"><x-heroicon-o-plus class="h-4 w-4" /> Tambah Unit</button>
                                </div>
                                <div class="space-y-3">
                                    @foreach($esbMaterialUnits as $unitIndex => $unit)
                                        @php
                                            $isBaseUnit = $unitIndex === 0;
                                            $baseUnitName = $esbMaterialUnits[0]['uom_name'] ?? 'Base Unit';
                                        @endphp
                                        <div wire:key="esb-material-unit-{{ $unitIndex }}" class="rounded-xl border {{ $isBaseUnit ? 'border-blue-200 bg-blue-50/40' : 'border-gray-200 bg-gray-50/50' }} p-4 dark:border-gray-700 dark:bg-gray-800/40">
                                            <div class="mb-3 flex items-center justify-between">
                                                <div><span class="rounded-full {{ $isBaseUnit ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700' }} px-2 py-1 text-[10px] font-bold">{{ $isBaseUnit ? 'BASE UNIT' : 'CONVERSION UNIT' }}</span>@if(!$isBaseUnit && filled($unit['uom_name'] ?? null) && filled($baseUnitName))<span class="ml-2 text-xs font-semibold text-gray-500">1 {{ $unit['uom_name'] }} = {{ $unit['conversion_factor'] ?: '…' }} {{ $baseUnitName }}</span>@endif</div>
                                                @if(!$isBaseUnit)<button type="button" wire:click="removeEsbMaterialUnit({{ $unitIndex }})" class="rounded-lg border border-red-200 p-1.5 text-red-600 hover:bg-red-50"><x-heroicon-o-trash class="h-4 w-4" /></button>@endif
                                            </div>
                                            <div class="grid gap-3 md:grid-cols-4">
                                                <div>
                                                    <label class="mb-1 block text-xs font-semibold">Nama Unit *</label>
                                                    <select wire:model.live="esbMaterialUnits.{{ $unitIndex }}.uom_id" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                                                        <option value="">Pilih Unit</option>
                                                        @foreach($this->esbUomOptions() as $uomId => $uomName)<option value="{{ $uomId }}">{{ $uomName }}</option>@endforeach
                                                    </select>
                                                    @error("esbMaterialUnits.$unitIndex.uom_id")<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                                </div>
                                                <div>
                                                    <label class="mb-1 block text-xs font-semibold">Conversion Factor *</label>
                                                    <input wire:model.live.debounce.250ms="esbMaterialUnits.{{ $unitIndex }}.conversion_factor" type="number" min="0.0001" step="0.0001" @readonly($isBaseUnit) class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm disabled:bg-gray-100 dark:border-gray-600 dark:bg-gray-800">
                                                    @error("esbMaterialUnits.$unitIndex.conversion_factor")<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                                </div>
                                                <div>
                                                    <label class="mb-1 block text-xs font-semibold">Base Price *</label>
                                                    <input wire:model="esbMaterialUnits.{{ $unitIndex }}.base_price" type="number" min="0" step="0.0001" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                                                    @error("esbMaterialUnits.$unitIndex.base_price")<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                                </div>
                                                <div>
                                                    <label class="mb-1 block text-xs font-semibold">SKU / Barcode</label>
                                                    <input wire:model="esbMaterialUnits.{{ $unitIndex }}.sku" readonly class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 font-mono text-xs text-gray-700 dark:border-gray-600 dark:bg-gray-800" placeholder="Otomatis">
                                                    @error("esbMaterialUnits.$unitIndex.sku")<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                    @error('esbMaterialUnits')<p class="text-xs font-semibold text-red-600">{{ $message }}</p>@enderror
                                </div>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-semibold">Notes</label>
                                <input wire:model="esbMaterialNotes" maxlength="100" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm dark:border-gray-600 dark:bg-gray-800" placeholder="Catatan singkat bahan">
                                @error('esbMaterialNotes')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                        </div>
                        <div class="mt-5 flex justify-end gap-2 border-t border-gray-200 pt-4 dark:border-gray-700">
                            <button type="button" wire:click="closeModal('esbMaterial')" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-bold">Batal</button>
                            <button type="submit" wire:loading.attr="disabled" wire:target="saveEsbMaterial" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-bold text-white disabled:opacity-50"><span wire:loading.remove wire:target="saveEsbMaterial">Simpan Draft</span><span wire:loading wire:target="saveEsbMaterial">Menyimpan...</span></button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        @if($exportPinModalOpen)
            <div class="fixed inset-0 z-[130] flex items-center justify-center p-4">
                <button type="button" aria-label="Tutup modal" class="absolute inset-0 bg-slate-950/55" wire:click="closeModal('exportPin')"></button>
                <div class="relative w-full max-w-md rounded-2xl border border-gray-200 bg-white p-6 text-center dark:border-gray-700 dark:bg-gray-900">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-300"><x-heroicon-o-lock-closed class="h-7 w-7" /></div>
                    <h3 class="mt-4 text-xl font-bold text-gray-900 dark:text-white">Export Dokumen Resep</h3>
                    <p class="mt-2 text-sm leading-6 text-gray-500">Masukkan PIN keamanan untuk mengunduh seluruh Bill of Material dalam format PDF.</p>
                    <form wire:submit="exportBomPdf" class="mt-5">
                        <input wire:model="exportPin" type="password" inputmode="numeric" autocomplete="one-time-code" placeholder="Masukkan PIN" class="w-full rounded-xl border border-gray-300 px-4 py-3 text-center text-lg font-bold tracking-[0.3em] dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                        @error('exportPin')<p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>@enderror
                        <div class="mt-4 grid grid-cols-2 gap-2">
                            <button type="button" wire:click="closeModal('exportPin')" class="rounded-xl border border-gray-300 px-4 py-3 text-sm font-bold text-gray-700 dark:border-gray-600 dark:text-gray-200">Batal</button>
                            <button type="submit" wire:loading.attr="disabled" wire:target="exportBomPdf" class="rounded-xl bg-emerald-600 px-4 py-3 text-sm font-bold text-white hover:bg-emerald-700 disabled:opacity-50">
                                <span wire:loading.remove wire:target="exportBomPdf">Download PDF</span><span wire:loading wire:target="exportBomPdf">Menyiapkan...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        @if($materialModalOpen)
            <div class="fixed inset-0 z-[120] flex items-center justify-center p-3 sm:p-6">
                <button type="button" aria-label="Tutup modal" class="absolute inset-0 bg-slate-950/50" wire:click="closeModal('material')"></button>
                <div class="relative w-full max-w-xl rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                    <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                        <div><h3 class="text-lg font-bold text-gray-900 dark:text-white">Upload Marketing Material</h3><p class="text-sm text-gray-500">File disimpan langsung ke Cloudflare R2.</p></div>
                        <button type="button" wire:click="closeModal('material')" class="rounded-lg border border-gray-200 p-2 text-gray-500 dark:border-gray-700"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                    </div>
                    <form wire:submit="saveMaterial" class="space-y-4 p-5">
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Jenis Material *</label>
                            <select wire:model="materialType" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                                @foreach(\App\Models\RndProjectMarketingMaterial::TYPES as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                            </select>
                            @error('materialType')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Judul Material *</label>
                            <input wire:model="materialTitle" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white" placeholder="Contoh: Final Design Packaging 250gr">
                            @error('materialTitle')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">File *</label>
                            <input wire:model="materialFile" type="file" accept=".jpg,.jpeg,.png,.webp,.svg,.pdf,.zip" class="block w-full rounded-lg border border-gray-300 bg-white text-sm file:mr-3 file:border-0 file:bg-blue-50 file:px-3 file:py-2 file:font-bold file:text-blue-700 dark:border-gray-600 dark:bg-gray-800">
                            <p class="mt-1.5 text-xs text-gray-500">JPG, PNG, WebP, SVG, PDF, atau ZIP. Maksimal 15 MB.</p>
                            <div wire:loading wire:target="materialFile" class="mt-1 text-xs font-semibold text-blue-600">Menyiapkan file...</div>
                            @error('materialFile')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Catatan</label>
                            <textarea wire:model="materialNotes" rows="3" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white" placeholder="Versi, ukuran, atau catatan penggunaan..."></textarea>
                        </div>
                        <div class="flex justify-end gap-2 border-t border-gray-200 pt-4 dark:border-gray-700">
                            <button type="button" wire:click="closeModal('material')" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-bold text-gray-700 dark:border-gray-600 dark:text-gray-200">Batal</button>
                            <button type="submit" wire:loading.attr="disabled" wire:target="saveMaterial" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-bold text-white disabled:opacity-50">
                                <span wire:loading.remove wire:target="saveMaterial">Upload Material</span><span wire:loading wire:target="saveMaterial">Mengunggah...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        @if($importModalOpen)
            <div wire:init="loadImportBoms" class="fixed inset-0 z-[120] flex items-center justify-center p-3 sm:p-6">
                <button type="button" aria-label="Tutup modal" class="absolute inset-0 bg-slate-950/50" wire:click="closeModal('import')" wire:loading.attr="disabled" wire:target="attachBom"></button>
                <div class="relative flex max-h-[88vh] w-full max-w-5xl flex-col overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                    <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                        <div><h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ $bomGroups[$importUsageType]['button'] ?? 'Add BOM' }}</h3><p class="text-sm text-gray-500">Pilih BOM untuk kelompok {{ $bomGroups[$importUsageType]['title'] ?? 'BOM' }}.</p></div>
                        <button type="button" wire:click="closeModal('import')" wire:loading.attr="disabled" wire:target="attachBom" class="rounded-lg border border-gray-200 p-2 text-gray-500 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-gray-700"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                    </div>
                    <div class="relative min-h-0 flex-1 overflow-auto">
                        <div wire:loading.flex wire:target="loadImportBoms" class="absolute inset-0 z-40 items-center justify-center bg-white/80 backdrop-blur-[2px] dark:bg-gray-900/80" role="status">
                            <div class="relative h-14 w-14">
                                <div class="absolute inset-0 rounded-full border-4 border-blue-100 dark:border-blue-950"></div>
                                <div class="absolute inset-0 animate-spin rounded-full border-4 border-transparent border-r-blue-400 border-t-blue-600"></div>
                                <div class="absolute inset-[10px] animate-[spin_1.2s_linear_infinite_reverse] rounded-full border-2 border-transparent border-b-blue-500"></div>
                            </div>
                            <span class="sr-only">Memuat daftar BOM...</span>
                        </div>
                        <div wire:loading.flex wire:target="attachBom" class="absolute inset-0 z-50 flex-col items-center justify-center bg-white/90 px-6 text-center backdrop-blur-[2px] dark:bg-gray-900/90" role="status" aria-live="polite">
                            <div class="relative h-16 w-16">
                                <div class="absolute inset-0 rounded-full border-4 border-blue-100 dark:border-blue-950"></div>
                                <div class="absolute inset-0 animate-spin rounded-full border-4 border-transparent border-r-blue-400 border-t-blue-600"></div>
                                <div class="absolute inset-[11px] animate-[spin_1.1s_linear_infinite_reverse] rounded-full border-2 border-transparent border-b-violet-500 border-l-violet-300"></div>
                            </div>
                            <p class="mt-4 text-sm font-bold text-gray-900 dark:text-white">Menambahkan Bill of Material...</p>
                            <p class="mt-1 max-w-md text-xs leading-5 text-gray-500 dark:text-gray-400">Mengambil detail BOM dari ESB dan memetakan komponen WIP serta packaging secara otomatis.</p>
                        </div>
                        @php
                            $importUnitOptions = collect($importBomOptions)->pluck('uomName')->filter()->unique()->sort()->values();
                            $importTypeOptions = collect($importBomOptions)->map(fn ($item) => $item['bomTypeName'] ?? 'Assembly')->filter()->unique()->sort()->values();
                        @endphp
                        <table class="w-full min-w-[980px] table-fixed text-sm">
                            <thead class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800">
                                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    <th class="w-[16%] px-4 pt-3">Kode BOM</th>
                                    <th class="w-[25%] px-4 pt-3">Nama BOM</th>
                                    <th class="w-[25%] px-4 pt-3">Produk Hasil</th>
                                    <th class="w-[12%] px-4 pt-3">Unit</th>
                                    <th class="w-[12%] px-4 pt-3">Tipe</th>
                                    <th class="w-[10%] px-4 pt-3 text-right">Aksi</th>
                                </tr>
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <th class="px-4 pb-3 pt-2"><input wire:model.live.debounce.250ms="importBomCodeSearch" type="search" placeholder="Cari kode..." class="w-full rounded-md border border-gray-300 px-2.5 py-2 text-xs font-normal normal-case tracking-normal dark:border-gray-600 dark:bg-gray-900"></th>
                                    <th class="px-4 pb-3 pt-2"><input wire:model.live.debounce.250ms="importBomNameSearch" type="search" placeholder="Cari nama..." class="w-full rounded-md border border-gray-300 px-2.5 py-2 text-xs font-normal normal-case tracking-normal dark:border-gray-600 dark:bg-gray-900"></th>
                                    <th class="px-4 pb-3 pt-2"><input wire:model.live.debounce.250ms="importBomProductSearch" type="search" placeholder="Cari produk..." class="w-full rounded-md border border-gray-300 px-2.5 py-2 text-xs font-normal normal-case tracking-normal dark:border-gray-600 dark:bg-gray-900"></th>
                                    <th class="px-4 pb-3 pt-2">
                                        <select wire:model.live="importBomUnitSearch" class="w-full rounded-md border border-gray-300 bg-white px-2.5 py-2 text-xs font-normal normal-case dark:border-gray-600 dark:bg-gray-900">
                                            <option value="">- Semua -</option>
                                            @foreach($importUnitOptions as $unit)<option value="{{ $unit }}">{{ $unit }}</option>@endforeach
                                        </select>
                                    </th>
                                    <th class="px-4 pb-3 pt-2">
                                        <select wire:model.live="importBomTypeSearch" class="w-full rounded-md border border-gray-300 bg-white px-2.5 py-2 text-xs font-normal normal-case dark:border-gray-600 dark:bg-gray-900">
                                            <option value="">- Semua -</option>
                                            @foreach($importTypeOptions as $type)<option value="{{ $type }}">{{ $type }}</option>@endforeach
                                        </select>
                                    </th>
                                    <th class="px-4 pb-3 pt-2"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @forelse($this->importRows() as $bom)
                                    <tr class="hover:bg-blue-50 dark:hover:bg-blue-950/20">
                                        <td class="px-4 py-3"><p class="truncate font-mono font-semibold text-blue-700 dark:text-blue-300">{{ $bom['bomCode'] ?: '-' }}</p></td>
                                        <td class="px-4 py-3"><p class="truncate font-semibold text-gray-900 dark:text-white">{{ $bom['bomName'] }}</p></td>
                                        <td class="px-4 py-3"><p class="truncate">{{ $bom['productName'] ?: '-' }}</p></td>
                                        <td class="px-4 py-3"><span class="rounded-md bg-blue-50 px-2 py-1 text-xs font-semibold text-blue-700 dark:bg-blue-950/50 dark:text-blue-300">{{ $bom['uomName'] ?: '-' }}</span></td>
                                        <td class="px-4 py-3">{{ $bom['bomTypeName'] ?? 'Assembly' }}</td>
                                        <td class="px-4 py-3 text-right"><button type="button" wire:click="attachBom({{ $bom['bomID'] }})" wire:loading.attr="disabled" wire:target="attachBom" class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-blue-700 disabled:cursor-wait disabled:opacity-60">Tambahkan</button></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="px-4 py-12 text-center text-gray-500">Tidak ada BOM yang dapat diimport.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @php
                        $importTotal = count($this->filteredImportBoms());
                        $importLastPage = max(1, (int) ceil($importTotal / $importPerPage));
                        $importPageStart = max(1, $importPage - 4);
                        $importPageEnd = min($importLastPage, $importPageStart + 8);
                        $importPageStart = max(1, $importPageEnd - 8);
                    @endphp
                    <div class="flex flex-col gap-3 border-t border-gray-200 px-4 py-3 dark:border-gray-700 lg:flex-row lg:items-center lg:justify-between">
                        <p class="text-xs font-medium text-gray-600 dark:text-gray-300">Halaman {{ $importPage }} dari {{ $importLastPage }}</p>
                        <div class="max-w-full overflow-x-auto">
                            <div class="inline-flex min-w-max overflow-hidden rounded-lg border border-gray-300 dark:border-gray-600">
                                <button type="button" wire:click="goToImportPage(1)" @disabled($importPage <= 1) class="border-r border-gray-300 px-3 py-2 text-xs font-semibold disabled:opacity-40 dark:border-gray-600">First</button>
                                <button type="button" wire:click="goToImportPage({{ max(1, $importPage - 1) }})" @disabled($importPage <= 1) class="border-r border-gray-300 px-3 py-2 text-sm font-bold disabled:opacity-40 dark:border-gray-600">&laquo;</button>
                                @foreach(range($importPageStart, $importPageEnd) as $pageNumber)
                                    <button type="button" wire:click="goToImportPage({{ $pageNumber }})" @disabled($pageNumber === $importPage) class="border-r border-gray-300 px-3.5 py-2 text-xs font-semibold dark:border-gray-600 {{ $pageNumber === $importPage ? 'bg-blue-600 text-white disabled:opacity-100' : 'hover:bg-gray-50 dark:hover:bg-gray-800' }}">{{ $pageNumber }}</button>
                                @endforeach
                                <button type="button" wire:click="goToImportPage({{ min($importLastPage, $importPage + 1) }})" @disabled($importPage >= $importLastPage) class="border-r border-gray-300 px-3 py-2 text-sm font-bold disabled:opacity-40 dark:border-gray-600">&raquo;</button>
                                <button type="button" wire:click="goToImportPage({{ $importLastPage }})" @disabled($importPage >= $importLastPage) class="px-3 py-2 text-xs font-semibold disabled:opacity-40">Last</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
