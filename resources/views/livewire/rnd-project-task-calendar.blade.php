<div>
    <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900" aria-labelledby="project-task-calendar-heading">
        <div class="flex flex-col gap-4 border-b border-gray-200 p-5 dark:border-gray-700 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex min-w-0 items-start gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-950/40 dark:text-blue-300">
                    <x-heroicon-o-calendar-days class="h-6 w-6" />
                </div>
                <div class="min-w-0">
                    <h3 id="project-task-calendar-heading" class="text-lg font-bold text-gray-900 dark:text-white">Kalender &amp; Task</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Deadline Task Project ini. Klik tanggal untuk menambah Task, atau gunakan template checkpoint.</p>
                </div>
            </div>
            @if($canApplyTemplate || $canCreateTask)
                <div class="grid shrink-0 grid-cols-2 gap-2 sm:flex sm:items-center" role="group" aria-label="Aksi Task">
                    @if($canApplyTemplate)
                        <button type="button" wire:click="openApplyTemplateModal" wire:loading.attr="disabled" wire:target="openApplyTemplateModal" aria-label="Gunakan Template" class="inline-flex h-9 shrink-0 items-center justify-center gap-1.5 whitespace-nowrap rounded-lg border border-gray-200 bg-white px-3 text-sm font-semibold text-gray-700 transition hover:border-gray-300 hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 disabled:opacity-60 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">
                            <x-heroicon-o-queue-list class="h-4 w-4 shrink-0 text-gray-500 dark:text-gray-400" />
                            <span class="sm:hidden">Template</span>
                            <span class="hidden sm:inline">Gunakan Template</span>
                        </button>
                    @endif
                    @if($canCreateTask)
                        <button type="button" wire:click="openTaskModal" wire:loading.attr="disabled" wire:target="openTaskModal" class="inline-flex h-9 shrink-0 items-center justify-center gap-1.5 whitespace-nowrap rounded-lg bg-blue-600 px-3 text-sm font-semibold text-white transition hover:bg-blue-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 disabled:opacity-60 dark:focus-visible:ring-offset-gray-900">
                            <x-heroicon-o-plus class="h-4 w-4 shrink-0" />
                            Tambah Task
                        </button>
                    @endif
                </div>
            @endif
        </div>

        <div class="flex items-center justify-end border-b border-gray-200 px-5 py-3 dark:border-gray-700">
            <div class="inline-flex h-9 items-stretch divide-x divide-gray-200 overflow-hidden rounded-lg border border-gray-200 bg-white dark:divide-gray-700 dark:border-gray-700 dark:bg-gray-900" role="group" aria-label="Navigasi bulan">
                <button type="button" wire:click="previousMonth" class="inline-flex w-9 items-center justify-center text-gray-600 transition hover:bg-gray-50 focus:outline-none focus-visible:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-800" aria-label="Bulan sebelumnya">
                    <x-heroicon-o-chevron-left class="h-4 w-4" />
                </button>
                <button type="button" wire:click="currentMonth" title="Kembali ke bulan ini" aria-live="polite" class="min-w-36 whitespace-nowrap px-3 text-sm font-semibold text-gray-800 transition hover:bg-gray-50 focus:outline-none focus-visible:bg-gray-50 dark:text-gray-100 dark:hover:bg-gray-800">{{ $monthLabel }}</button>
                <button type="button" wire:click="nextMonth" class="inline-flex w-9 items-center justify-center text-gray-600 transition hover:bg-gray-50 focus:outline-none focus-visible:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-800" aria-label="Bulan berikutnya">
                    <x-heroicon-o-chevron-right class="h-4 w-4" />
                </button>
            </div>
        </div>

        @if($this->isReadOnly())
            <div class="flex items-center gap-2 border-b border-amber-200 bg-amber-50 px-5 py-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200" role="status">
                <x-heroicon-o-lock-closed class="h-4 w-4 shrink-0" />
                Project sudah diarsipkan. Kalender hanya dapat dibaca; Task baru, template, copy, dan edit dinonaktifkan.
            </div>
        @endif

        <div class="relative">
            <div wire:loading.flex wire:target="previousMonth, nextMonth, currentMonth" class="absolute inset-0 z-10 items-center justify-center bg-white/70 dark:bg-gray-900/70" role="status">
                <span class="inline-flex items-center gap-2 text-sm font-semibold text-gray-600 dark:text-gray-300">
                    <x-filament::loading-indicator class="h-5 w-5" /> Memuat kalender...
                </span>
            </div>

            {{-- Desktop: month grid; each date lists its own Tasks so busy days grow instead of breaking the grid --}}
            <div class="hidden md:block">
                <div class="grid grid-cols-7 border-b border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800/60">
                    @foreach(['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] as $dayName)
                        <div class="border-r border-gray-200 px-3 py-2 text-center text-xs font-bold uppercase tracking-wide text-gray-500 last:border-r-0 dark:border-gray-700">{{ $dayName }}</div>
                    @endforeach
                </div>
                @foreach($weeks as $weekIndex => $week)
                    <div class="grid grid-cols-7 border-b border-gray-200 last:border-b-0 dark:border-gray-700" wire:key="project-calendar-week-{{ $calendarMonth }}-{{ $weekIndex }}">
                        @foreach($week as $day)
                            @php $dateKey = $day['date']->toDateString(); @endphp
                            <div
                                wire:key="project-calendar-day-{{ $dateKey }}"
                                x-data="{ expanded: false }"
                                @class([
                                    'flex min-h-28 min-w-0 flex-col gap-1 border-r border-gray-100 p-1.5 last:border-r-0 dark:border-gray-800',
                                    'bg-gray-50/70 dark:bg-gray-800/30' => ! $day['isCurrentMonth'],
                                ])
                            >
                                @if($canCreateTask)
                                    <button type="button" wire:click="openTaskModal('{{ $dateKey }}')" class="group flex items-center justify-end rounded-md px-1 py-0.5 text-xs hover:bg-blue-50/60 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 dark:hover:bg-blue-950/20" aria-label="Tambah Task pada {{ $day['date']->translatedFormat('d F Y') }}">
                                        <x-heroicon-o-plus class="mr-auto h-3.5 w-3.5 text-blue-500 opacity-0 group-hover:opacity-100 group-focus-visible:opacity-100" />
                                        <span @class(['inline-flex h-6 w-6 items-center justify-center rounded-full', 'bg-blue-600 font-bold text-white' => $day['isToday'], 'text-gray-700 dark:text-gray-200' => $day['isCurrentMonth'] && ! $day['isToday'], 'text-gray-300 dark:text-gray-600' => ! $day['isCurrentMonth']])>{{ $day['date']->day }}</span>
                                    </button>
                                @else
                                    <div class="flex justify-end px-1 py-0.5 text-xs">
                                        <span @class(['inline-flex h-6 w-6 items-center justify-center rounded-full', 'bg-blue-600 font-bold text-white' => $day['isToday'], 'text-gray-700 dark:text-gray-200' => $day['isCurrentMonth'] && ! $day['isToday'], 'text-gray-300 dark:text-gray-600' => ! $day['isCurrentMonth']])>{{ $day['date']->day }}</span>
                                    </div>
                                @endif
                                @if($day['isRelease'])
                                    <span class="flex items-center gap-1 truncate rounded-md border border-dashed border-indigo-300 bg-indigo-50 px-1.5 py-1 text-[11px] font-bold text-indigo-800 dark:border-indigo-800 dark:bg-indigo-950/40 dark:text-indigo-200" title="Tanggal rilis Project">
                                        <x-heroicon-o-flag class="h-3 w-3 shrink-0" /> Rilis
                                    </span>
                                @endif
                                @foreach($day['tasks'] as $taskIndex => $task)
                                    <x-rnd.task-chip
                                        :task="$task"
                                        wire:key="project-calendar-task-{{ $task->id }}"
                                        wire:click="openTaskDetail({{ $task->id }})"
                                        x-show="expanded || {{ $taskIndex < 3 ? 'true' : 'false' }}"
                                        class="w-full truncate rounded-md px-1.5 py-1 text-[11px] font-bold"
                                        title="{{ $task->title }} · {{ $task->status->getLabel() }} · Deadline {{ $task->due_date->format('d M Y') }}"
                                    >
                                        <span class="sr-only">{{ $task->status->getLabel() }}: </span>{{ $task->title }}
                                    </x-rnd.task-chip>
                                @endforeach
                                @if(count($day['tasks']) > 3)
                                    <button type="button" x-on:click="expanded = ! expanded" x-text="expanded ? 'Tampilkan lebih sedikit' : '+{{ count($day['tasks']) - 3 }} lainnya'" class="rounded-md px-1.5 py-0.5 text-left text-[11px] font-semibold text-blue-600 hover:underline dark:text-blue-400">+{{ count($day['tasks']) - 3 }} lainnya</button>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>

            {{-- Mobile: agenda list from the same dataset so nothing gets clipped in a 7-column grid --}}
            <div class="divide-y divide-gray-100 md:hidden dark:divide-gray-800">
                @forelse($agendaDays as $day)
                    <div class="p-4" wire:key="project-agenda-{{ $day['date']->toDateString() }}">
                        <p class="mb-2 text-xs font-bold uppercase tracking-wide text-gray-500">{{ $day['date']->translatedFormat('l, d F Y') }}</p>
                        <div class="space-y-2">
                            @if($day['isRelease'])
                                <p class="flex items-center gap-2 rounded-lg border border-dashed border-indigo-300 bg-indigo-50 px-3 py-2 text-sm font-bold text-indigo-800 dark:border-indigo-800 dark:bg-indigo-950/40 dark:text-indigo-200">
                                    <x-heroicon-o-flag class="h-4 w-4 shrink-0" /> Tanggal Rilis Project
                                </p>
                            @endif
                            @foreach($day['tasks'] as $task)
                                <x-rnd.task-chip :task="$task" wire:click="openTaskDetail({{ $task->id }})" class="block w-full rounded-lg px-3 py-2 text-sm">
                                    <span class="flex items-start justify-between gap-2">
                                        <span class="min-w-0 font-bold break-words">{{ $task->title }}</span>
                                        <span class="shrink-0 text-xs font-semibold">{{ $task->status->getLabel() }}</span>
                                    </span>
                                    <span class="mt-1 block truncate text-xs opacity-80">
                                        {{ $task->assignments->map(fn ($assignment) => $assignment->user?->display_username)->filter()->unique()->join(', ') ?: 'Belum ada PIC' }}
                                        · {{ $task->branches->pluck('name')->join(', ') }}
                                    </span>
                                </x-rnd.task-chip>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center">
                        <p class="text-sm font-semibold text-gray-600 dark:text-gray-300">Belum ada Task pada bulan ini.</p>
                        @if($canCreateTask || $canApplyTemplate)
                            <p class="mt-1 text-xs text-gray-400">Gunakan Tambah Task atau Gunakan Template untuk mulai merencanakan.</p>
                        @endif
                    </div>
                @endforelse
            </div>
        </div>
    </section>

    @include('filament.helpdesk.rnd-projects.partials.task-form-modal', ['lockedTaskProjectName' => $this->project->name])

    @include('filament.helpdesk.rnd-projects.partials.task-detail-modal')

    @include('filament.helpdesk.rnd-projects.partials.task-copy-modal')

    @include('filament.helpdesk.rnd-projects.partials.task-apply-template-modal')
</div>
