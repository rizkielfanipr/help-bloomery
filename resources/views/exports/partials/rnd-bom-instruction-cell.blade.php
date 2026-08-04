@if($hasInstruction)
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
@else
    <span class="instruction-empty">—</span>
@endif
