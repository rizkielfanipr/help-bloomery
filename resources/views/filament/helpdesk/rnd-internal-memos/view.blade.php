<x-filament-panels::page>
    @php
        $memo = $this->getRecord();
        $menus = $this->menus();
        $canManage = $this->canUpdateMemo();
        $canDeleteMemo = $this->canDeleteMemo();
        $summary = $this->summary();
    @endphp
    <div class="space-y-6">
        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <div class="flex flex-col gap-5 p-5 sm:p-6 md:flex-row md:items-center md:justify-between">
                <div class="flex min-w-0 items-start gap-4">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-950/40 dark:text-blue-300">
                        <x-heroicon-o-document-text class="h-6 w-6" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-bold uppercase tracking-wider text-blue-600 dark:text-blue-400">Memo Internal · BLSS</p>
                        <h2 class="mt-1 break-words text-2xl font-bold text-gray-950 dark:text-white">{{ $memo->title }}</h2>
                        <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">{{ $memo->memo_number }} · Periode {{ $memo->period_month->translatedFormat('F Y') }}</p>
                    </div>
                </div>
                <div class="flex shrink-0 flex-col items-start gap-2 md:items-end">
                    <div class="flex flex-wrap items-center justify-end gap-2">
                        @if($canManage)
                            <button type="button" wire:click="openEditMemoModal" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-2 text-xs font-bold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200">
                                <x-heroicon-o-pencil-square class="h-4 w-4" /> Edit Info
                            </button>
                        @endif
                        @if($canDeleteMemo)
                            <button type="button" x-on:click="window.BloomeryConfirm.show({ title: 'Hapus Memo?', text: 'Memo ini akan dihapus dari daftar, tetapi data tetap disimpan.', confirmText: 'Hapus Memo' }).then((confirmed) => { if (confirmed) $wire.deleteMemo() })" wire:loading.attr="disabled" wire:target="deleteMemo" class="inline-flex items-center gap-1.5 rounded-lg border border-red-200 px-3 py-2 text-xs font-bold text-red-700 hover:bg-red-50 disabled:opacity-50 dark:border-red-900 dark:text-red-300 dark:hover:bg-red-950/30">
                                <x-heroicon-o-trash class="h-4 w-4" /> Hapus Memo
                            </button>
                        @endif
                    </div>
                    <a href="{{ \App\Filament\Helpdesk\Resources\RndInternalMemos\RndInternalMemoResource::getUrl('index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-blue-700 hover:underline dark:text-blue-300">
                        <x-heroicon-o-arrow-left class="h-4 w-4" /> Daftar Memo
                    </a>
                </div>
            </div>
            @if($memo->notes)
                <div class="border-t border-gray-200 p-5 dark:border-gray-700">
                    <p class="text-xs font-semibold text-gray-500 dark:text-gray-400">Catatan</p>
                    <p class="mt-1 text-sm text-gray-700 dark:text-gray-200">{{ $memo->notes }}</p>
                </div>
            @endif
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <div class="flex flex-col gap-3 border-b border-gray-200 p-5 dark:border-gray-700 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Menu Terpilih</h3>
                    <p class="mt-1 text-sm text-gray-500">{{ $menus->count() }} Menu dari Master Menu BLSS.</p>
                </div>
                @if($canManage)
                    <button type="button" wire:click="openMenuPicker" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-bold text-white hover:bg-blue-700">
                        <x-heroicon-o-plus class="h-4 w-4" /> Tambah Menu
                    </button>
                @endif
            </div>
            <div class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse($menus as $menuRow)
                    <div wire:key="memo-menu-{{ $menuRow->id }}" class="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-bold text-gray-900 dark:text-white">{{ $menuRow->menu_name }}</p>
                                @if($menuRow->sync_status->value === 'failed')
                                    <span class="rounded-full bg-red-50 px-2 py-0.5 text-[11px] font-bold text-red-700 dark:bg-red-950/40 dark:text-red-300">Gagal Sinkronisasi</span>
                                @elseif($menuRow->sync_status->value === 'syncing')
                                    <span class="rounded-full bg-blue-50 px-2 py-0.5 text-[11px] font-bold text-blue-700 dark:bg-blue-950/40 dark:text-blue-300">Memproses...</span>
                                @endif
                            </div>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ $menuRow->menu_code ?: 'ID '.$menuRow->esb_menu_id }} ·
                                BOM {{ $menuRow->bom_name ?: $menuRow->esb_bom_id }} ·
                                {{ $menuRow->synced_at ? 'Diperbarui '.$menuRow->synced_at->diffForHumans() : 'Belum pernah disinkronkan' }}
                            </p>
                            @if($menuRow->sync_error)
                                <p class="mt-1 max-w-xl text-xs leading-5 text-amber-600 dark:text-amber-400">{{ $menuRow->sync_error }}</p>
                            @endif
                        </div>
                        @if($canManage)
                            <div class="flex shrink-0 items-center gap-1.5">
                                <button type="button" wire:click="refreshMenu({{ $menuRow->id }})" wire:loading.attr="disabled" wire:target="refreshMenu({{ $menuRow->id }})" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-2 text-xs font-bold text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-700 dark:text-gray-200">
                                    <x-heroicon-o-arrow-path class="h-3.5 w-3.5" />
                                    <span wire:loading.remove wire:target="refreshMenu({{ $menuRow->id }})">{{ $menuRow->sync_status->value === 'failed' ? 'Coba Ambil Ulang' : 'Refresh' }}</span>
                                    <span wire:loading wire:target="refreshMenu({{ $menuRow->id }})">Memproses...</span>
                                </button>
                                <button type="button" x-on:click="window.BloomeryConfirm.show({ title: 'Hapus Menu?', text: 'Menu ini akan dihapus dari Memo.', confirmText: 'Hapus Menu' }).then((confirmed) => { if (confirmed) $wire.removeMenu({{ $menuRow->id }}) })" class="rounded-lg border border-red-200 p-2 text-red-600 hover:bg-red-50 dark:border-red-900 dark:text-red-300" title="Hapus Menu" aria-label="Hapus Menu {{ $menuRow->menu_name }}">
                                    <x-heroicon-o-trash class="h-4 w-4" />
                                </button>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="p-10 text-center">
                        <x-heroicon-o-rectangle-stack class="mx-auto h-10 w-10 text-gray-300" />
                        <h4 class="mt-3 font-bold text-gray-700 dark:text-gray-200">Belum ada Menu pada Memo ini</h4>
                        <p class="mt-1 text-sm text-gray-500">Tambahkan Menu dari Master Menu BLSS untuk mulai menyusun kebutuhan Bahan dan WIP.</p>
                    </div>
                @endforelse
            </div>
        </section>

        @foreach($menus as $menuRow)
            @php
                $materials = $menuRow->materials;
            @endphp
            @if($materials->isNotEmpty())
                <section class="rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                    <div class="border-b border-gray-200 p-5 dark:border-gray-700">
                        <h3 class="text-base font-bold text-gray-900 dark:text-white">Struktur BOM · {{ $menuRow->menu_name }}</h3>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Komponen langsung BOM Menu dan jalur Assembly/WIP yang ditelusuri.</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[640px] text-sm">
                            <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-800">
                                <tr>
                                    <th class="px-4 py-3 text-left">Komponen</th>
                                    <th class="px-4 py-3 text-left">Kategori</th>
                                    <th class="px-4 py-3 text-right">Qty BOM</th>
                                    <th class="px-4 py-3 text-left">UOM BOM</th>
                                    <th class="px-4 py-3 text-left">Jenis</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach($materials as $material)
                                    <tr wire:key="memo-material-{{ $material->id }}">
                                        <td class="px-4 py-3" style="padding-left: {{ 16 + $material->depth * 20 }}px">
                                            <p class="font-semibold text-gray-900 dark:text-white">{{ $material->product_name }}</p>
                                            <p class="text-xs text-gray-400">{{ $material->product_code ?: '—' }}</p>
                                        </td>
                                        <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-300">{{ $material->category_name ?: '—' }}</td>
                                        <td class="px-4 py-3 text-right">{{ rtrim(rtrim(number_format((float) $material->quantity_per_menu, 4, '.', ''), '0'), '.') }}</td>
                                        <td class="px-4 py-3">{{ $material->uom_name }}</td>
                                        <td class="px-4 py-3">
                                            @if($material->is_wip)
                                                <span class="rounded-full bg-violet-50 px-2 py-1 text-[11px] font-bold text-violet-700 dark:bg-violet-950/40 dark:text-violet-300">WIP/Assembly</span>
                                            @elseif($material->is_packaging)
                                                <span class="rounded-full bg-sky-50 px-2 py-1 text-[11px] font-bold text-sky-700 dark:bg-sky-950/40 dark:text-sky-300">Packaging</span>
                                            @else
                                                <span class="rounded-full bg-emerald-50 px-2 py-1 text-[11px] font-bold text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">Bahan Baku</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif
        @endforeach

        @if(! empty($summary['warnings']))
            <section class="space-y-1 rounded-2xl border border-amber-200 bg-amber-50/40 p-5 dark:border-amber-900 dark:bg-amber-950/10">
                @foreach($summary['warnings'] as $warning)
                    <p class="flex items-start gap-1.5 text-xs text-amber-700 dark:text-amber-300"><x-heroicon-o-exclamation-triangle class="mt-0.5 h-3.5 w-3.5 shrink-0" /> {{ $warning }}</p>
                @endforeach
            </section>
        @endif

        @foreach(['bahan' => 'Bahan', 'wip' => 'WIP'] as $groupKey => $groupLabel)
            @if(! empty($summary[$groupKey]))
                <section class="rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                    <div class="border-b border-gray-200 p-5 dark:border-gray-700">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">Ringkasan Item Akhir · {{ $groupLabel }}</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Item yang sama dari lebih dari satu Menu/jalur BOM digabung dalam satu baris.</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[800px] text-sm">
                            <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-800">
                                <tr>
                                    <th class="px-4 py-3 text-left">Produk</th>
                                    <th class="px-4 py-3 text-left">Sumber</th>
                                    <th class="px-4 py-3 text-left">UOM BOM</th>
                                    <th class="px-4 py-3 text-left">Purchase UOM</th>
                                    <th class="px-4 py-3 text-left">Minimum Order</th>
                                    <th class="px-4 py-3 text-left">Diperbarui</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach($summary[$groupKey] as $row)
                                    <tr wire:key="summary-{{ $groupKey }}-{{ $row['key'] }}">
                                        <td class="px-4 py-3">
                                            <p class="font-semibold text-gray-900 dark:text-white">{{ $row['product_name'] }}</p>
                                            <p class="text-xs text-gray-400">{{ $row['product_code'] ?: '—' }}</p>
                                        </td>
                                        <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-300">
                                            @foreach($row['sources'] as $source)
                                                <p>{{ $source['menu_name'] }}@if($source['path']) · {{ $source['path'] }} @endif</p>
                                            @endforeach
                                        </td>
                                        <td class="px-4 py-3">{{ $row['uom_name'] }}</td>
                                        <td class="px-4 py-3">
                                            @if($row['has_purchase_uom'])
                                                {{ $row['purchase_uom_name'] }}
                                            @else
                                                <span class="text-xs italic text-gray-400">Purchase UOM belum tersedia</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">
                                            @if($minimumOrderKey === $row['key'])
                                                <form wire:submit="saveMinimumOrder" class="flex items-center gap-1.5">
                                                    <input type="number" step="any" min="0" wire:model="minimumOrderValue" autofocus class="w-24 rounded-lg border border-gray-300 bg-white px-2 py-1.5 text-xs dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                                                    <button type="submit" class="rounded-lg bg-blue-600 px-2 py-1.5 text-xs font-bold text-white hover:bg-blue-700" aria-label="Simpan Minimum Order">
                                                        <x-heroicon-o-check class="h-3.5 w-3.5" />
                                                    </button>
                                                    <button type="button" wire:click="cancelMinimumOrder" class="rounded-lg border border-gray-300 px-2 py-1.5 text-xs text-gray-600 dark:border-gray-600 dark:text-gray-300" aria-label="Batal">
                                                        <x-heroicon-o-x-mark class="h-3.5 w-3.5" />
                                                    </button>
                                                </form>
                                                @error('minimumOrderValue')<p class="mt-1 text-[11px] text-red-600">{{ $message }}</p>@enderror
                                            @else
                                                <button type="button" @if($canManage) wire:click="editMinimumOrder('{{ $row['key'] }}', '{{ $row['minimum_order'] }}')" @else disabled @endif class="text-sm font-semibold {{ $canManage ? 'text-blue-700 hover:underline dark:text-blue-300' : 'text-gray-400' }}">
                                                    {{ $row['minimum_order'] !== null ? rtrim(rtrim(number_format($row['minimum_order'], 4, '.', ''), '0'), '.').' '.$row['uom_name'] : 'Belum ditentukan' }}
                                                </button>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">{{ $row['product_synced_at'] ? \Illuminate\Support\Carbon::parse($row['product_synced_at'])->diffForHumans() : '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif
        @endforeach
    </div>

    @if($editMemoModalOpen)
        <div class="fixed inset-0 z-[130] flex items-center justify-center p-4" x-data x-trap.inert.noscroll="true" x-on:keydown.escape.window="$wire.closeEditMemoModal()">
            <button type="button" wire:click="closeEditMemoModal" class="absolute inset-0 bg-gray-950/60" aria-label="Tutup"></button>
            <form wire:submit="saveMemoInfo" class="relative flex max-h-[90vh] w-full max-w-lg flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900" role="dialog" aria-modal="true" aria-label="Edit Informasi Memo">
                <div class="flex items-start justify-between gap-4 border-b border-gray-200 p-5 dark:border-gray-700">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Edit Informasi Memo</h3>
                    <button type="button" wire:click="closeEditMemoModal" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800" aria-label="Tutup"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                </div>
                <div class="space-y-4 overflow-y-auto p-5">
                    <div>
                        <label class="text-xs font-semibold text-gray-500 dark:text-gray-400">Nama Memo *</label>
                        <input wire:model="memoTitle" class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                        @error('memoTitle')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-gray-500 dark:text-gray-400">Bulan Memo *</label>
                        <input type="month" wire:model="periodMonth" class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                        @error('periodMonth')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-gray-500 dark:text-gray-400">Nomor Memo</label>
                        <input wire:model="memoNumber" class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                        @error('memoNumber')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-gray-500 dark:text-gray-400">Catatan</label>
                        <textarea wire:model="notes" rows="3" class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"></textarea>
                        @error('notes')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div class="flex justify-end gap-2 border-t border-gray-200 p-5 dark:border-gray-700">
                    <button type="button" wire:click="closeEditMemoModal" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Batal</button>
                    <button type="submit" wire:loading.attr="disabled" wire:target="saveMemoInfo" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-50">Simpan</button>
                </div>
            </form>
        </div>
    @endif

    @if($menuPickerOpen)
        <div class="fixed inset-0 z-[130] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-label="Pilih Menu ESB">
            <button type="button" wire:click="closeMenuPicker" class="absolute inset-0 bg-gray-950/60" aria-label="Tutup pilih Menu"></button>
            <x-rnd.picker-modal title="Pilih Menu ESB" description="Menu diambil langsung dari Master Menu BLSS. Menu tanpa BOM tidak dapat dipilih." max-width="6xl">
                <x-slot:close>
                    <button type="button" wire:click="closeMenuPicker" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800" aria-label="Tutup"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                </x-slot:close>

                <div class="grid gap-3 border-b border-gray-200 p-5 dark:border-gray-700 sm:grid-cols-3">
                    <input wire:model="menuSearchName" wire:keydown.enter="searchMenus" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white" placeholder="Cari nama Menu...">
                    <input wire:model="menuSearchCode" wire:keydown.enter="searchMenus" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white" placeholder="Cari kode Menu...">
                    <button type="button" wire:click="searchMenus" wire:loading.attr="disabled" wire:target="searchMenus,loadMenuPage" class="inline-flex items-center justify-center gap-2 rounded-lg border border-blue-200 px-3 py-2 text-sm font-bold text-blue-700 hover:bg-blue-50 disabled:opacity-50">
                        <x-heroicon-o-magnifying-glass class="h-4 w-4" /> Cari
                    </button>
                </div>

                <div class="max-h-[55vh] overflow-y-auto p-5" wire:loading.class="opacity-50" wire:target="searchMenus,loadMenuPage">
                    @if($menuPickerError)
                        <div class="flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-300">
                            <x-heroicon-o-exclamation-triangle class="mt-0.5 h-5 w-5 shrink-0" />
                            <div>
                                <p class="font-semibold">Gagal memuat Master Menu</p>
                                <p class="mt-1">{{ $menuPickerError }}</p>
                                <button type="button" wire:click="searchMenus" class="mt-2 inline-flex items-center gap-1.5 text-xs font-bold text-red-700 hover:underline dark:text-red-300"><x-heroicon-o-arrow-path class="h-3.5 w-3.5" /> Coba Lagi</button>
                            </div>
                        </div>
                    @elseif(empty($menuPickerRows) && ! $menuPickerLoading)
                        <p class="rounded-xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">Tidak ada Menu ditemukan.</p>
                    @else
                        <div class="space-y-2">
                            @foreach($menuPickerRows as $row)
                                <div wire:key="menu-picker-{{ $row['menuID'] }}" class="flex items-center justify-between gap-3 rounded-xl border border-gray-200 p-3 dark:border-gray-700">
                                    <div class="min-w-0">
                                        <p class="truncate font-semibold text-gray-900 dark:text-white">{{ $row['menuName'] ?: 'Menu #'.$row['menuID'] }}</p>
                                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                            {{ $row['menuCode'] ?: 'ID '.$row['menuID'] }}
                                            @if($row['categoryDetail']) · {{ $row['categoryDetail'] }} @endif
                                        </p>
                                    </div>
                                    @if($row['hasBom'])
                                        <button type="button" wire:click="addMenu({{ \Illuminate\Support\Js::from($row) }})" wire:loading.attr="disabled" wire:target="addMenu" class="shrink-0 rounded-lg bg-blue-600 px-3 py-2 text-xs font-bold text-white hover:bg-blue-700 disabled:opacity-50">Pilih</button>
                                    @else
                                        <span class="shrink-0 rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-400 dark:border-gray-700" title="Menu belum memiliki BOM">Belum Memiliki BOM</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="flex items-center justify-between border-t border-gray-200 px-5 py-4 dark:border-gray-700">
                    <button type="button" wire:click="loadMenuPage({{ max(1, $menuPickerPage - 1) }})" @disabled($menuPickerPage <= 1) class="rounded-lg border border-gray-300 px-3 py-2 text-xs font-semibold text-gray-700 disabled:cursor-not-allowed disabled:opacity-40 dark:border-gray-600 dark:text-gray-200">Sebelumnya</button>
                    <span class="text-xs text-gray-500 dark:text-gray-400">Halaman {{ $menuPickerPage }}</span>
                    <button type="button" wire:click="loadMenuPage({{ $menuPickerPage + 1 }})" @disabled(! $menuPickerHasNext) class="rounded-lg border border-gray-300 px-3 py-2 text-xs font-semibold text-gray-700 disabled:cursor-not-allowed disabled:opacity-40 dark:border-gray-600 dark:text-gray-200">Berikutnya</button>
                </div>
            </x-rnd.picker-modal>
        </div>
    @endif
</x-filament-panels::page>
