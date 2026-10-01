<x-filament-panels::page>
    @php
        $memo = $this->getRecord();
        $memoBranches = $memo->branches;
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
                        <p class="text-xs font-bold uppercase tracking-wider text-blue-600 dark:text-blue-400">Memo Internal</p>
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

        {{-- docs/rnd-internal-memo-multi-branch-prd.md §7.1, §9.1: read-only summary — adding or
             removing a branch is handled by its own reconciliation-aware action, not from here. --}}
        <section class="rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <div class="border-b border-gray-200 p-5 dark:border-gray-700">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white">Branch Tujuan</h3>
                <p class="mt-1 text-sm text-gray-500">{{ $memoBranches->count() }} branch dipilih untuk Memo ini.</p>
            </div>
            <div class="flex flex-wrap gap-2 p-5">
                @forelse($memoBranches as $memoBranch)
                    @php
                        $syncColor = match ($memoBranch->catalog_sync_status) {
                            'synced' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-300',
                            'failed' => 'bg-red-50 text-red-700 dark:bg-red-950/30 dark:text-red-300',
                            default => 'bg-amber-50 text-amber-700 dark:bg-amber-950/30 dark:text-amber-300',
                        };
                    @endphp
                    <div class="inline-flex items-center gap-2 rounded-xl border border-gray-200 px-3 py-2 dark:border-gray-700">
                        <div>
                            <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ $memoBranch->branch_name_snapshot }}</p>
                            <p class="text-xs text-gray-400">{{ $memoBranch->company_code_snapshot }} · {{ $memoBranch->branch_code_snapshot }}</p>
                        </div>
                        <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $syncColor }}">{{ ucfirst($memoBranch->catalog_sync_status) }}</span>
                    </div>
                @empty
                    <p class="text-sm text-amber-600 dark:text-amber-400">Perlu Menentukan Branch — Memo ini dibuat sebelum fitur multi-branch dan belum mempunyai Branch Tujuan.</p>
                @endforelse
            </div>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <div class="flex flex-col gap-3 border-b border-gray-200 p-5 dark:border-gray-700 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Menu Terpilih</h3>
                    <p class="mt-1 text-sm text-gray-500">{{ $menus->count() }} Menu dari Master Menu Branch Tujuan.</p>
                </div>
                @if($canManage)
                    <button type="button" wire:click="openMenuPicker" wire:loading.attr="disabled" wire:target="openMenuPicker" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-50">
                        <span wire:loading.remove wire:target="openMenuPicker" class="inline-flex items-center gap-2"><x-heroicon-o-plus class="h-4 w-4" /> Tambah Menu</span>
                        <span wire:loading wire:target="openMenuPicker" class="inline-flex items-center gap-2">
                            <svg class="h-4 w-4 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            Memuat...
                        </span>
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
                        <p class="mt-1 text-sm text-gray-500">Tambahkan Menu dari katalog Branch Tujuan untuk mulai menyusun kebutuhan Bahan dan WIP.</p>
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
                        <label class="text-xs font-semibold text-gray-500 dark:text-gray-400">Branch Tujuan *</label>
                        <div class="mt-1">
                            <x-rnd.branch-multi-select :options="$this->branchOptions()" />
                        </div>
                        @error('branchIds')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        @error('branchIds.*')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        <p class="mt-1 text-[11px] text-gray-400">Lepaskan Menu yang hanya tersedia pada branch tersebut sebelum menghapus Branch Tujuan.</p>
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
        <div wire:key="menu-picker-modal" wire:init="initializeMenuPicker" class="fixed inset-0 z-[160] flex items-center justify-center p-3 sm:p-6" role="dialog" aria-modal="true" aria-label="Pilih Menu ESB">
            <button type="button" wire:click="closeMenuPicker" class="absolute inset-0 bg-gray-950/60" aria-label="Tutup pilih Menu"></button>
            <x-rnd.picker-modal title="Pilih Menu ESB" description="Menu berasal dari katalog seluruh Branch Tujuan. Menu tanpa BOM tidak dapat dipilih." max-width="6xl">
                <x-slot:close>
                    <button type="button" wire:click="closeMenuPicker" class="rounded-lg border border-gray-200 p-2 text-gray-500 hover:bg-gray-50 dark:border-gray-700" aria-label="Tutup"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                </x-slot:close>

                <div class="relative flex min-h-0 flex-1 flex-col overflow-hidden">
                    <div class="grid shrink-0 gap-3 border-b border-gray-200 p-4 dark:border-gray-700 sm:grid-cols-2">
                        <select wire:model.live="menuBranchFilter" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                            <option value="">Semua Branch</option>
                            @foreach($this->getRecord()->branches as $memoBranch)
                                <option value="{{ $memoBranch->id }}">{{ $memoBranch->branch_name_snapshot }}</option>
                            @endforeach
                        </select>
                        <select wire:model.live="menuCompanyFilter" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                            <option value="">Semua Company Code</option>
                            @foreach($this->getRecord()->branches->pluck('company_code_snapshot')->unique() as $companyCode)
                                <option value="{{ $companyCode }}">{{ $companyCode }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="min-h-0 flex-1 overflow-auto overscroll-contain">
                        <table class="w-full min-w-[900px] text-left text-sm">
                            <thead class="sticky top-0 z-10 bg-white dark:bg-gray-900">
                                <tr class="border-b border-gray-200 text-xs font-bold uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                    <th class="px-4 pb-3 pt-4">Menu Code</th>
                                    <th class="px-4 pb-3 pt-4">Menu Name</th>
                                    <th class="px-4 pb-3 pt-4">Company</th>
                                    <th class="px-4 pb-3 pt-4">Tersedia Di Branch</th>
                                    <th class="px-4 pb-3 pt-4">Category</th>
                                    <th class="px-4 pb-3 pt-4 text-right">Aksi</th>
                                </tr>
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <th class="px-4 pb-3 pt-2"><input wire:model.live.debounce.700ms="menuSearchCode" type="search" placeholder="Cari kode..." class="w-full rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-xs font-normal normal-case text-gray-900 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"></th>
                                    <th class="px-4 pb-3 pt-2"><input wire:model.live.debounce.700ms="menuSearchName" type="search" placeholder="Cari nama..." class="w-full rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-xs font-normal normal-case text-gray-900 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"></th>
                                    <th class="px-4 pb-3 pt-2"></th>
                                    <th class="px-4 pb-3 pt-2"></th>
                                    <th class="px-4 pb-3 pt-2"></th>
                                    <th class="px-4 pb-3 pt-2"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @if($menuPickerError)
                                    <tr>
                                        <td colspan="6" class="px-5 py-10">
                                            <div class="flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-300">
                                                <x-heroicon-o-exclamation-triangle class="mt-0.5 h-5 w-5 shrink-0" />
                                                <div>
                                                    <p class="font-semibold">Gagal memuat Master Menu</p>
                                                    <p class="mt-1">{{ $menuPickerError }}</p>
                                                    <button type="button" wire:click="loadMenuPage({{ $menuPickerPage }})" class="mt-2 inline-flex items-center gap-1.5 text-xs font-bold text-red-700 hover:underline dark:text-red-300"><x-heroicon-o-arrow-path class="h-3.5 w-3.5" /> Coba Lagi</button>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @elseif(empty($menuPickerRows) && ! $menuPickerLoading)
                                    <tr>
                                        <td colspan="6" class="px-5 py-14 text-center text-gray-500 dark:text-gray-400">Menu tidak ditemukan.</td>
                                    </tr>
                                @else
                                    @foreach($menuPickerRows as $row)
                                        @php
                                            $splitCategory = $this->splitMenuCategory($row['categoryDetail']);
                                        @endphp
                                        <tr wire:key="menu-picker-{{ $row['menuID'] }}" class="{{ $row['hasBom'] ? 'cursor-pointer hover:bg-blue-50 dark:hover:bg-blue-950/30' : 'opacity-60' }} text-gray-700 transition dark:text-gray-200" @if($row['hasBom']) wire:click="addMenu({{ \Illuminate\Support\Js::from($row) }})" @endif>
                                            <td class="px-4 py-3"><p class="truncate font-mono font-semibold text-blue-700 dark:text-blue-300">{{ $row['menuCode'] ?: '-' }}</p></td>
                                            <td class="px-4 py-3"><p class="truncate font-semibold text-gray-900 dark:text-white">{{ $row['menuName'] ?: 'Menu #'.$row['menuID'] }}</p></td>
                                            <td class="px-4 py-3"><span class="rounded-md bg-gray-100 px-2 py-1 text-xs font-semibold dark:bg-gray-800">{{ $row['companyCode'] }}</span></td>
                                            <td class="px-4 py-3"><p class="max-w-xs text-xs leading-5">{{ implode(', ', $row['branchNames']) ?: '-' }}</p></td>
                                            <td class="px-4 py-3"><p class="truncate">{{ collect([$splitCategory['category'], $splitCategory['detail']])->filter()->implode(' · ') ?: '-' }}</p></td>
                                            <td class="px-4 py-3 text-right">
                                                @if($row['hasBom'])
                                                    <span class="inline-flex items-center gap-1 rounded-md bg-blue-50 px-2 py-1 text-xs font-semibold text-blue-700 dark:bg-blue-950/50 dark:text-blue-300">Pilih</span>
                                                @else
                                                    <span class="inline-flex items-center gap-1 rounded-md border border-gray-200 px-2 py-1 text-xs font-semibold text-gray-400 dark:border-gray-700" title="Menu belum memiliki BOM">Belum Memiliki BOM</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                @endif
                            </tbody>
                        </table>
                    </div>

                    <div wire:loading.flex wire:target="initializeMenuPicker,loadMenuPage,previousMenuPage,nextMenuPage,goToMenuPage,updatedMenuSearchName,updatedMenuSearchCode,updatedMenuBranchFilter,updatedMenuCompanyFilter" class="absolute inset-0 z-20 hidden items-center justify-center bg-white/70 dark:bg-gray-900/70">
                        <span class="h-8 w-8 animate-spin rounded-full border-4 border-blue-200 border-t-blue-600"></span>
                    </div>
                </div>

                @php
                    $menuPickerLastPage = max(1, (int) ceil($menuPickerTotal / max(1, $menuPickerPerPage)));
                    $menuPickerPageStart = max(1, $menuPickerPage - 4);
                    $menuPickerPageEnd = min($menuPickerLastPage, $menuPickerPageStart + 8);
                    $menuPickerPageStart = max(1, $menuPickerPageEnd - 8);
                @endphp
                <div class="flex shrink-0 flex-col gap-3 border-t border-gray-200 px-4 py-3 dark:border-gray-700 lg:flex-row lg:items-center lg:justify-between">
                    <p class="text-xs font-medium text-gray-600 dark:text-gray-300">Halaman {{ $menuPickerPage }} dari {{ $menuPickerLastPage }}</p>
                    <div class="max-w-full overflow-x-auto">
                        <div class="inline-flex min-w-max overflow-hidden rounded-lg border border-gray-300 dark:border-gray-600">
                            <button type="button" wire:click="goToMenuPage(1)" @disabled($menuPickerPage <= 1) class="border-r border-gray-300 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800">First</button>
                            <button type="button" wire:click="previousMenuPage" @disabled($menuPickerPage <= 1) aria-label="Halaman sebelumnya" class="border-r border-gray-300 px-3 py-2 text-sm font-bold text-gray-600 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800">&laquo;</button>
                            @foreach(range($menuPickerPageStart, $menuPickerPageEnd) as $pageNumber)
                                <button type="button" wire:click="goToMenuPage({{ $pageNumber }})" @disabled($pageNumber === $menuPickerPage) class="border-r border-gray-300 px-3.5 py-2 text-xs font-semibold transition dark:border-gray-600 {{ $pageNumber === $menuPickerPage ? 'bg-blue-600 text-white disabled:cursor-default disabled:opacity-100' : 'text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-800' }}">{{ $pageNumber }}</button>
                            @endforeach
                            <button type="button" wire:click="nextMenuPage" @disabled(! $menuPickerHasNext) aria-label="Halaman berikutnya" class="border-r border-gray-300 px-3 py-2 text-sm font-bold text-gray-600 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800">&raquo;</button>
                            <button type="button" wire:click="goToMenuPage({{ $menuPickerLastPage }})" @disabled($menuPickerPage >= $menuPickerLastPage) class="px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40 dark:text-gray-200 dark:hover:bg-gray-800">Last</button>
                        </div>
                    </div>
                </div>
            </x-rnd.picker-modal>
        </div>
    @endif
</x-filament-panels::page>
