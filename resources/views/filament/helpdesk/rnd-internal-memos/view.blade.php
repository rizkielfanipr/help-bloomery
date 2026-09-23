<x-filament-panels::page>
    @php
        $memo = $this->getRecord();
        $menus = $this->menus();
        $canManage = auth()->user()?->can('update', $memo) ?? false;
        $canUpdateForecast = $this->canUpdateForecast();
        $consolidated = $this->consolidatedMaterials();
        $validation = $this->validation();
        $canFinalize = $this->canFinalize();
        $canCreateRevision = $this->canCreateRevision();
        $canArchive = $this->canArchive();
        $canDeleteMemo = $this->canDeleteMemo();
        $canGeneratePdf = $this->canGeneratePdf();
        $canDownloadPdf = $this->canDownloadPdf();
        $documents = $this->documents();
        $statusClass = match($memo->status->getColor()) {
            'success' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300',
            'warning' => 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300',
            'danger' => 'bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-300',
            'info' => 'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300',
            default => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
        };
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
                        <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">{{ $memo->memo_number }} · Periode {{ $memo->period_month->translatedFormat('F Y') }} · Revisi {{ $memo->revision }}</p>
                    </div>
                </div>
                <div class="flex shrink-0 flex-col items-start gap-2 md:items-end">
                    <span class="inline-flex rounded-lg px-3 py-2 text-sm font-semibold {{ $statusClass }}">{{ $memo->status->getLabel() }}</span>
                    <div class="flex flex-wrap items-center justify-end gap-2">
                        @if($canFinalize)
                            <button type="button" wire:click="finalizeMemo" wire:confirm="Finalisasi Memo ini? Data tidak dapat diedit setelah difinalisasi." wire:loading.attr="disabled" wire:target="finalizeMemo" class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-2 text-xs font-bold text-white hover:bg-emerald-700 disabled:opacity-50">
                                <x-heroicon-o-lock-closed class="h-4 w-4" /> Finalisasi
                            </button>
                        @endif
                        @if($canGeneratePdf)
                            <button type="button" wire:click="generatePdf" wire:loading.attr="disabled" wire:target="generatePdf" class="inline-flex items-center gap-1.5 rounded-lg border border-blue-200 px-3 py-2 text-xs font-bold text-blue-700 hover:bg-blue-50 disabled:opacity-50 dark:border-blue-900 dark:text-blue-300">
                                <x-heroicon-o-document-arrow-down class="h-4 w-4" /> Generate PDF
                            </button>
                        @endif
                        @if($canCreateRevision)
                            <button type="button" wire:click="openRevisionModal" class="inline-flex items-center gap-1.5 rounded-lg border border-blue-200 px-3 py-2 text-xs font-bold text-blue-700 hover:bg-blue-50 dark:border-blue-900 dark:text-blue-300">
                                <x-heroicon-o-document-duplicate class="h-4 w-4" /> Buat Revisi
                            </button>
                        @endif
                        @if($canArchive)
                            <button type="button" wire:click="toggleArchive" wire:confirm="{{ $memo->status === \App\Enums\RndInternalMemoStatus::Archived ? 'Pulihkan Memo ini dari arsip?' : 'Arsipkan Memo ini?' }}" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-2 text-xs font-bold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200">
                                @if($memo->status === \App\Enums\RndInternalMemoStatus::Archived)
                                    <x-heroicon-o-arrow-uturn-left class="h-4 w-4" /> Pulihkan
                                @else
                                    <x-heroicon-o-archive-box class="h-4 w-4" /> Arsipkan
                                @endif
                            </button>
                        @endif
                        @if($canDeleteMemo)
                            <button type="button" wire:click="deleteMemo" wire:confirm="Hapus Memo Internal ini? Memo akan dihapus dari daftar, tetapi data tetap disimpan sebagai soft delete." wire:loading.attr="disabled" wire:target="deleteMemo" class="inline-flex items-center gap-1.5 rounded-lg border border-red-200 px-3 py-2 text-xs font-bold text-red-700 hover:bg-red-50 disabled:opacity-50 dark:border-red-900 dark:text-red-300 dark:hover:bg-red-950/30">
                                <x-heroicon-o-trash class="h-4 w-4" /> Hapus Memo
                            </button>
                        @endif
                    </div>
                    <a href="{{ \App\Filament\Helpdesk\Resources\RndInternalMemos\RndInternalMemoResource::getUrl('index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-blue-700 hover:underline dark:text-blue-300">
                        <x-heroicon-o-arrow-left class="h-4 w-4" /> Daftar Memo
                    </a>
                </div>
            </div>
        </section>

        @if(! empty($validation['blockers']) || ! empty($validation['warnings']))
            <section class="space-y-3 rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
                @if(! empty($validation['blockers']))
                    <div>
                        <p class="flex items-center gap-1.5 text-sm font-bold text-red-700 dark:text-red-300"><x-heroicon-o-x-circle class="h-4 w-4" /> Blocker Finalisasi ({{ count($validation['blockers']) }})</p>
                        <ul class="mt-2 space-y-1 text-xs text-red-600 dark:text-red-400">
                            @foreach($validation['blockers'] as $blocker)
                                <li class="flex items-start gap-1.5"><span class="mt-1.5 h-1 w-1 shrink-0 rounded-full bg-red-500"></span> {{ $blocker }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                @if(! empty($validation['warnings']))
                    <div>
                        <p class="flex items-center gap-1.5 text-sm font-bold text-amber-700 dark:text-amber-300"><x-heroicon-o-exclamation-triangle class="h-4 w-4" /> Warning ({{ count($validation['warnings']) }})</p>
                        <ul class="mt-2 space-y-1 text-xs text-amber-600 dark:text-amber-400">
                            @foreach($validation['warnings'] as $warning)
                                <li class="flex items-start gap-1.5"><span class="mt-1.5 h-1 w-1 shrink-0 rounded-full bg-amber-500"></span> {{ $warning }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </section>
        @endif

        <section class="grid gap-4 rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900 sm:grid-cols-2 lg:grid-cols-4">
            <div><p class="text-xs font-semibold text-gray-500 dark:text-gray-400">Kepada</p><p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">{{ $memo->recipient }}</p></div>
            <div><p class="text-xs font-semibold text-gray-500 dark:text-gray-400">Dari</p><p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">{{ $memo->sender }}</p></div>
            <div><p class="text-xs font-semibold text-gray-500 dark:text-gray-400">Perihal</p><p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">{{ $memo->subject }}</p></div>
            <div><p class="text-xs font-semibold text-gray-500 dark:text-gray-400">Tanggal Memo</p><p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">{{ $memo->memo_date->translatedFormat('d F Y') }}</p></div>
            @if($memo->notes)
                <div class="sm:col-span-2 lg:col-span-4"><p class="text-xs font-semibold text-gray-500 dark:text-gray-400">Catatan</p><p class="mt-1 text-sm text-gray-700 dark:text-gray-200">{{ $memo->notes }}</p></div>
            @endif
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <div class="flex flex-col gap-3 border-b border-gray-200 p-5 dark:border-gray-700 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Menu yang Akan Dirilis</h3>
                    <p class="mt-1 text-sm text-gray-500">{{ $menus->count() }} Menu dari Master Menu BLSS.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    @if($this->canSync())
                        <button type="button" wire:click="runSync" wire:loading.attr="disabled" wire:target="runSync" @disabled($memo->status === \App\Enums\RndInternalMemoStatus::Syncing) class="inline-flex items-center gap-2 rounded-lg border border-blue-200 px-4 py-2 text-sm font-bold text-blue-700 hover:bg-blue-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-blue-900 dark:text-blue-300">
                            <x-heroicon-o-arrow-path class="h-4 w-4 {{ $memo->status === \App\Enums\RndInternalMemoStatus::Syncing ? 'animate-spin' : '' }}" />
                            {{ $memo->status === \App\Enums\RndInternalMemoStatus::Syncing ? 'Sinkronisasi berjalan...' : 'Sinkronkan BOM' }}
                        </button>
                    @endif
                    @if($canManage)
                        <button type="button" wire:click="openMenuPicker" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-bold text-white hover:bg-blue-700">
                            <x-heroicon-o-plus class="h-4 w-4" /> Pilih Menu
                        </button>
                    @endif
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px] text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-800">
                        <tr>
                            <th class="px-4 py-3 text-left">Menu</th>
                            <th class="px-4 py-3 text-left">BOM</th>
                            <th class="px-4 py-3 text-left">Tanggal Rilis</th>
                            <th class="px-4 py-3 text-right">Forecast Qty</th>
                            <th class="px-4 py-3 text-left">Shelf Life</th>
                            <th class="px-4 py-3 text-left">Sync</th>
                            @if($canManage || $canUpdateForecast)<th class="px-4 py-3 text-right">Aksi</th>@endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($menus as $menuRow)
                            <tr wire:key="memo-menu-{{ $menuRow->id }}">
                                <td class="px-4 py-3">
                                    <p class="font-bold text-gray-900 dark:text-white">{{ $menuRow->menu_name }}</p>
                                    <p class="text-xs text-gray-400">{{ $menuRow->menu_code ?: 'ID '.$menuRow->esb_menu_id }}</p>
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-300">{{ $menuRow->bom_name ?: 'BOM-'.$menuRow->esb_bom_id }}</td>
                                <td class="px-4 py-3">{{ $menuRow->release_date->format('d M Y') }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format((float) $menuRow->forecast_quantity, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-300">
                                    @if($menuRow->hasShelfLife())
                                        {{ rtrim(rtrim(number_format((float) $menuRow->shelf_life_value, 2, '.', ''), '0'), '.') }} {{ $menuRow->shelf_life_unit }}
                                        @if($menuRow->storage_condition)<br><span class="text-gray-400">{{ $menuRow->storage_condition }}</span>@endif
                                    @else
                                        <span class="text-amber-600 dark:text-amber-400">Belum diisi</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full bg-gray-100 px-2 py-1 text-[11px] font-bold text-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ $menuRow->sync_status->getLabel() }}</span>
                                    @if($menuRow->sync_error)
                                        <p class="mt-1 max-w-xs text-[11px] leading-4 text-amber-600 dark:text-amber-400">{{ $menuRow->sync_error }}</p>
                                    @endif
                                </td>
                                @if($canManage || $canUpdateForecast)
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-end gap-1.5">
                                            @if($canUpdateForecast)
                                                <button type="button" wire:click="openForecastModal({{ $menuRow->id }})" class="rounded-lg border border-gray-200 p-2 text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300" title="Forecast &amp; Shelf Life" aria-label="Ubah Forecast dan Shelf Life {{ $menuRow->menu_name }}">
                                                    <x-heroicon-o-pencil-square class="h-4 w-4" />
                                                </button>
                                            @endif
                                            @if($canManage)
                                                <button type="button" wire:click="removeMenu({{ $menuRow->id }})" wire:confirm="Hapus Menu ini dari Memo?" class="rounded-lg border border-red-200 p-2 text-red-600 hover:bg-red-50" title="Hapus" aria-label="Hapus Menu {{ $menuRow->menu_name }}">
                                                    <x-heroicon-o-trash class="h-4 w-4" />
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada Menu pada Memo ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        @foreach($menus as $menuRow)
            @php($materials = $menuRow->materials)
            @if($materials->isNotEmpty())
                <section class="rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                    <div class="border-b border-gray-200 p-5 dark:border-gray-700">
                        <h3 class="text-base font-bold text-gray-900 dark:text-white">Bahan · {{ $menuRow->menu_name }}</h3>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Hasil penguraian BOM Menu dan Assembly, per satu unit Menu.</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[720px] text-sm">
                            <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-800">
                                <tr>
                                    <th class="px-4 py-3 text-left">Bahan</th>
                                    <th class="px-4 py-3 text-left">Kategori</th>
                                    <th class="px-4 py-3 text-right">Qty / Menu</th>
                                    <th class="px-4 py-3 text-right">Net Qty (Forecast)</th>
                                    <th class="px-4 py-3 text-left">UOM</th>
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
                                        <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-white">{{ $material->is_wip ? '—' : rtrim(rtrim(number_format((float) $material->net_quantity, 4, '.', ''), '0'), '.') }}</td>
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

        @if(! empty($consolidated['rows']))
            <section class="rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                <div class="border-b border-gray-200 p-5 dark:border-gray-700">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Forecast Konsolidasi</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Total kebutuhan bahan dari seluruh Menu pada Memo ini, dijumlahkan per Product Detail ID dan UOM.</p>
                    @if(! empty($consolidated['warnings']))
                        <div class="mt-3 space-y-1">
                            @foreach($consolidated['warnings'] as $warning)
                                <p class="flex items-start gap-1.5 text-xs text-amber-600 dark:text-amber-400"><x-heroicon-o-exclamation-triangle class="mt-0.5 h-3.5 w-3.5 shrink-0" /> {{ $warning }}</p>
                            @endforeach
                        </div>
                    @endif
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[640px] text-sm">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-800">
                            <tr>
                                <th class="px-4 py-3 text-left">Bahan</th>
                                <th class="px-4 py-3 text-left">UOM</th>
                                <th class="px-4 py-3 text-right">Jumlah Menu</th>
                                <th class="px-4 py-3 text-right">Total Net Qty</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach($consolidated['rows'] as $row)
                                <tr wire:key="consolidated-{{ $row['key'] }}">
                                    <td class="px-4 py-3">
                                        <p class="font-semibold text-gray-900 dark:text-white">{{ $row['product_name'] }}</p>
                                        <p class="text-xs text-gray-400">
                                            {{ $row['product_code'] ?: '—' }}
                                            @if($row['has_fallback_identity'])
                                                <span class="ml-1 rounded-full bg-amber-50 px-1.5 py-0.5 text-[10px] font-bold text-amber-700 dark:bg-amber-950/40 dark:text-amber-300">Tanpa Product Detail ID</span>
                                            @endif
                                        </p>
                                    </td>
                                    <td class="px-4 py-3">{{ $row['uom_name'] }}</td>
                                    <td class="px-4 py-3 text-right">{{ $row['menu_count'] }}</td>
                                    <td class="px-4 py-3 text-right font-bold text-gray-900 dark:text-white">{{ rtrim(rtrim(number_format($row['net_quantity'], 4, '.', ''), '0'), '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        @if($canDownloadPdf && $documents->isNotEmpty())
            <section class="rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                <div class="border-b border-gray-200 p-5 dark:border-gray-700">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Dokumen PDF</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Setiap generate ulang menghasilkan file baru; file lama tidak ditimpa.</p>
                </div>
                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach($documents as $document)
                        <div class="flex items-center justify-between gap-3 px-5 py-3">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">Revisi {{ $document->revision }} · {{ $document->generated_at->translatedFormat('d M Y H:i') }}</p>
                                <p class="text-xs text-gray-400">{{ number_format($document->file_size / 1024, 1) }} KB · Checksum {{ substr($document->checksum, 0, 12) }}…</p>
                            </div>
                            <a href="{{ route('helpdesk.rnd-internal-memos.download-pdf', ['memo' => $memo->id, 'document' => $document->id]) }}" class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-2 text-xs font-bold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200">
                                <x-heroicon-o-arrow-down-tray class="h-4 w-4" /> Unduh
                            </a>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    </div>

    @if($forecastModalMenuId)
        @php($forecastMenu = $menus->firstWhere('id', $forecastModalMenuId))
        <div class="fixed inset-0 z-[130] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-label="Forecast dan Shelf Life">
            <button type="button" wire:click="closeForecastModal" class="absolute inset-0 bg-gray-950/60" aria-label="Tutup"></button>
            <x-rnd.picker-modal title="Forecast &amp; Shelf Life" :description="$forecastMenu?->menu_name" max-width="5xl">
                <x-slot:close>
                    <button type="button" wire:click="closeForecastModal" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800" aria-label="Tutup"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                </x-slot:close>

                <form wire:submit="saveForecast" class="space-y-4 p-5">
                    <div>
                        <label class="text-xs font-semibold text-gray-500 dark:text-gray-400">Forecast Quantity</label>
                        <input type="number" step="any" min="0" wire:model="forecastQuantity" class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                        @error('forecastQuantity')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="text-xs font-semibold text-gray-500 dark:text-gray-400">Shelf Life Value</label>
                            <input type="number" step="any" min="0" wire:model="shelfLifeValue" class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                            @error('shelfLifeValue')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-500 dark:text-gray-400">Shelf Life Unit</label>
                            <input type="text" wire:model="shelfLifeUnit" placeholder="hari / jam" class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                            @error('shelfLifeUnit')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-gray-500 dark:text-gray-400">Storage Condition</label>
                        <input type="text" wire:model="storageCondition" class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                        @error('storageCondition')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-gray-500 dark:text-gray-400">Catatan Shelf Life</label>
                        <textarea wire:model="shelfLifeNotes" rows="2" class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"></textarea>
                        @error('shelfLifeNotes')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="flex justify-end gap-2 border-t border-gray-200 pt-4 dark:border-gray-700">
                        <button type="button" wire:click="closeForecastModal" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Batal</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="saveForecast" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-50">Simpan</button>
                    </div>
                </form>
            </x-rnd.picker-modal>
        </div>
    @endif

    @if($revisionModalOpen)
        <div class="fixed inset-0 z-[130] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-label="Buat Revisi">
            <button type="button" wire:click="closeRevisionModal" class="absolute inset-0 bg-gray-950/60" aria-label="Tutup"></button>
            <x-rnd.picker-modal title="Buat Revisi Baru" description="Menu, Forecast, dan Shelf Life disalin sebagai Draft; BOM perlu disinkronkan ulang." max-width="5xl">
                <x-slot:close>
                    <button type="button" wire:click="closeRevisionModal" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800" aria-label="Tutup"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                </x-slot:close>

                <form wire:submit="createRevision" class="space-y-4 p-5">
                    <div>
                        <label class="text-xs font-semibold text-gray-500 dark:text-gray-400">Nomor Memo Revisi</label>
                        <input type="text" wire:model="revisionMemoNumber" class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                        @error('revisionMemoNumber')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="flex justify-end gap-2 border-t border-gray-200 pt-4 dark:border-gray-700">
                        <button type="button" wire:click="closeRevisionModal" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Batal</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="createRevision" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-50">Buat Revisi</button>
                    </div>
                </form>
            </x-rnd.picker-modal>
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
                                        <button type="button" wire:click="addMenu({{ \Illuminate\Support\Js::from($row) }})" class="shrink-0 rounded-lg bg-blue-600 px-3 py-2 text-xs font-bold text-white hover:bg-blue-700">Pilih</button>
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
