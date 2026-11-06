<table class="product-summary-table">
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
                                @if($exportScope === 'store')
                                    <div class="regional-price-channel">Dine In <strong>Rp {{ number_format((float) ($price->dine_in_price ?? $price->offline_price), 0, ',', '.') }}</strong></div>
                                    <div class="regional-price-channel">Takeaway <strong>Rp {{ number_format((float) ($price->takeaway_price ?? $price->offline_price), 0, ',', '.') }}</strong></div>
                                    <div class="regional-price-channel">GoFood <strong>Rp {{ number_format((float) ($price->gofood_price ?? $price->online_price), 0, ',', '.') }}</strong></div>
                                    <div class="regional-price-channel">GrabFood <strong>Rp {{ number_format((float) ($price->grabfood_price ?? $price->online_price), 0, ',', '.') }}</strong></div>
                                    <div class="regional-price-channel">ShopeeFood <strong>Rp {{ number_format((float) ($price->shopeefood_price ?? $price->online_price), 0, ',', '.') }}</strong></div>
                                @else
                                    Offline <strong>Rp {{ number_format((float) $price->offline_price, 0, ',', '.') }}</strong>
                                    &nbsp;-&nbsp; Online <strong>Rp {{ number_format((float) $price->online_price, 0, ',', '.') }}</strong>
                                @endif
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
</table>
