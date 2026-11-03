@php
    $incident = $getRecord();
    $receipts = \App\Models\GoodsReceipt::query()
        ->where('company_code', $incident->goodsReceipt?->company_code)
        ->where('reference_number', $incident->goodsReceipt?->reference_number)
        ->orderBy('goods_receipt_date')->orderBy('id')
        ->get(['id', 'esb_goods_receipt_number', 'goods_receipt_date', 'po_required_date', 'delivery_delay_days', 'delivery_assessment_status', 'status']);
@endphp
<div>
    <p class="text-sm font-medium text-gray-950 dark:text-white">Penerimaan PO {{ $incident->goodsReceipt?->reference_number }}</p>
    <div class="mt-2 overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
        <table class="w-full min-w-[520px] text-left text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 dark:bg-white/5 dark:text-gray-400">
                <tr>
                    <th class="px-3 py-2 font-medium">Goods Receipt</th>
                    <th class="px-3 py-2 font-medium">Diterima</th>
                    <th class="px-3 py-2 font-medium">Dibutuhkan</th>
                    <th class="px-3 py-2 font-medium">Penilaian</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                @foreach ($receipts as $receipt)
                    <tr>
                        <td class="px-3 py-2 text-gray-700 dark:text-gray-200">
                            {{ $receipt->esb_goods_receipt_number ?? 'GR lokal #'.$receipt->id }}
                            @if ($receipt->id === $incident->goods_receipt_id)<span class="ml-1 text-xs text-gray-400">(pemicu)</span>@endif
                        </td>
                        <td class="px-3 py-2 text-gray-700 dark:text-gray-200">{{ $receipt->goods_receipt_date?->format('d M Y') }}</td>
                        <td class="px-3 py-2 text-gray-700 dark:text-gray-200">{{ $receipt->po_required_date?->format('d M Y') ?? '-' }}</td>
                        <td class="px-3 py-2 text-gray-700 dark:text-gray-200">
                            {{ $receipt->delivery_assessment_status?->getLabel() ?? 'Belum Dinilai' }}@if ($receipt->delivery_delay_days) · {{ $receipt->delivery_delay_days }} hari @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
