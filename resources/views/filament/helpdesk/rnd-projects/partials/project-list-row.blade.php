@php
    $isArchived = $project->trashed();
    $viewUrl = $isArchived ? null : \App\Filament\Helpdesk\Resources\Projects\ProjectResource::getUrl('view', ['record' => $project]);
@endphp
<li wire:key="project-row-{{ $project->id }}" class="grid gap-3 p-4 transition hover:bg-gray-50 dark:hover:bg-gray-800/40 xl:items-center xl:px-5 xl:py-3 {{ $projectRowGrid }}">
    <div class="min-w-0">
        @if($isArchived)
            <span class="text-sm font-bold text-gray-900 [overflow-wrap:anywhere] dark:text-white">{{ $project->name }}</span>
        @else
            <a href="{{ $viewUrl }}" class="text-sm font-bold text-gray-900 [overflow-wrap:anywhere] hover:text-blue-700 dark:text-white dark:hover:text-blue-300">{{ $project->name }}</a>
        @endif
        @if(filled($project->description))
            <p class="mt-0.5 line-clamp-2 text-xs leading-5 text-gray-500 [overflow-wrap:anywhere] dark:text-gray-400">{{ $project->description }}</p>
        @endif
    </div>
    <div class="min-w-0">
        <span class="inline-flex w-fit rounded-full bg-blue-50 px-2.5 py-1 text-xs font-bold text-blue-700 dark:bg-blue-950/40 dark:text-blue-300">{{ $project->project_type->label() }}</span>
    </div>
    <div class="grid grid-cols-2 gap-3 xl:contents">
        <div class="min-w-0">
            <p class="text-xs text-gray-400 xl:hidden">Periode</p>
            <p class="text-xs font-medium text-gray-700 dark:text-gray-200">
                <span class="whitespace-nowrap">{{ $project->start_date->format('d M Y') }}</span> – <span class="whitespace-nowrap">{{ $project->end_date->format('d M Y') }}</span>
            </p>
        </div>
        <div class="min-w-0">
            <p class="text-xs text-gray-400">{{ match($project->project_type) { \App\Enums\RndProjectType::Premix => 'BOM Assembly', \App\Enums\RndProjectType::Partnership => 'Menu Partnership', default => 'Product Release' } }}</p>
            <p class="text-sm font-bold text-gray-900 dark:text-white">{{ match($project->project_type) { \App\Enums\RndProjectType::Premix => $project->premix_assemblies_count, \App\Enums\RndProjectType::Partnership => $project->partnership_menus_count, default => $project->products_count } }}</p>
        </div>
    </div>
    @if($showStatus)
        @php
            $status = $this->projectLifecycleStatus($project);
            $statusClass = match($status) {
                'Completed' => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
                default => 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300',
            };
        @endphp
        <div class="min-w-0">
            <p class="mb-1 text-xs text-gray-400 xl:hidden">Status</p>
            <span class="inline-flex w-fit rounded-full px-2.5 py-1 text-xs font-bold {{ $statusClass }}">{{ $status }}</span>
        </div>
    @else
        @php
            $duration = max(1, $project->start_date->diffInDays($project->end_date) + 1);
            $elapsed = min($duration, max(0, $project->start_date->diffInDays(today(), false) + 1));
            $progress = min(100, (int) round(($elapsed / $duration) * 100));
        @endphp
        <div class="min-w-0">
            <div class="mb-1 flex items-center justify-between gap-2 text-xs xl:justify-end">
                <span class="text-gray-400 xl:hidden">Progress</span>
                <span class="font-bold text-blue-600 dark:text-blue-400">{{ $progress }}%</span>
            </div>
            <div aria-hidden="true" class="h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                <div class="h-full rounded-full bg-blue-500" style="width: {{ $progress }}%"></div>
            </div>
        </div>
    @endif
    <div class="flex items-center gap-2 xl:justify-end">
        @if($isArchived)
            @if(\App\Filament\Helpdesk\Resources\Projects\ProjectResource::canRestore($project))
                <button type="button" x-on:click="window.BloomeryConfirm.show({ title: 'Pulihkan Project?', text: @js('Project '.$project->name.' akan dipulihkan dari arsip beserta seluruh datanya.'), confirmText: 'Pulihkan Project', icon: 'question', confirmColor: '#059669' }).then((confirmed) => { if (confirmed) $wire.restoreProject({{ $project->id }}) })" wire:loading.attr="disabled" wire:target="restoreProject({{ $project->id }})" aria-label="Pulihkan Project {{ $project->name }}" title="Pulihkan Project" class="inline-flex flex-1 items-center justify-center gap-2 rounded-lg bg-emerald-600 px-3 py-2 text-sm font-bold text-white hover:bg-emerald-700 disabled:opacity-50 xl:flex-none xl:p-2">
                    <span class="xl:hidden">Pulihkan Project</span>
                    <x-heroicon-o-arrow-path class="h-4 w-4" />
                </button>
            @endif
        @else
            <a href="{{ $viewUrl }}" aria-label="Buka Project {{ $project->name }}" title="Buka Project" class="inline-flex flex-1 items-center justify-center gap-2 rounded-lg bg-blue-600 px-3 py-2 text-sm font-bold text-white hover:bg-blue-700 xl:flex-none xl:p-2">
                <span class="xl:hidden">Buka Project</span>
                <x-heroicon-o-arrow-right class="h-4 w-4" />
            </a>
            @if(\App\Filament\Helpdesk\Resources\Projects\ProjectResource::canEdit($project))
                <button type="button" wire:click="openEditProjectModal({{ $project->id }})" wire:loading.attr="disabled" wire:target="openEditProjectModal({{ $project->id }})" aria-label="Edit Project {{ $project->name }}" title="Edit Project" class="rounded-lg border border-gray-300 p-2 text-gray-600 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800">
                    <x-heroicon-o-pencil-square class="h-4 w-4" />
                </button>
            @endif
            @if(\App\Filament\Helpdesk\Resources\Projects\ProjectResource::canDelete($project))
                <button type="button" x-on:click="window.BloomeryConfirm.show({ title: 'Arsipkan Project?', text: @js('Project '.$project->name.' akan diarsipkan dan dapat dipulihkan dari tab Nonaktif. Seluruh Menu, BOM, dan attachment tetap tersimpan.'), confirmText: 'Arsipkan Project' }).then((confirmed) => { if (confirmed) $wire.archiveProject({{ $project->id }}) })" wire:loading.attr="disabled" wire:target="archiveProject({{ $project->id }})" aria-label="Arsipkan Project {{ $project->name }}" title="Arsipkan Project" class="rounded-lg border border-amber-200 p-2 text-amber-700 hover:bg-amber-50 disabled:opacity-50 dark:border-amber-900 dark:text-amber-300 dark:hover:bg-amber-950/30">
                    <x-heroicon-o-archive-box class="h-4 w-4" />
                </button>
            @endif
        @endif
    </div>
</li>
