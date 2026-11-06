<?php
    $materialRows = $bom['bomDetails'] ?? [];
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
    <table class="bom-table">
        <tbody>
            <tr>
                <td class="bom-header" colspan="6">
                    <div class="bom-title">{{ $bom['bomName'] ?? $bomModel->bom_name }}</div>
                    @unless($sectionLabel === 'Menu')
                        <div class="bom-result">Product Hasil: <strong>{{ $resultLabel }}</strong></div>
                    @endunless
                </td>
            </tr>
        </tbody>
        <tbody>
            <tr>
                <th class="center number-column">NO</th>
                <th style="width:70px">KODE</th>
                <th colspan="2">NAMA BAHAN</th>
                <th style="width:55px" class="center">UNIT</th>
                <th style="width:55px" class="center">QTY</th>
            </tr>
        </tbody>
        <tbody>
            @forelse($materialRows as $index => $item)
                <tr>
                    <td class="center num number-column">{{ $index + 1 }}</td>
                    <td>{{ $item['productCode'] ?? '-' }}</td>
                    <td colspan="2">
                        {{ $item['productName'] ?? '-' }}
                        @if($item['isDocumentOnly'] ?? false)<small>(SOP)</small>@endif
                        @if(filled($item['notes'] ?? null))<br><small>{{ $item['notes'] }}</small>@endif
                    </td>
                    <td class="center"><span class="unit-badge">{{ $item['uomName'] ?? '-' }}</span></td>
                    <td class="center">{{ rtrim(rtrim(number_format((float) ($item['qty'] ?? 0), 4, '.', ''), '0'), '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="no-bom">Belum ada bahan penyusun.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    <div class="additional-info-section">
        <div class="additional-info-heading">INFORMASI TAMBAHAN</div>
        <div class="additional-info-content">
            @include('exports.partials.rnd-bom-instruction-cell', ['instruction' => $instruction, 'hasInstruction' => $hasInstruction])
        </div>
    </div>
</div>
