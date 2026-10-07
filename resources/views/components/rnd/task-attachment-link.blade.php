@props(['path', 'number'])

{{-- One authorized Task attachment link (served by RndProjectTaskAttachmentController), labelled with its file type. --}}
@php
    $extension = \Illuminate\Support\Str::lower(pathinfo($path, PATHINFO_EXTENSION));
    $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true);
@endphp

<a
    href="{{ route('helpdesk.rnd-project-tasks.attachments.show', ['path' => $path]) }}"
    target="_blank"
    rel="noopener"
    {{ $attributes->class(['inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-2 py-1 text-[11px] font-semibold text-blue-700 hover:border-blue-300 hover:bg-blue-50 dark:border-gray-700 dark:bg-gray-900 dark:text-blue-300 dark:hover:bg-blue-950/30']) }}
>
    @if($isImage)
        <x-heroicon-o-photo class="h-3.5 w-3.5 shrink-0" />
    @else
        <x-heroicon-o-document-text class="h-3.5 w-3.5 shrink-0" />
    @endif
    Lampiran {{ $number }} · {{ \Illuminate\Support\Str::upper($extension) }}
</a>
