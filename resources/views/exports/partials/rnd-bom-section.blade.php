<?php
    $materialRows = $bom['bomDetails'] ?? [];
    $rowCount = max(count($materialRows), 1);
    $hasInstruction = ! empty($instruction['content_html']) || ! empty($instruction['images']);
    $resultInfo = ($resultUnitMap ?? [])[(int) ($bom['productDetailID'] ?? 0)] ?? null;
    $resultLabel = App\Services\EsbService::formatResultLabel(
        $bom['productName'] ?? $bomModel->product_name ?? '-',
        $bom['uomName'] ?? '-',
        $resultInfo['baseUnit'] ?? null,
        $resultInfo['conversionFactor'] ?? null,
    );
?>
<div class="bom-section">
    <div class="bom-header">
        <div class="bom-title">{{ $bom['bomName'] ?? $bomModel->bom_name }}</div>
        <div class="bom-subtitle">{{ $sectionLabel }} · {{ ($bom['bomCode'] ?? '') ?: ($bomModel->bom_code ?: '-') }}</div>
        <div class="bom-result">Product Hasil: <strong>{{ $resultLabel }}</strong></div>
    </div>
    <table>
        <thead>
            <tr>
                <th style="width:22px" class="center">No</th>
                <th style="width:70px">Kode</th>
                <th>Nama Bahan</th>
                <th style="width:55px" class="center">Unit</th>
                <th style="width:55px" class="center">Qty</th>
                <th style="width:220px" class="center">Informasi Tambahan</th>
            </tr>
        </thead>
        <tbody>
            @forelse($materialRows as $index => $item)
                <tr>
                    <td class="center num">{{ $index + 1 }}</td>
                    <td>{{ $item['productCode'] ?? '-' }}</td>
                    <td>{{ $item['productName'] ?? '-' }}</td>
                    <td class="center"><span class="unit-badge">{{ $item['uomName'] ?? '-' }}</span></td>
                    <td class="center">{{ rtrim(rtrim(number_format((float) ($item['qty'] ?? 0), 4, '.', ''), '0'), '.') }}</td>
                    @if($loop->first)
                        <td class="additional-cell" rowspan="{{ $rowCount }}">
                            @include('exports.partials.rnd-bom-instruction-cell', ['instruction' => $instruction, 'hasInstruction' => $hasInstruction])
                        </td>
                    @endif
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="no-bom">Belum ada bahan penyusun.</td>
                    <td class="additional-cell">
                        @include('exports.partials.rnd-bom-instruction-cell', ['instruction' => $instruction, 'hasInstruction' => $hasInstruction])
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
