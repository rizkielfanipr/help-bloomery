{{-- One consolidated product table of the Memo summary. Expects $rows, $tableKey, and $showShelfLife (WIP tables only); reads $canManage, $shelfLives, and $canFillShelfLife from the page. --}}
<div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
    <table class="w-full min-w-[760px] text-sm">
        <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-800">
            <tr>
                <th class="px-4 py-3 text-left">Produk</th>
                <th class="px-4 py-3 text-left">UOM BOM</th>
                <th class="px-4 py-3 text-left">Purchase UOM</th>
                @if($showShelfLife)
                    <th class="px-4 py-3 text-left">Shelf Life</th>
                @endif
                <th class="px-4 py-3 text-left">Minimum Order</th>
                <th class="px-4 py-3 text-left">Diperbarui</th>
                <th class="px-4 py-3 text-right"><span class="sr-only">Aksi</span></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
            @foreach($rows as $row)
                <tr wire:key="summary-{{ $tableKey }}-{{ $row['key'] }}">
                    <td class="px-4 py-3">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <p class="font-semibold text-gray-900 dark:text-white">{{ $row['product_name'] }}</p>
                            @if(($row['source'] ?? 'bom') === 'manual')
                                <span class="rounded-full bg-sky-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-sky-700 dark:bg-sky-950/40 dark:text-sky-300">Manual</span>
                            @endif
                        </div>
                        <p class="text-xs text-gray-400">{{ $row['product_code'] ?: '—' }}</p>
                    </td>
                    <td class="px-4 py-3">{{ $row['uom_name'] }}</td>
                    <td class="px-4 py-3">
                        @if($row['has_purchase_uom'])
                            {{ $row['purchase_uom_name'] }}
                        @else
                            <span class="text-xs italic text-gray-400">Purchase UOM belum tersedia</span>
                        @endif
                    </td>
                    @if($showShelfLife)
                    <td class="px-4 py-3">
                        @if(! $row['product_detail_id'])
                            <span class="text-xs italic text-red-600 dark:text-red-400">Identitas WIP tidak lengkap</span>
                        @else
                            @php
                                $shelfLife = $shelfLives->get($row['product_detail_id']);
                            @endphp
                            @if($shelfLife && $shelfLife->is_active)
                                <p class="whitespace-nowrap font-semibold text-gray-900 dark:text-white">{{ $shelfLife->shelfLifeLabel() }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $shelfLife->storageConditionLabel() }}</p>
                            @elseif($shelfLife)
                                <x-rnd.wip-shelf-life-status :status="\App\Enums\RndWipShelfLifeStatus::Inactive" />
                            @else
                                <div class="flex items-center gap-1.5">
                                    <span class="text-xs italic text-gray-400">Belum diisi</span>
                                    @if($canFillShelfLife)
                                        <button type="button" wire:click="openMemoShelfLifeModal({{ (int) $row['product_detail_id'] }})" wire:loading.attr="disabled" wire:target="openMemoShelfLifeModal({{ (int) $row['product_detail_id'] }})" aria-label="Isi Shelf Life {{ $row['product_name'] }}" title="Isi Shelf Life" class="inline-flex shrink-0 rounded-lg p-1.5 text-blue-600 hover:bg-blue-50 disabled:opacity-50 dark:text-blue-400 dark:hover:bg-blue-950/40">
                                            <x-heroicon-o-pencil-square class="h-4 w-4" aria-hidden="true" />
                                        </button>
                                    @endif
                                </div>
                            @endif
                        @endif
                    </td>
                    @endif
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-1.5">
                            @if($row['minimum_order'] !== null)
                                <span class="whitespace-nowrap text-sm font-semibold text-gray-900 dark:text-white">{{ rtrim(rtrim(number_format($row['minimum_order'], 4, '.', ''), '0'), '.') }} {{ $row['uom_name'] }}</span>
                            @else
                                <span class="text-xs italic text-gray-400">Belum ditentukan</span>
                            @endif
                            @if($canManage)
                                <button type="button" wire:click="editMinimumOrder({{ \Illuminate\Support\Js::from($row['key']) }})" wire:loading.attr="disabled" wire:target="editMinimumOrder" aria-label="Ubah Minimum Order {{ $row['product_name'] }}" title="Ubah Minimum Order" class="inline-flex shrink-0 rounded-lg p-1.5 text-blue-600 hover:bg-blue-50 disabled:opacity-50 dark:text-blue-400 dark:hover:bg-blue-950/40">
                                    <x-heroicon-o-pencil-square class="h-4 w-4" aria-hidden="true" />
                                </button>
                            @endif
                        </div>
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">{{ $row['product_synced_at'] ? \Illuminate\Support\Carbon::parse($row['product_synced_at'])->diffForHumans() : '—' }}</td>
                    <td class="px-4 py-3 text-right">
                        @if($canManage && ($row['source'] ?? 'bom') === 'manual')
                            <button type="button" x-on:click="window.BloomeryConfirm.show({ title: 'Hapus Product?', text: @js($row['product_name'].' akan dihapus dari Memo.'), confirmText: 'Hapus' }).then((confirmed) => { if (confirmed) $wire.removeExtraProduct({{ (int) $row['extra_product_id'] }}) })" aria-label="Hapus {{ $row['product_name'] }}" title="Hapus Product" class="inline-flex rounded-lg p-1.5 text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/40">
                                <x-heroicon-o-trash class="h-4 w-4" aria-hidden="true" />
                            </button>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
