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
    $showProductSummary = in_array($sectionLabel, ['Main Recipe', 'Menu'], true);
?>
<div class="bom-section">
    <table class="bom-table">
        @if($showProductSummary)
        <tbody class="product-info-row">
            <tr>
                <td class="product-release-photo">
                    @if($productPhoto)
                        <img src="{{ $productPhoto }}" alt="{{ $productRecord->name }}">
                    @else
                        <div class="product-release-placeholder">FOTO PRODUK<br>BELUM TERSEDIA</div>
                    @endif
                </td>
                <td class="product-release-info" colspan="2">
                    <div class="product-field-heading">Nama Product</div>
                    <div class="product-field-content">
                        <div class="product-release-name">{{ $productRecord->name }}</div>
                        <div class="simple-product-code">Product Code / SKU: {{ $productRecord->product_code ?: '-' }}</div>
                    </div>
                </td>
                <td class="simple-price-cell" colspan="3">
                    <div class="product-field-heading">Harga</div>
                    <div class="product-field-content">
                        @forelse($regionalPrices as $price)
                            <div class="regional-price-line">
                                <div class="regional-price-name">{{ $price->region?->name ?? '-' }} <span>{{ $price->region?->code ?? '-' }}</span></div>
                                <div class="regional-price-values">
                                    Offline <strong>Rp {{ number_format((float) $price->offline_price, 0, ',', '.') }}</strong>
                                    &nbsp;·&nbsp; Online <strong>Rp {{ number_format((float) $price->online_price, 0, ',', '.') }}</strong>
                                </div>
                            </div>
                        @empty
                            <div class="regional-price-empty">Belum ada harga regional aktif.</div>
                        @endforelse
                    </div>
                </td>
            </tr>
            <tr>
                <td class="product-detail-cell" colspan="6">
                    <div class="product-field-heading">Product Detail</div>
                    <div class="product-field-content product-detail-text">{{ $productRecord->description ?: 'Belum ada deskripsi product.' }}</div>
                </td>
            </tr>
        </tbody>
        @endif
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
                    <td colspan="2">{{ $item['productName'] ?? '-' }}</td>
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
