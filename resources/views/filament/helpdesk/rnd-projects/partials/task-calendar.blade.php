@php
    $taskCalendar = $this->taskCalendar();
    $canCreateTask = auth()->user()->can('create', \App\Models\RndProjectTask::class);
@endphp

<section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
    <div class="grid gap-3 lg:grid-cols-6">
        <select wire:model.live="taskFilterProjectId" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
            <option value="">Semua Project</option>
            @foreach($this->taskFilterProjects() as $option)
                <option value="{{ $option->id }}">{{ $option->name }}</option>
            @endforeach
        </select>
        <select wire:model.live="taskFilterBranchId" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
            <option value="">Semua Branch</option>
            @foreach($this->taskFilterBranches() as $option)
                <option value="{{ $option->id }}">{{ $option->name }}</option>
            @endforeach
        </select>
        <select wire:model.live="taskFilterCategory" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
            <option value="">Semua Kategori</option>
            @foreach($this->taskCategoryOptions() as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
        <select wire:model.live="taskFilterStatus" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
            <option value="">Semua Status</option>
            @foreach($this->taskStatusOptions() as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
        <select wire:model.live="taskFilterPicId" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
            <option value="">Semua PIC</option>
            @foreach($this->taskFilterPics() as $option)
                <option value="{{ $option->id }}">{{ $option->display_username }}</option>
            @endforeach
        </select>
        <div class="flex items-center gap-2">
            <label class="inline-flex flex-1 items-center gap-2 rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 dark:border-gray-600 dark:text-gray-200">
                <input type="checkbox" wire:model.live="taskFilterMineOnly" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                Tugas Saya
            </label>
            <button type="button" wire:click="resetTaskFilters" title="Reset filter" aria-label="Reset filter" class="shrink-0 rounded-lg border border-gray-300 p-2 text-gray-500 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800">
                <x-heroicon-o-x-mark class="h-4 w-4" />
            </button>
        </div>
    </div>
</section>

<div class="grid gap-5 md:grid-cols-[minmax(0,1fr)_320px] md:items-start">
<section class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
    <div class="flex flex-col gap-4 border-b border-gray-200 p-4 dark:border-gray-700 sm:flex-row sm:items-center sm:justify-between sm:px-5">
        <div>
            <div class="flex items-center gap-2">
                <x-heroicon-o-calendar-days class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                <h3 class="text-lg font-bold text-gray-900 dark:text-white">Kalender</h3>
            </div>
            <p class="mt-1 text-sm text-gray-500">Tanggal rilis Project ditampilkan bersama deadline Tugas operasional.</p>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" wire:click="previousTaskCalendarMonth" class="rounded-lg border border-gray-300 p-2 text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800" aria-label="Bulan sebelumnya">
                <x-heroicon-o-chevron-left class="h-4 w-4" />
            </button>
            <button type="button" wire:click="currentTaskCalendarMonth" class="min-w-36 rounded-lg border border-gray-300 px-3 py-2 text-sm font-bold text-gray-800 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-100 dark:hover:bg-gray-800">{{ $taskCalendar['monthLabel'] }}</button>
            <button type="button" wire:click="nextTaskCalendarMonth" class="rounded-lg border border-gray-300 p-2 text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800" aria-label="Bulan berikutnya">
                <x-heroicon-o-chevron-right class="h-4 w-4" />
            </button>
            @if($canCreateTask)
                <button type="button" wire:click="openTaskModal" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-3 py-2 text-sm font-bold text-white hover:bg-blue-700">
                    <x-heroicon-o-plus class="h-4 w-4" /> Tambah Tugas
                </button>
            @endif
        </div>
    </div>

    {{-- Desktop: monthly grid --}}
    <div class="hidden overflow-x-auto md:block">
        <div class="min-w-[760px]">
            <div class="grid grid-cols-7 border-b border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800/60">
                @foreach(['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] as $dayName)
                    <div class="border-r border-gray-200 px-3 py-2 text-center text-xs font-bold uppercase tracking-wide text-gray-500 last:border-r-0 dark:border-gray-700">{{ $dayName }}</div>
                @endforeach
            </div>
            @foreach($taskCalendar['weeks'] as $weekIndex => $week)
                <div class="border-b border-gray-200 last:border-b-0 dark:border-gray-700" wire:key="task-calendar-week-{{ $weekIndex }}">
                    <div class="grid grid-cols-7">
                        @foreach($week['dates'] as $day)
                            <button
                                type="button"
                                @if($canCreateTask) wire:click="openTaskModal('{{ $day['date']->toDateString() }}')" @endif
                                class="min-h-10 border-r border-gray-100 px-2 py-2 text-right text-xs last:border-r-0 dark:border-gray-800 {{ $day['isCurrentMonth'] ? 'text-gray-700 hover:bg-blue-50/60 dark:text-gray-200 dark:hover:bg-blue-950/20' : 'bg-gray-50/70 text-gray-300 dark:bg-gray-800/30 dark:text-gray-600' }}"
                            >
                                <span class="inline-flex h-6 w-6 items-center justify-center rounded-full {{ $day['isToday'] ? 'bg-blue-600 font-bold text-white' : '' }}">{{ $day['date']->day }}</span>
                            </button>
                        @endforeach
                    </div>
                    <div class="grid grid-cols-7 gap-y-1 px-1 pb-2">
                        @foreach($week['projects'] as $segment)
                            @php
                                $releaseProject = $segment['project'];
                            @endphp
                            <a
                                href="{{ \App\Filament\Helpdesk\Resources\Projects\ProjectResource::getUrl('view', ['record' => $releaseProject]) }}"
                                class="mx-0.5 flex items-center gap-1 truncate rounded-md border border-dashed border-indigo-300 bg-indigo-50 px-2 py-1.5 text-left text-xs font-bold text-indigo-800 hover:brightness-95 dark:border-indigo-800 dark:bg-indigo-950/40 dark:text-indigo-200"
                                style="grid-column: {{ $segment['dayColumn'] }} / span 1"
                                title="Rilis {{ $releaseProject->name }} · {{ $releaseProject->end_date->format('d M Y') }}"
                            >
                                <x-heroicon-o-flag class="h-3 w-3 shrink-0" />
                                <span class="truncate">{{ $releaseProject->name }}</span>
                            </a>
                        @endforeach
                        @foreach($week['tasks'] as $segment)
                            @php
                                $task = $segment['task'];
                            @endphp
                            <x-rnd.task-chip
                                :task="$task"
                                wire:click="openTaskDetail({{ $task->id }})"
                                class="mx-0.5 truncate rounded-md px-2 py-1.5 text-xs font-bold"
                                style="grid-column: {{ $segment['dayColumn'] }} / span 1"
                                title="{{ $task->title }} · {{ $task->status->getLabel() }} · Deadline {{ $task->due_date->format('d M Y') }}"
                            >
                                {{ $task->title }}
                            </x-rnd.task-chip>
                        @endforeach
                        @if(count($week['tasks']) === 0 && count($week['projects']) === 0)
                            <div class="col-span-7 h-6"></div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Mobile: agenda list so nothing gets clipped in a 7-column grid --}}
    <div class="divide-y divide-gray-100 md:hidden dark:divide-gray-800">
        @php
            $tasksByDate = $this->taskCalendarTasks()->sortBy('due_date')->groupBy(fn ($task) => $task->due_date->toDateString());
            $releasesByDate = collect($taskCalendar['weeks'])
                ->flatMap(fn (array $week): array => $week['projects'])
                ->pluck('project')
                ->unique('id')
                ->sortBy('end_date')
                ->groupBy(fn (\App\Models\RndProject $project) => $project->end_date->toDateString());
            $agendaDates = $tasksByDate->keys()->merge($releasesByDate->keys())->unique()->sort()->values();
        @endphp
        @forelse($agendaDates as $dateKey)
            <div class="p-4">
                <p class="mb-2 text-xs font-bold uppercase tracking-wide text-gray-500">{{ \Illuminate\Support\Carbon::parse($dateKey)->translatedFormat('d F Y') }}</p>
                <div class="space-y-2">
                    @foreach($releasesByDate->get($dateKey, []) as $releaseProject)
                        <a href="{{ \App\Filament\Helpdesk\Resources\Projects\ProjectResource::getUrl('view', ['record' => $releaseProject]) }}" class="flex w-full items-center gap-2 rounded-lg border border-dashed border-indigo-300 bg-indigo-50 px-3 py-2 text-left text-sm text-indigo-800 dark:border-indigo-800 dark:bg-indigo-950/40 dark:text-indigo-200">
                            <x-heroicon-o-flag class="h-4 w-4 shrink-0" />
                            <span class="min-w-0 truncate font-bold">Rilis · {{ $releaseProject->name }}</span>
                        </a>
                    @endforeach
                    @foreach($tasksByDate->get($dateKey, []) as $task)
                        <x-rnd.task-chip :task="$task" wire:click="openTaskDetail({{ $task->id }})" class="flex w-full items-center justify-between gap-2 rounded-lg px-3 py-2 text-sm">
                            <span class="min-w-0 truncate font-bold">{{ $task->title }}</span>
                            <span class="shrink-0 text-xs font-semibold">{{ $task->status->getLabel() }}</span>
                        </x-rnd.task-chip>
                    @endforeach
                </div>
            </div>
        @empty
            <div class="p-8 text-center text-sm text-gray-400">Tidak ada Tugas atau tanggal rilis pada bulan ini.</div>
        @endforelse
    </div>
</section>

<aside class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900 md:sticky md:top-4">
    <div class="mb-3 flex items-center gap-2">
        <x-heroicon-o-user-circle class="h-5 w-5 text-blue-600 dark:text-blue-400" />
        <h4 class="text-sm font-bold text-gray-900 dark:text-white">Tugas Saya</h4>
    </div>
    <div class="space-y-2">
        @forelse($this->myOpenTaskAssignments() as $myAssignment)
            @php $myTask = $myAssignment->task; @endphp
            <button
                type="button"
                wire:key="my-open-task-{{ $myAssignment->id }}"
                wire:click="openTaskDetail({{ $myTask->id }})"
                class="block w-full rounded-xl border px-3 py-2.5 text-left transition hover:border-blue-300 hover:bg-blue-50/40 dark:hover:border-blue-700 dark:hover:bg-blue-950/20 {{ $myTask->isOverdue() ? 'border-red-200 dark:border-red-900' : 'border-gray-200 dark:border-gray-700' }}"
            >
                <div class="flex items-start justify-between gap-2">
                    <span class="min-w-0 truncate text-sm font-bold text-gray-900 dark:text-white">{{ $myTask->title }}</span>
                    <x-filament::badge :color="$myAssignment->status->getColor()" class="shrink-0">{{ $myAssignment->status->getLabel() }}</x-filament::badge>
                </div>
                <p class="mt-1 truncate text-xs text-gray-500">{{ $myTask->project->name }} · {{ $myAssignment->branch->name }}</p>
                <p class="mt-1.5 text-xs font-semibold {{ $myTask->isOverdue() ? 'text-red-600 dark:text-red-400' : 'text-gray-500' }}">
                    Deadline {{ $myTask->due_date->format('d M Y') }}{{ $myTask->isOverdue() ? ' · Overdue' : '' }}
                </p>
            </button>
        @empty
            <p class="py-8 text-center text-xs text-gray-400">Tidak ada Tugas aktif untuk Anda.</p>
        @endforelse
    </div>
</aside>
</div>

@include('filament.helpdesk.rnd-projects.partials.task-form-modal')

@include('filament.helpdesk.rnd-projects.partials.task-detail-modal')

@include('filament.helpdesk.rnd-projects.partials.task-copy-modal')
