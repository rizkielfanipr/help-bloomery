@php $projectOverviewHtml = $this->projectOverviewHtml(); @endphp
<section class="rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900" aria-labelledby="project-overview-heading">
    <div class="flex flex-col justify-between gap-3 border-b border-gray-200 p-5 dark:border-gray-700 sm:flex-row sm:items-center">
        <div class="min-w-0">
            <h3 id="project-overview-heading" class="text-lg font-bold text-gray-900 dark:text-white">Project Overview</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">Tujuan, ruang lingkup, konsep, referensi, dan catatan lengkap Project.</p>
        </div>
        @if($canManage)
            <button type="button" wire:click="mountAction('editProjectOverview')" wire:loading.attr="disabled" wire:target="mountAction" class="inline-flex shrink-0 items-center justify-center gap-2 rounded-lg border border-gray-200 px-3.5 py-2.5 text-sm font-bold text-gray-600 transition hover:bg-gray-50 disabled:opacity-60 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
                <x-heroicon-o-pencil-square class="h-4 w-4" />
                {{ $projectOverviewHtml === null ? 'Tambahkan Overview' : 'Edit Overview' }}
            </button>
        @endif
    </div>
    <div class="p-5">
        @if($projectOverviewHtml === null)
            <div class="py-6 text-center">
                <x-heroicon-o-document-text class="mx-auto h-8 w-8 text-gray-300 dark:text-gray-600" />
                <p class="mt-2 text-sm font-bold text-gray-700 dark:text-gray-200">Project Overview belum ditambahkan</p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Overview merangkum konteks Project yang tidak muat pada deskripsi singkat.</p>
            </div>
        @else
            {{-- Only RndRichContentService output reaches this raw echo. --}}
            <div wire:key="project-overview-content" class="prose prose-sm max-w-none break-words text-gray-700 dark:prose-invert dark:text-gray-200 [&_img]:h-auto [&_img]:max-w-full">
                {!! $projectOverviewHtml !!}
            </div>
        @endif
    </div>
</section>
