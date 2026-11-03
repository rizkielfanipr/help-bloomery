@php $attachments = $getRecord()->attachmentList(); @endphp
<div>
    <p class="text-sm font-medium text-gray-950 dark:text-white">Lampiran</p>
    @if($attachments === [])
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Tidak ada lampiran.</p>
    @else
        <ul class="mt-2 flex flex-wrap gap-2">
            @foreach($attachments as $index => $attachment)
                <li>
                    <a href="{{ route('material-sourcing-request-attachments.show', ['sourcingRequest' => $getRecord()->id, 'index' => $index]) }}" target="_blank" rel="noopener"
                       class="inline-flex max-w-xs items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-primary-600 hover:bg-gray-50 dark:border-white/10 dark:text-primary-400 dark:hover:bg-white/5">
                        <x-heroicon-o-paper-clip class="h-4 w-4 shrink-0" />
                        <span class="truncate">{{ $attachment['name'] }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</div>
