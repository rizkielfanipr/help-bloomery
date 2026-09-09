<div class="flex flex-col items-center gap-4 py-4 text-center">
    @if($asset->qrDataUri())
        <img src="{{ $asset->qrDataUri() }}" alt="QR {{ $asset->asset_number }}" class="h-64 w-64">
    @else
        <p class="text-sm text-gray-500">QR Code belum tersedia.</p>
    @endif
    <div><p class="font-bold">{{ $asset->name }}</p><p class="text-sm text-gray-500">{{ $asset->asset_number }}</p></div>
</div>
