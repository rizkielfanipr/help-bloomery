@php
    $inlineBomId = $bom->id;
    $inlineDetail = $bomComponentDetails[$inlineBomId] ?? null;
    $inlineDraft = $bomComponentDrafts[$inlineBomId] ?? null;
    $inlineEditing = (bool) ($bomComponentEditing[$inlineBomId] ?? false);
    $inlineRows = $inlineEditing
        ? ($inlineDraft['bomDetails'] ?? [])
        : ($inlineDetail['bomDetails'] ?? []);
    $inlineResult = $inlineEditing ? $inlineDraft : $inlineDetail;
    $inlineIsMenu = $this->isMenuBomDetail($inlineDetail ?? []);
    $inlineColumnCount = $inlineIsMenu ? 7 : 8;
@endphp

<div class="border-t border-gray-200 bg-white/70 dark:border-gray-700 dark:bg-gray-900/60">
    <div class="flex flex-wrap items-center justify-between gap-2 px-3 py-2.5">
        <div class="inline-flex items-center gap-1.5 text-xs font-bold text-gray-700 dark:text-gray-200">
            <x-heroicon-o-list-bullet class="h-4 w-4 text-blue-600" />
            Detail BOM @if($inlineDetail)({{ count($inlineDetail['bomDetails'] ?? []) }} komponen)@endif
        </div>
        <div class="flex items-center gap-1.5">
            <button type="button" wire:click="loadBomComponents({{ $inlineBomId }}, true)" wire:loading.attr="disabled" wire:target="loadBomComponents({{ $inlineBomId }}, true)" class="rounded-md border border-gray-200 p-1.5 text-gray-500 hover:bg-gray-50 dark:border-gray-700" title="Muat ulang dari ESB">
                <x-heroicon-o-arrow-path class="h-3.5 w-3.5" />
            </button>
            @if($canUpdateBomInline && $inlineDetail && !$inlineEditing)
                <button type="button" wire:click="editBomComponents({{ $inlineBomId }})" class="rounded-md bg-blue-50 px-2.5 py-1.5 text-[11px] font-bold text-blue-700 hover:bg-blue-100 dark:bg-blue-950/40 dark:text-blue-300">Edit BOM</button>
            @endif
        </div>
    </div>

    <div wire:loading.flex wire:target="loadBomComponents({{ $inlineBomId }}),loadBomComponents({{ $inlineBomId }}, true),updateInlineBom({{ $inlineBomId }})" class="items-center gap-2 border-t border-gray-100 px-3 py-3 text-xs font-semibold text-blue-600 dark:border-gray-800">
        <span class="h-4 w-4 animate-spin rounded-full border-2 border-blue-200 border-r-blue-600"></span>
        Memproses komponen BOM...
    </div>

    @if($inlineDetail)
        <div wire:loading.remove wire:target="loadBomComponents({{ $inlineBomId }}),loadBomComponents({{ $inlineBomId }}, true)" class="border-t border-gray-100 dark:border-gray-800">
            @unless($inlineIsMenu)
                <div class="flex flex-col gap-3 border-b border-gray-100 bg-blue-50/40 px-3 py-3 dark:border-gray-800 dark:bg-blue-950/10 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <p class="text-[10px] font-bold uppercase tracking-wide text-blue-600">Product Hasil</p>
                        <p class="mt-0.5 truncate text-sm font-bold text-gray-900 dark:text-white">{{ $inlineResult['productName'] ?? 'Product hasil belum dipilih' }}</p>
                        <p class="mt-0.5 truncate text-xs text-gray-500">
                            <span class="font-mono font-bold text-blue-600">{{ ($inlineResult['productCode'] ?? '') ?: 'Tanpa kode' }}</span>
                        </p>
                        @if(!empty($resultUnitLabels[$inlineBomId]))
                            <p class="mt-1.5 inline-block rounded-md border border-emerald-200 bg-emerald-50 px-2 py-1 text-xs font-bold text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300">
                                {{ $resultUnitLabels[$inlineBomId] }}
                            </p>
                        @endif
                        @error("bomComponentDrafts.$inlineBomId.productDetailID")<p class="mt-1 text-[10px] font-semibold text-red-600">{{ $message }}</p>@enderror
                    </div>
                    @if($inlineEditing)
                        <button type="button" wire:click="openInlineProductPicker({{ $inlineBomId }}, 'result')" class="inline-flex shrink-0 items-center justify-center gap-1.5 rounded-lg border border-blue-200 bg-white px-3 py-2 text-xs font-bold text-blue-700 hover:bg-blue-50 dark:border-blue-800 dark:bg-gray-900">
                            <x-heroicon-o-arrow-path class="h-4 w-4" /> Ganti Product Hasil
                        </button>
                    @endif
                </div>
            @endunless

            <div class="flex items-center justify-between gap-3 px-3 py-2.5">
                <p class="text-xs font-bold text-gray-700 dark:text-gray-200">{{ $inlineIsMenu ? 'Item Menu' : 'Komponen Penyusun' }}</p>
                @if($inlineEditing)
                    <button type="button" wire:click="openInlineProductPicker({{ $inlineBomId }}, 'component')" class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3 py-2 text-xs font-bold text-white hover:bg-blue-700">
                        <x-heroicon-o-plus class="h-4 w-4" /> Tambah Komponen
                    </button>
                @endif
            </div>

            <div class="overflow-x-auto">
            <table class="w-full min-w-[880px] table-fixed text-xs">
                <colgroup>
                    <col class="w-[28%]">
                    <col class="w-[9%]">
                    <col class="w-[12%]">
                    <col class="w-[10%]">
                    <col class="w-[10%]">
                    @unless($inlineIsMenu)
                        <col class="w-[11%]">
                    @endunless
                    <col class="w-[14%]">
                    <col class="w-[6%]">
                </colgroup>
                <thead class="bg-gray-50 text-[10px] uppercase text-gray-500 dark:bg-gray-800">
                    <tr>
                        <th class="px-3 py-2 text-left">Kode & Bahan</th>
                        <th class="px-3 py-2 text-left">Unit</th>
                        <th class="px-3 py-2 text-right" title="Harga rata-rata tertimbang dari histori pembelian (Product Price Index)">Harga WA</th>
                        <th class="px-3 py-2 text-right">Qty</th>
                        <th class="px-3 py-2 text-right">Waste %</th>
                        @unless($inlineIsMenu)
                            <th class="px-3 py-2 text-right">Tolerance %</th>
                        @endunless
                        <th class="px-3 py-2 text-left">Print Group</th>
                        <th class="px-2 py-2 text-right">
                            @if($inlineEditing)
                                Aksi
                            @endif
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse($inlineRows as $componentIndex => $component)
                        <tr wire:key="inline-bom-{{ $inlineBomId }}-component-{{ $componentIndex }}" class="align-top">
                            <td class="px-3 py-2.5">
                                <p class="font-mono text-[10px] font-bold text-blue-600">{{ $component['productCode'] ?: 'PD-'.$component['productDetailID'] }}</p>
                                <p class="mt-0.5 truncate font-semibold text-gray-900 dark:text-white" title="{{ $component['productName'] ?: 'Product Detail '.$component['productDetailID'] }}">{{ $component['productName'] ?: 'Product Detail '.$component['productDetailID'] }}</p>
                            </td>
                            <td class="px-3 py-2.5 font-semibold">{{ $component['uomName'] ?: '-' }}</td>
                            <td class="px-3 py-2 text-right">
                                @php
                                    $waRow = $waPrices[(int) $component['productDetailID']] ?? null;
                                @endphp
                                @if($waRow)
                                    <p class="font-bold text-emerald-700 dark:text-emerald-400">{{ number_format($waRow['average_price'], 0, ',', '.') }}</p>
                                    <p class="text-[9px] text-gray-400">{{ $waRow['po_count'] }}x beli</p>
                                @else
                                    <p class="text-gray-400" title="Tidak ada histori pembelian pada rentang tanggal ini, pakai harga ESB">{{ number_format((float) ($component['lastHPP'] ?? 0), 0, ',', '.') }}</p>
                                    <p class="text-[9px] text-amber-600">pakai ESB</p>
                                @endif
                            </td>
                            <td class="px-3 py-2">
                                @if($inlineEditing)
                                    <input wire:model="bomComponentDrafts.{{ $inlineBomId }}.bomDetails.{{ $componentIndex }}.qty" type="number" min="0.0001" step="0.0001" class="block w-full min-w-0 rounded-md border border-gray-300 px-2 py-1.5 text-right text-xs dark:border-gray-600 dark:bg-gray-800">
                                    @error("bomComponentDrafts.$inlineBomId.bomDetails.$componentIndex.qty")<p class="mt-1 text-[10px] text-red-600">{{ $message }}</p>@enderror
                                @else
                                    <p class="text-right font-bold">{{ rtrim(rtrim(number_format((float) $component['qty'], 4, '.', ''), '0'), '.') }}</p>
                                @endif
                            </td>
                            <td class="px-3 py-2">
                                @if($inlineEditing)
                                    <input wire:model="bomComponentDrafts.{{ $inlineBomId }}.bomDetails.{{ $componentIndex }}.yieldPercent" type="number" min="0" max="100" step="0.01" class="block w-full min-w-0 rounded-md border border-gray-300 px-2 py-1.5 text-right text-xs dark:border-gray-600 dark:bg-gray-800">
                                @else
                                    <p class="text-right">{{ $component['yieldPercent'] }}</p>
                                @endif
                            </td>
                            @unless($inlineIsMenu)
                                <td class="px-3 py-2">
                                    @if($inlineEditing)
                                        <input wire:model="bomComponentDrafts.{{ $inlineBomId }}.bomDetails.{{ $componentIndex }}.tolerancePercent" type="number" min="0" max="100" step="0.01" class="block w-full min-w-0 rounded-md border border-gray-300 px-2 py-1.5 text-right text-xs dark:border-gray-600 dark:bg-gray-800">
                                    @else
                                        <p class="text-right">{{ $component['tolerancePercent'] }}</p>
                                    @endif
                                </td>
                            @endunless
                            <td class="px-3 py-2">
                                @if($inlineEditing)
                                    <input wire:model="bomComponentDrafts.{{ $inlineBomId }}.bomDetails.{{ $componentIndex }}.printGroup" maxlength="100" class="block w-full min-w-0 rounded-md border border-gray-300 px-2 py-1.5 text-xs dark:border-gray-600 dark:bg-gray-800">
                                @else
                                    {{ $component['printGroup'] ?: '-' }}
                                @endif
                            </td>
                            <td class="px-2 py-2 text-right">
                                @if($inlineEditing)
                                    <button type="button" wire:click="removeInlineBomComponent({{ $inlineBomId }}, {{ $componentIndex }})" wire:confirm="Hapus komponen ini dari BOM?" class="rounded-md border border-red-200 p-1.5 text-red-600 hover:bg-red-50"><x-heroicon-o-trash class="h-3.5 w-3.5" /></button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $inlineColumnCount }}" class="px-3 py-8 text-center text-gray-500">Tidak ada komponen pada BOM ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
            </div>

            @if(!empty($inlineRows))
                @php
                    $waTotal = $this->bomWeightedAverageTotal($inlineBomId);
                @endphp
                <div class="flex flex-wrap items-center justify-between gap-2 border-t border-gray-100 bg-emerald-50/40 px-3 py-2.5 dark:border-gray-800 dark:bg-emerald-950/10">
                    <p class="text-xs font-bold text-emerald-800 dark:text-emerald-300">
                        Total HPP (dari Weighted Average): Rp {{ number_format($waTotal['total'], 0, ',', '.') }}
                    </p>
                    @if($waTotal['hasFallback'])
                        <p class="text-[10px] text-amber-600">*Sebagian komponen belum punya histori pembelian pada rentang tanggal ini, memakai harga ESB.</p>
                    @endif
                </div>
            @endif

            @error("bomComponentDrafts.$inlineBomId")<p class="border-t border-red-100 bg-red-50 px-3 py-2 text-xs font-semibold text-red-700">{{ $message }}</p>@enderror
            @error("bomComponentDrafts.$inlineBomId.bomDetails")<p class="border-t border-red-100 bg-red-50 px-3 py-2 text-xs font-semibold text-red-700">{{ $message }}</p>@enderror

            @if($inlineEditing)
                <div class="flex justify-end gap-2 border-t border-gray-200 px-3 py-3 dark:border-gray-700">
                    <button type="button" wire:click="cancelBomComponentEdit({{ $inlineBomId }})" class="rounded-lg border border-gray-300 px-3 py-2 text-xs font-bold text-gray-700 dark:border-gray-600 dark:text-gray-200">Batal</button>
                    <button type="button" wire:click="updateInlineBom({{ $inlineBomId }})" wire:loading.attr="disabled" wire:target="updateInlineBom({{ $inlineBomId }})" class="rounded-lg bg-blue-600 px-3 py-2 text-xs font-bold text-white hover:bg-blue-700 disabled:opacity-50">Simpan ke ESB</button>
                </div>
            @endif
        </div>
    @else
        <div wire:loading.remove wire:target="loadBomComponents({{ $inlineBomId }})" class="border-t border-gray-100 px-3 py-5 text-center text-xs text-gray-500 dark:border-gray-800">
            Komponen belum dapat dimuat dari ESB. Gunakan tombol refresh untuk mencoba kembali.
        </div>
    @endif
</div>
