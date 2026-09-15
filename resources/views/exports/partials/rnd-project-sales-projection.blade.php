<section class="sales-projection-section">
    <div class="sales-projection-title">Sales Projection</div>

    @php
        $hasSalesProjections = $products->contains(fn ($product) => $product->salesProjections->isNotEmpty());
    @endphp

    @if($hasSalesProjections)
        <table class="sales-projection-table">
            <thead>
                <tr>
                    <th>Periode</th>
                    <th>Region / Channel</th>
                    <th>Target Qty</th>
                    <th>Target Omzet</th>
                    <th>Outlet</th>
                    <th>Target Cabang</th>
                </tr>
            </thead>
            <tbody>
                @foreach($products as $product)
                    @if($product->salesProjections->isNotEmpty())
                        @foreach($product->salesProjections as $projection)
                            <tr>
                                <td>{{ $projection->projection_month->translatedFormat('M Y') }}</td>
                                <td>
                                    <strong>{{ $projection->region?->name ?? 'Semua Region' }}</strong><br>
                                    {{ \App\Models\RndProductSalesProjection::CHANNELS[$projection->channel] ?? ucfirst($projection->channel) }}
                                </td>
                                <td class="money">{{ number_format((float) $projection->target_quantity, 2, ',', '.') }}</td>
                                <td class="money">Rp {{ number_format((float) $projection->target_revenue, 0, ',', '.') }}</td>
                                <td class="center">{{ $projection->target_outlets ?: '—' }}</td>
                                <td>
                                    <div class="sales-projection-branches">
                                        @forelse($projection->targetBranches as $branch)
                                            {{ $branch->name }}: {{ number_format((float) $branch->pivot->target_quantity, 2, ',', '.') }}{{ ! $loop->last ? '; ' : '' }}
                                        @empty
                                            —
                                        @endforelse
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    @endif
                @endforeach
            </tbody>
        </table>
    @else
        <div class="sales-projection-empty">Belum ada Sales Projection pada produk yang termasuk dalam dokumen Store ini.</div>
    @endif
</section>
