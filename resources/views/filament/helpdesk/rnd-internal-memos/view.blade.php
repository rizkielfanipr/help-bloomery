<x-filament-panels::page>
    @php
        $memo = $this->getRecord();
        $brandLabel = $memo->brandLabel();
        $menus = $this->menus();
        $canManage = $this->canUpdateMemo();
        $canDeleteMemo = $this->canDeleteMemo();
        $summary = $this->summary();
        $shelfLives = $this->summaryShelfLives();
        $canFillShelfLife = $this->canFillShelfLife();
    @endphp
    <div class="space-y-6">
        @php
            $memoInfo = [
                ['label' => 'Nomor Memo', 'icon' => 'heroicon-o-hashtag', 'value' => $memo->memo_number, 'mono' => true],
                ['label' => 'Bulan Memo', 'icon' => 'heroicon-o-calendar', 'value' => $memo->period_month->translatedFormat('F Y')],
                ['label' => 'Brand', 'icon' => 'heroicon-o-tag', 'value' => $brandLabel ?? 'Belum ditentukan'],
            ];
        @endphp
        <section class="rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                <div class="flex min-w-0 items-center gap-3">
                    <x-heroicon-o-document-text class="h-5 w-5 shrink-0 text-blue-600 dark:text-blue-400" aria-hidden="true" />
                    <h2 class="min-w-0 break-words text-lg font-bold text-gray-900 dark:text-white">{{ $memo->title }}</h2>
                    @if($brandLabel)
                        <span class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2 py-0.5 text-[11px] font-semibold text-blue-700 dark:bg-blue-950/40 dark:text-blue-300"><x-heroicon-o-tag class="h-3 w-3" aria-hidden="true" /> {{ $brandLabel }}</span>
                    @else
                        <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-700 dark:bg-amber-950/40 dark:text-amber-300"><x-heroicon-o-exclamation-triangle class="h-3 w-3" aria-hidden="true" /> Brand belum ditentukan</span>
                    @endif
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ \App\Filament\Helpdesk\Resources\RndInternalMemos\RndInternalMemoResource::getUrl('index') }}" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-bold transition border border-gray-200 text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
                        <x-heroicon-o-arrow-left class="h-4 w-4" aria-hidden="true" /> Daftar Memo
                    </a>
                    @if($this->canExportPdf())
                        <a href="{{ route('helpdesk.rnd-internal-memos.export-pdf', ['memo' => $memo->id]) }}" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-bold transition border border-gray-200 text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
                            <x-heroicon-o-arrow-down-tray class="h-4 w-4" aria-hidden="true" /> Export PDF
                        </a>
                    @endif
                    @if($canManage)
                        <button type="button" wire:click="openEditMemoModal" wire:loading.attr="disabled" wire:target="openEditMemoModal" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-bold transition bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50">
                            <x-heroicon-o-pencil-square class="h-4 w-4" aria-hidden="true" /> Edit Info
                        </button>
                    @endif
                    @if($canDeleteMemo)
                        <button type="button" x-on:click="window.BloomeryConfirm.show({ title: 'Hapus Memo?', text: 'Memo ini akan dihapus dari daftar, tetapi data tetap disimpan.', confirmText: 'Hapus Memo' }).then((confirmed) => { if (confirmed) $wire.deleteMemo() })" wire:loading.attr="disabled" wire:target="deleteMemo" aria-label="Hapus Memo" title="Hapus Memo" class="inline-flex rounded-lg border border-red-200 p-2 text-red-600 transition hover:bg-red-50 disabled:opacity-50 dark:border-red-900 dark:text-red-400 dark:hover:bg-red-950/30">
                            <x-heroicon-o-trash class="h-4 w-4" aria-hidden="true" />
                        </button>
                    @endif
                </div>
            </div>
            @if(! $brandLabel && $canManage)
                <div class="flex items-start gap-2 border-b border-amber-200 bg-amber-50/60 px-5 py-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950/20 dark:text-amber-200" role="status">
                    <x-heroicon-o-exclamation-triangle class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
                    <p>Memo lama ini belum mempunyai Brand. Pilih Brand melalui <button type="button" wire:click="openEditMemoModal" class="font-bold underline">Edit Info</button> sebelum membuat revisi.</p>
                </div>
            @endif
            <div class="p-5">
                <dl class="grid gap-3 sm:grid-cols-3">
                    @foreach($memoInfo as $info)
                        <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-700">
                            <dt class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wide text-gray-400">
                                <x-dynamic-component :component="$info['icon']" class="h-3.5 w-3.5" aria-hidden="true" /> {{ $info['label'] }}
                            </dt>
                            <dd @class(['mt-1.5 break-words text-sm font-semibold text-gray-800 dark:text-gray-100', 'font-mono' => $info['mono'] ?? false])>{{ filled($info['value']) ? $info['value'] : '—' }}</dd>
                        </div>
                    @endforeach
                    <div class="rounded-xl border border-gray-200 p-3 sm:col-span-3 dark:border-gray-700">
                        <dt class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wide text-gray-400">
                            <x-heroicon-o-document-text class="h-3.5 w-3.5" aria-hidden="true" /> Catatan
                        </dt>
                        <dd class="mt-1.5 whitespace-pre-line break-words text-sm text-gray-700 dark:text-gray-200">{{ filled($memo->notes) ? $memo->notes : '—' }}</dd>
                    </div>
                </dl>
            </div>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                <div class="flex items-center gap-3">
                    <x-heroicon-o-rectangle-stack class="h-5 w-5 shrink-0 text-blue-600 dark:text-blue-400" aria-hidden="true" />
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Menu Active Store</h3>
                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-semibold text-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ $menus->count() }} Menu</span>
                </div>
                @if($canManage)
                    <div class="flex flex-wrap items-center gap-2">
                        @if($menus->isNotEmpty())
                            <button type="button" wire:click="refreshAllMenus" wire:loading.attr="disabled" wire:target="refreshAllMenus,refreshMenu" aria-label="Refresh Semua Menu" title="Refresh Semua Menu" class="inline-flex rounded-lg border border-gray-200 p-2.5 text-gray-600 transition hover:bg-gray-50 disabled:opacity-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
                                <x-heroicon-o-arrow-path class="h-4 w-4" wire:loading.class="animate-spin" wire:target="refreshAllMenus" aria-hidden="true" />
                            </button>
                        @endif
                        <button type="button" wire:click="openMenuPicker" wire:loading.attr="disabled" wire:target="openMenuPicker,refreshAllMenus" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-3 py-2 text-sm font-bold text-white transition hover:bg-blue-700 disabled:opacity-50">
                            <x-heroicon-o-plus class="h-4 w-4" wire:loading.remove wire:target="openMenuPicker" aria-hidden="true" />
                            <span wire:loading wire:target="openMenuPicker" class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white" aria-hidden="true"></span>
                            Tambah Menu
                        </button>
                    </div>
                @endif
            </div>
            <div class="p-5">
                @if($menus->isEmpty())
                    <div class="rounded-xl border border-dashed border-gray-300 p-8 text-center dark:border-gray-700">
                        <x-heroicon-o-rectangle-stack class="mx-auto h-10 w-10 text-gray-300" aria-hidden="true" />
                        <h4 class="mt-3 font-bold text-gray-700 dark:text-gray-200">Belum ada Menu pada Memo ini</h4>
                        <p class="mt-1 text-sm text-gray-500">Tambahkan Menu dari katalog ESB BLSS untuk mulai menyusun kebutuhan WIP dan RAW.</p>
                    </div>
                @else
                    <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
                        <table class="w-full min-w-[720px] text-sm">
                            <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-800">
                                <tr>
                                    <th class="px-4 py-3 text-left">Menu</th>
                                    <th class="px-4 py-3 text-left">Category</th>
                                    <th class="px-4 py-3 text-left">Category Detail</th>
                                    <th class="px-4 py-3 text-left">Diperbarui</th>
                                    @if($canManage)
                                        <th class="px-4 py-3 text-right"><span class="sr-only">Aksi</span></th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach($menus as $menuRow)
                                    @php
                                        $menuCategory = $this->splitMenuCategory($menuRow->category_detail);
                                    @endphp
                                    <tr wire:key="memo-menu-{{ $menuRow->id }}" class="align-top">
                                        <td class="px-4 py-3">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <p class="font-semibold text-gray-900 dark:text-white">{{ $menuRow->menu_name }}</p>
                                                @if($menuRow->sync_status->value === 'failed')
                                                    <span class="inline-flex items-center gap-1 rounded-full bg-red-50 px-2 py-0.5 text-[11px] font-bold text-red-700 dark:bg-red-950/40 dark:text-red-300"><x-heroicon-o-x-circle class="h-3 w-3" aria-hidden="true" /> Gagal Sinkronisasi</span>
                                                @elseif($menuRow->sync_status->value === 'syncing')
                                                    <span class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2 py-0.5 text-[11px] font-bold text-blue-700 dark:bg-blue-950/40 dark:text-blue-300"><x-heroicon-o-arrow-path class="h-3 w-3" aria-hidden="true" /> Memproses</span>
                                                @endif
                                            </div>
                                            <p class="text-xs text-gray-400">{{ $menuRow->menu_code ?: 'ID '.$menuRow->esb_menu_id }}</p>
                                            @if($menuRow->sync_error)
                                                <p class="mt-1 flex max-w-md items-start gap-1 text-xs leading-5 text-amber-600 dark:text-amber-400"><x-heroicon-o-exclamation-triangle class="mt-0.5 h-3.5 w-3.5 shrink-0" aria-hidden="true" /> {{ $menuRow->sync_error }}</p>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $menuCategory['category'] ?? '—' }}</td>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $menuCategory['detail'] ?? '—' }}</td>
                                        <td class="whitespace-nowrap px-4 py-3 text-xs text-gray-500 dark:text-gray-400">{{ $menuRow->synced_at ? $menuRow->synced_at->diffForHumans() : 'Belum pernah' }}</td>
                                        @if($canManage)
                                            <td class="px-4 py-3">
                                                <div class="flex items-center justify-end gap-1">
                                                    <button type="button" wire:click="refreshMenu({{ $menuRow->id }})" wire:loading.attr="disabled" wire:target="refreshMenu({{ $menuRow->id }})" title="{{ $menuRow->sync_status->value === 'failed' ? 'Coba Ambil Ulang' : 'Refresh' }}" aria-label="{{ $menuRow->sync_status->value === 'failed' ? 'Coba Ambil Ulang' : 'Refresh' }} {{ $menuRow->menu_name }}" class="inline-flex rounded-lg p-1.5 disabled:opacity-50 text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-950/40">
                                                        <x-heroicon-o-arrow-path class="h-4 w-4" wire:loading.class="animate-spin" wire:target="refreshMenu({{ $menuRow->id }})" aria-hidden="true" />
                                                    </button>
                                                    <button type="button" x-on:click="window.BloomeryConfirm.show({ title: 'Hapus Menu?', text: 'Menu ini akan dihapus dari Memo.', confirmText: 'Hapus Menu' }).then((confirmed) => { if (confirmed) $wire.removeMenu({{ $menuRow->id }}) })" title="Hapus Menu" aria-label="Hapus Menu {{ $menuRow->menu_name }}" class="inline-flex rounded-lg p-1.5 disabled:opacity-50 text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/40">
                                                        <x-heroicon-o-trash class="h-4 w-4" aria-hidden="true" />
                                                    </button>
                                                </div>
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </section>


        @if(! empty($summary['warnings']))
            <section class="space-y-1 rounded-2xl border border-amber-200 bg-amber-50/40 p-5 dark:border-amber-900 dark:bg-amber-950/10">
                @foreach($summary['warnings'] as $warning)
                    <p class="flex items-start gap-1.5 text-xs text-amber-700 dark:text-amber-300"><x-heroicon-o-exclamation-triangle class="mt-0.5 h-3.5 w-3.5 shrink-0" /> {{ $warning }}</p>
                @endforeach
            </section>
        @endif

        @foreach([
            'store' => ['title' => 'Product Active Store', 'wip' => 'WIP Store', 'raw' => 'RAW Store', 'icon' => 'heroicon-o-building-storefront'],
            'kitchen' => ['title' => 'Product Active Kitchen', 'wip' => 'WIP Kitchen', 'raw' => 'RAW Kitchen', 'icon' => 'heroicon-o-fire'],
        ] as $scopeKey => $scope)
            @if($canManage || ! empty($summary[$scopeKey]['wip']) || ! empty($summary[$scopeKey]['bahan']))
                @php
                    $scopeTabs = ['wip' => ['label' => $scope['wip'], 'icon' => 'heroicon-o-beaker'], 'bahan' => ['label' => $scope['raw'], 'icon' => 'heroicon-o-cube']];
                    $defaultTab = ! empty($summary[$scopeKey]['wip']) || empty($summary[$scopeKey]['bahan']) ? 'wip' : 'bahan';
                @endphp
                <section wire:key="memo-summary-{{ $scopeKey }}" x-data="{ tab: @js($defaultTab) }" class="rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                        <div class="flex items-center gap-3">
                            <x-dynamic-component :component="$scope['icon']" class="h-5 w-5 shrink-0 text-blue-600 dark:text-blue-400" aria-hidden="true" />
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ $scope['title'] }}</h3>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <a href="{{ route('helpdesk.rnd-internal-memos.product-active-export', ['memo' => $memo->id, 'scope' => $scopeKey]) }}" aria-label="Export {{ $scope['title'] }} ke .xlsx" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-2 text-sm font-bold text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
                                <x-heroicon-o-arrow-down-tray class="h-4 w-4" aria-hidden="true" /> Export .xlsx
                            </a>
                            @if($canManage)
                                <button type="button" x-on:click="$wire.openExtraProductPicker(@js($scopeKey), tab === 'wip' ? 'wip' : 'raw')" wire:loading.attr="disabled" wire:target="openExtraProductPicker" class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3 py-2 text-sm font-bold text-white transition hover:bg-blue-700 disabled:opacity-50">
                                    <x-heroicon-o-plus class="h-4 w-4" aria-hidden="true" />
                                    <span x-text="tab === 'wip' ? @js('Tambah '.$scope['wip']) : @js('Tambah '.$scope['raw'])">Tambah {{ $scope['wip'] }}</span>
                                </button>
                            @endif
                        </div>
                    </div>
                    <div>
                        <div class="flex flex-wrap gap-2 border-b border-gray-200 px-5 py-3 dark:border-gray-700" role="group" aria-label="{{ $scope['title'] }}">
                            @foreach($scopeTabs as $groupKey => $tab)
                                <button type="button" x-on:click="tab = @js($groupKey)" x-bind:aria-pressed="(tab === @js($groupKey)).toString()"
                                    x-bind:class="tab === @js($groupKey) ? 'bg-blue-600 text-white' : 'border border-gray-200 text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800'"
                                    class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-bold transition">
                                    <x-dynamic-component :component="$tab['icon']" class="h-4 w-4" aria-hidden="true" />
                                    {{ $tab['label'] }}
                                    <span class="rounded-full bg-black/10 px-1.5 text-[11px] font-semibold dark:bg-white/10">{{ count($summary[$scopeKey][$groupKey]) }}</span>
                                </button>
                            @endforeach
                        </div>
                        @foreach($scopeTabs as $groupKey => $tab)
                            <div x-show="tab === @js($groupKey)" @if($groupKey !== $defaultTab) x-cloak @endif class="p-5">
                                @if(empty($summary[$scopeKey][$groupKey]))
                                    <p class="rounded-xl border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">Tidak ada {{ $tab['label'] }} pada bagian ini.</p>
                                @else
                                    @include('filament.helpdesk.rnd-internal-memos.partials.summary-table', ['rows' => $summary[$scopeKey][$groupKey], 'tableKey' => $scopeKey.'-'.$groupKey, 'showShelfLife' => $groupKey === 'wip'])
                                @endif
                            </div>
                        @endforeach
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
                        <label for="edit-memo-brand" class="text-xs font-semibold text-gray-500 dark:text-gray-400">Brand *</label>
                        <select id="edit-memo-brand" wire:model="brandId" required @error('brandId') aria-invalid="true" aria-describedby="edit-memo-brand-error" @enderror class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                            <option value="">Pilih Brand</option>
                            @foreach($this->brandOptions() as $brandOption)
                                <option value="{{ $brandOption->id }}">{{ $brandOption->name }}</option>
                            @endforeach
                        </select>
                        @error('brandId')<p id="edit-memo-brand-error" class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        @if(! $memo->hasBrand())
                            <p class="mt-1 text-[11px] text-amber-600 dark:text-amber-400">Brand belum ditentukan untuk Memo lama ini. Pilih Brand yang valid sebelum menyimpan.</p>
                        @else
                            <p class="mt-1 text-[11px] text-gray-400">Mengganti Brand tidak mengubah Menu, BOM, maupun Minimum Order.</p>
                        @endif
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-gray-500 dark:text-gray-400">Catatan</label>
                        <textarea wire:model="notes" rows="3" class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"></textarea>
                        @error('notes')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div class="flex justify-end gap-2 border-t border-gray-200 p-5 dark:border-gray-700">
                    <button type="button" wire:click="closeEditMemoModal" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Batal</button>
                    <button type="submit" wire:loading.attr="disabled" wire:target="saveMemoInfo" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-50">
                        <span wire:loading.remove wire:target="saveMemoInfo">Simpan</span>
                        <span wire:loading wire:target="saveMemoInfo">Menyimpan...</span>
                    </button>
                </div>
            </form>
        </div>
    @endif

    @if($menuPickerOpen)
        <div wire:key="menu-picker-modal" wire:init="initializeMenuPicker" class="fixed inset-0 z-[160] flex items-center justify-center p-3 sm:p-6" role="dialog" aria-modal="true" aria-label="Pilih Menu ESB">
            <button type="button" wire:click="closeMenuPicker" class="absolute inset-0 bg-gray-950/60" aria-label="Tutup pilih Menu"></button>
            <x-rnd.picker-modal title="Pilih Menu ESB" description="Sumber data: ESB BLSS. Menu tanpa BOM atau yang sudah ada pada Memo tidak dapat dipilih." max-width="6xl">
                <x-slot:close>
                    <button type="button" wire:click="closeMenuPicker" class="rounded-lg border border-gray-200 p-2 text-gray-500 hover:bg-gray-50 dark:border-gray-700" aria-label="Tutup"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                </x-slot:close>

                <div class="relative flex min-h-0 flex-1 flex-col overflow-hidden">
                    @php
                        $catalogStatus = $this->menuCatalogStatus();
                    @endphp
                    <div class="flex shrink-0 flex-col gap-2 border-b border-gray-200 px-4 py-3 text-xs dark:border-gray-700 sm:flex-row sm:items-center sm:justify-between" role="status" @if($catalogStatus['syncing']) wire:poll.5s="loadMenuPage({{ $menuPickerPage }})" @endif>
                        <p class="flex items-center gap-1.5 text-gray-600 dark:text-gray-300">
                            @if($catalogStatus['syncing'])
                                <span class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-blue-200 border-t-blue-600" aria-hidden="true"></span> Memperbarui katalog. Daftar terakhir tetap dapat dipakai.
                            @elseif($catalogStatus['failed'])
                                <x-heroicon-o-exclamation-triangle class="h-4 w-4 text-amber-500" aria-hidden="true" /> Pembaruan katalog terakhir gagal. Menampilkan data {{ $catalogStatus['last_synced_at'] ? 'per '.$catalogStatus['last_synced_at']->diffForHumans() : 'yang tersedia' }}.
                            @elseif($catalogStatus['last_synced_at'])
                                <x-heroicon-o-clock class="h-4 w-4" aria-hidden="true" /> Katalog diperbarui {{ $catalogStatus['last_synced_at']->diffForHumans() }}.
                            @else
                                <x-heroicon-o-clock class="h-4 w-4" aria-hidden="true" /> Katalog belum pernah disinkronkan.
                            @endif
                        </p>
                        <button type="button" wire:click="refreshMenuCatalog" wire:loading.attr="disabled" wire:target="refreshMenuCatalog" @disabled($catalogStatus['syncing']) class="inline-flex items-center gap-1.5 self-start rounded-lg border border-gray-200 px-2.5 py-1.5 font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">
                            <x-heroicon-o-arrow-path class="h-3.5 w-3.5" aria-hidden="true" /> Muat Ulang Katalog
                        </button>
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
                                    <th class="px-4 pb-3 pt-2"><input wire:model.live.debounce.700ms="menuSearchCode" type="search" aria-label="Cari kode Menu" placeholder="Cari kode..." class="w-full rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-xs font-normal normal-case text-gray-900 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"></th>
                                    <th class="px-4 pb-3 pt-2"><input wire:model.live.debounce.700ms="menuSearchName" type="search" aria-label="Cari nama Menu" placeholder="Cari nama..." class="w-full rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-xs font-normal normal-case text-gray-900 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"></th>
                                    <th class="px-4 pb-3 pt-2"></th>
                                    <th class="px-4 pb-3 pt-2"></th>
                                    <th class="px-4 pb-3 pt-2"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @if($menuPickerError)
                                    <tr>
                                        <td colspan="5" class="px-5 py-10">
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
                                        <td colspan="5" class="px-5 py-14 text-center text-gray-500 dark:text-gray-400">
                                        @if(trim($menuSearchName.$menuSearchCode) !== '')
                                            Tidak ada Menu yang cocok dengan pencarian.
                                        @elseif($catalogStatus['syncing'])
                                            Katalog sedang disinkronkan. Daftar Menu akan muncul setelah proses selesai.
                                        @else
                                            Katalog Menu BLSS masih kosong. Tekan Muat Ulang Katalog untuk mengambil data dari ESB.
                                        @endif
                                    </td>
                                    </tr>
                                @else
                                    @foreach($menuPickerRows as $row)
                                        @php
                                            $splitCategory = $this->splitMenuCategory($row['categoryDetail']);
                                        @endphp
                                        @php
                                            $selectable = $row['hasBom'] && ! $row['isSelected'];
                                        @endphp
                                        <tr wire:key="menu-picker-{{ $row['menuID'] }}" class="{{ $selectable ? 'hover:bg-blue-50 dark:hover:bg-blue-950/30' : 'opacity-60' }} text-gray-700 transition dark:text-gray-200">
                                            <td class="px-4 py-3"><p class="truncate font-mono font-semibold text-blue-700 dark:text-blue-300">{{ $row['menuCode'] ?: '-' }}</p></td>
                                            <td class="px-4 py-3"><p class="truncate font-semibold text-gray-900 dark:text-white">{{ $row['menuName'] ?: 'Menu #'.$row['menuID'] }}</p></td>
                                            <td class="px-4 py-3"><p class="truncate">{{ collect([$splitCategory['category'], $splitCategory['detail']])->filter()->implode(' · ') ?: '-' }}</p></td>
                                            <td class="px-4 py-3">
                                                @if($row['hasBom'])
                                                    <span class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-700 dark:text-emerald-300"><x-heroicon-o-check-circle class="h-3.5 w-3.5" aria-hidden="true" /> {{ $row['bomName'] ?: 'Memiliki BOM' }}</span>
                                                @else
                                                    <span class="inline-flex items-center gap-1 text-xs font-semibold text-gray-500 dark:text-gray-400"><x-heroicon-o-minus-circle class="h-3.5 w-3.5" aria-hidden="true" /> Belum Memiliki BOM</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-right">
                                                @if($row['isSelected'])
                                                    <span class="inline-flex items-center gap-1 rounded-md border border-gray-200 px-2 py-1 text-xs font-semibold text-gray-500 dark:border-gray-700 dark:text-gray-400"><x-heroicon-o-check class="h-3.5 w-3.5" aria-hidden="true" /> Sudah Dipilih</span>
                                                @elseif($row['hasBom'])
                                                    <button type="button" wire:click="addMenu({{ (int) $row['menuID'] }})" wire:loading.attr="disabled" wire:target="addMenu" aria-label="Pilih Menu {{ $row['menuName'] }}" class="inline-flex items-center gap-1 rounded-md bg-blue-50 px-2 py-1 text-xs font-semibold text-blue-700 hover:bg-blue-100 disabled:opacity-50 dark:bg-blue-950/50 dark:text-blue-300">Pilih</button>
                                                @else
                                                    <span class="inline-flex items-center gap-1 rounded-md border border-gray-200 px-2 py-1 text-xs font-semibold text-gray-400 dark:border-gray-700">Tidak Dapat Dipilih</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                @endif
                            </tbody>
                        </table>
                    </div>

                    <div wire:loading.flex wire:target="initializeMenuPicker,loadMenuPage,previousMenuPage,nextMenuPage,goToMenuPage,updatedMenuSearchName,updatedMenuSearchCode,addMenu" class="absolute inset-0 z-20 hidden items-center justify-center bg-white/70 dark:bg-gray-900/70">
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
    @if($minimumOrderKey !== null && $minimumOrderTarget)
        <div class="fixed inset-0 z-[130] flex items-center justify-center p-4" x-data x-trap.inert.noscroll="true" x-on:keydown.escape.window="$wire.cancelMinimumOrder()">
            <button type="button" wire:click="cancelMinimumOrder" class="absolute inset-0 bg-gray-950/60" aria-label="Tutup modal Minimum Order"></button>
            <form wire:submit="saveMinimumOrder" class="relative flex max-h-[90vh] w-full max-w-md flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900" role="dialog" aria-modal="true" aria-labelledby="minimum-order-heading">
                <div class="flex items-start justify-between gap-4 border-b border-gray-200 p-5 dark:border-gray-700">
                    <h3 id="minimum-order-heading" class="text-lg font-bold text-gray-900 dark:text-white">Minimum Order</h3>
                    <button type="button" wire:click="cancelMinimumOrder" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-200" aria-label="Tutup">
                        <x-heroicon-o-x-mark class="h-5 w-5" />
                    </button>
                </div>
                <div class="space-y-4 overflow-y-auto p-5">
                    <dl class="rounded-xl border border-gray-200 bg-gray-50 p-3.5 text-sm dark:border-gray-700 dark:bg-gray-800/60">
                        <dt class="text-[10px] font-bold uppercase tracking-wide text-gray-400">{{ $minimumOrderTarget['scope_label'] }}</dt>
                        <dd class="mt-0.5 break-words font-semibold text-gray-900 dark:text-white">{{ $minimumOrderTarget['product_name'] }}</dd>
                        <dd class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $minimumOrderTarget['product_code'] ?: '-' }} · UOM BOM {{ $minimumOrderTarget['uom_name'] }}</dd>
                        <dd class="mt-1 text-xs text-gray-600 dark:text-gray-300">Unit Purchase: <span class="font-semibold">{{ $minimumOrderTarget['purchase_uom_name'] ?: 'Belum tersedia' }}</span></dd>
                    </dl>
                    <div>
                        <label for="minimum-order-value" class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Minimum Order</label>
                        <div class="flex items-center overflow-hidden rounded-lg border border-gray-300 bg-white focus-within:border-blue-500 focus-within:ring-1 focus-within:ring-blue-500 dark:border-gray-600 dark:bg-gray-800">
                            <input id="minimum-order-value" type="number" step="any" min="0" inputmode="decimal" wire:model="minimumOrderValue" autofocus @error('minimumOrderValue') aria-invalid="true" aria-describedby="minimum-order-error" @enderror class="min-w-0 flex-1 border-0 bg-transparent px-3 py-2.5 text-sm focus:ring-0 dark:text-white">
                            <span class="border-l border-gray-200 px-3 text-xs font-semibold text-gray-500 dark:border-gray-700 dark:text-gray-400">{{ $minimumOrderTarget['uom_name'] }}</span>
                        </div>
                        @error('minimumOrderValue')<p id="minimum-order-error" class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        <p class="mt-1 text-[11px] text-gray-400">Kosongkan untuk menghapus Minimum Order.</p>
                    </div>
                </div>
                <div class="flex justify-end gap-2 border-t border-gray-200 p-5 dark:border-gray-700">
                    <button type="button" wire:click="cancelMinimumOrder" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Batal</button>
                    <button type="submit" wire:loading.attr="disabled" wire:target="saveMinimumOrder" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-50">
                        <span wire:loading.remove wire:target="saveMinimumOrder">Simpan</span>
                        <span wire:loading wire:target="saveMinimumOrder">Menyimpan...</span>
                    </button>
                </div>
            </form>
        </div>
    @endif
    @include('filament.helpdesk.partials.esb-product-picker-modal', [
        'pickerTitle' => $extraProductTarget
            ? 'Tambah Product · '.mb_strtoupper($extraProductTarget['kind']).' '.ucfirst($extraProductTarget['scope'])
            : 'Tambah Product',
        'pickerSelectMethod' => 'selectExtraProduct',
        'pickerCloseAction' => 'closeInlineProductPicker',
    ])
    @include('filament.helpdesk.pages.partials.wip-shelf-life-modal', ['shelfLifeSaveMethod' => 'saveMemoShelfLife'])
</x-filament-panels::page>
