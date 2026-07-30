<div class="bom-section">
    <div class="bom-title">
        <strong>{{ $bom['bomName'] ?? $bomModel->bom_name }}</strong>
        <div class="bom-subtitle">{{ $sectionLabel }} · {{ $bom['bomCode'] ?? $bomModel->bom_code ?? '-' }} · {{ $bom['productName'] ?? $bomModel->product_name ?? '-' }}</div>
    </div>
    <table class="materials">
        <thead>
            <tr>
                <th class="number">No</th>
                <th class="code">Kode</th>
                <th class="material-name">Nama Bahan</th>
                <th class="unit">Unit</th>
                <th class="qty">Qty</th>
                <th class="additional">Informasi Tambahan</th>
            </tr>
        </thead>
        <tbody>
            @forelse($bom['bomDetails'] ?? [] as $index => $item)
                <tr>
                    <td class="number">{{ $index + 1 }}</td>
                    <td class="code">{{ $item['productCode'] ?? '-' }}</td>
                    <td class="material-name">{{ $item['productName'] ?? '-' }}</td>
                    <td class="unit">{{ $item['uomName'] ?? '-' }}</td>
                    <td class="qty">{{ rtrim(rtrim(number_format((float) ($item['qty'] ?? 0), 4, '.', ''), '0'), '.') }}</td>
                    <td class="additional">
                        @if((float) ($item['yieldPercent'] ?? 0) > 0)Waste: {{ number_format((float) $item['yieldPercent'], 2) }}%<br>@endif
                        @if(! empty($item['printGroup']))Print Group: {{ $item['printGroup'] }}@endif
                        @if((float) ($item['yieldPercent'] ?? 0) <= 0 && empty($item['printGroup']))—@endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">Belum ada bahan penyusun.</td></tr>
            @endforelse
        </tbody>
    </table>
    @if(!empty($instruction['content_html']) || !empty($instruction['images']))
        <div class="instruction">
            <div class="instruction-title">Informasi Tambahan & Cara Pembuatan</div>
            @if(!empty($instruction['content_html']))
                <div class="instruction-content">{!! $instruction['content_html'] !!}</div>
            @endif
            @if(!empty($instruction['images']))
                <div class="instruction-images">
                    @foreach($instruction['images'] as $image)
                        <img class="instruction-image" src="{{ $image }}" alt="Gambar proses">
                    @endforeach
                </div>
            @endif
        </div>
    @endif
</div>
