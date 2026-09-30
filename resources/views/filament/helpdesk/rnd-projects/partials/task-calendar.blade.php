@php
    $taskCalendar = $this->taskCalendar();
    $canCreateTask = auth()->user()->can('create', \App\Models\RndProjectTask::class);
    $barClassFor = function (\App\Models\RndProjectTask $task): string {
        return match (true) {
            $task->isOverdue() => 'border-red-300 bg-red-100 text-red-800 dark:border-red-700 dark:bg-red-950/60 dark:text-red-200',
            ! $task->status->isTerminal() && today()->diffInDays($task->due_date, false) >= 0 && today()->diffInDays($task->due_date, false) <= 3
                => 'border-amber-300 bg-amber-100 text-amber-800 dark:border-amber-700 dark:bg-amber-950/60 dark:text-amber-200',
            $task->status->value === 'completed' => 'border-emerald-300 bg-emerald-100 text-emerald-800 dark:border-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-200',
            in_array($task->status->value, ['submitted', 'revision_required'], true)
                => 'border-purple-300 bg-purple-100 text-purple-800 dark:border-purple-700 dark:bg-purple-950/60 dark:text-purple-200',
            in_array($task->status->value, ['cancelled', 'draft'], true)
                => 'border-gray-300 bg-gray-100 text-gray-700 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300',
            default => 'border-blue-300 bg-blue-100 text-blue-800 dark:border-blue-700 dark:bg-blue-950/60 dark:text-blue-200',
        };
    };
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

<section class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
    <div class="flex flex-col gap-4 border-b border-gray-200 p-4 dark:border-gray-700 sm:flex-row sm:items-center sm:justify-between sm:px-5">
        <div>
            <div class="flex items-center gap-2">
                <x-heroicon-o-clipboard-document-check class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                <h3 class="text-lg font-bold text-gray-900 dark:text-white">Kalender Tugas</h3>
            </div>
            <p class="mt-1 text-sm text-gray-500">Tugas ditampilkan pada tanggal deadline-nya.</p>
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
                        @foreach($week['tasks'] as $segment)
                            @php
                                $task = $segment['task'];
                            @endphp
                            <button
                                type="button"
                                wire:click="openTaskDetail({{ $task->id }})"
                                class="mx-0.5 truncate rounded-md border px-2 py-1.5 text-left text-xs font-bold hover:brightness-95 {{ $barClassFor($task) }}"
                                style="grid-column: {{ $segment['dayColumn'] }} / span 1"
                                title="{{ $task->title }} · {{ $task->status->getLabel() }} · Deadline {{ $task->due_date->format('d M Y') }}"
                            >
                                {{ $task->title }}
                            </button>
                        @endforeach
                        @if(count($week['tasks']) === 0)
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
        @endphp
        @forelse($tasksByDate as $dateKey => $dateTasks)
            <div class="p-4">
                <p class="mb-2 text-xs font-bold uppercase tracking-wide text-gray-500">{{ \Illuminate\Support\Carbon::parse($dateKey)->translatedFormat('d F Y') }}</p>
                <div class="space-y-2">
                    @foreach($dateTasks as $task)
                        <button type="button" wire:click="openTaskDetail({{ $task->id }})" class="flex w-full items-center justify-between gap-2 rounded-lg border px-3 py-2 text-left text-sm {{ $barClassFor($task) }}">
                            <span class="min-w-0 truncate font-bold">{{ $task->title }}</span>
                            <span class="shrink-0 text-xs font-semibold">{{ $task->status->getLabel() }}</span>
                        </button>
                    @endforeach
                </div>
            </div>
        @empty
            <div class="p-8 text-center text-sm text-gray-400">Tidak ada Tugas pada bulan ini.</div>
        @endforelse
    </div>
</section>

@if($taskModalOpen)
    <div class="fixed inset-0 z-[130] flex items-center justify-center p-4" x-data x-trap.inert.noscroll="true" x-on:keydown.escape.window="$wire.closeTaskModal()">
        <button type="button" wire:click="closeTaskModal" class="absolute inset-0 bg-gray-950/60" aria-label="Tutup modal Tugas"></button>
        <form wire:submit="saveTask" class="relative flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900" role="dialog" aria-modal="true" aria-label="{{ $editingTaskId ? 'Edit Tugas' : 'Tambah Tugas' }}">
            <div class="flex items-start justify-between gap-4 border-b border-gray-200 p-5 dark:border-gray-700">
                <div>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ $editingTaskId ? 'Edit Tugas' : 'Tambah Tugas' }}</h3>
                    <p class="mt-1 text-sm text-gray-500">Informasi Pengisian: lengkapi detail Tugas{{ $editingTaskId ? '.' : ', Branch tujuan, dan PIC per Branch.' }}</p>
                </div>
                <button type="button" wire:click="closeTaskModal" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-200" aria-label="Tutup">
                    <x-heroicon-o-x-mark class="h-5 w-5" />
                </button>
            </div>
            <div class="space-y-4 overflow-y-auto p-5">
                @unless($editingTaskId)
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Project *</label>
                        <select wire:model="taskProjectId" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                            <option value="">Pilih project...</option>
                            @foreach($this->taskFilterProjects() as $option)
                                <option value="{{ $option->id }}">{{ $option->name }}</option>
                            @endforeach
                        </select>
                        @error('taskProjectId')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                @endunless
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Nama Tugas *</label>
                        <input wire:model="taskTitle" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white" placeholder="Contoh: Uji Rasa Batch Baru">
                        @error('taskTitle')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Kategori Tugas *</label>
                        <select wire:model="taskCategory" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                            <option value="">Pilih kategori...</option>
                            @foreach($this->taskCategoryOptions() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('taskCategory')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Prioritas *</label>
                        <select wire:model="taskPriority" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                            @foreach($this->taskPriorityOptions() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('taskPriority')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Tanggal Assign *</label>
                        <input type="date" wire:model="taskAssignedDate" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                        @error('taskAssignedDate')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Deadline *</label>
                        <input type="date" wire:model="taskDueDate" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                        @error('taskDueDate')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Catatan Task</label>
                        <textarea wire:model="taskDescription" rows="3" class="w-full resize-none rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white" placeholder="Instruksi pekerjaan..."></textarea>
                        @error('taskDescription')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Attachment Task</label>
                        @if($editingTaskId)
                            @if($existingTaskInstructionAttachments !== [])
                                <div class="mb-2 space-y-1.5">
                                    @foreach($existingTaskInstructionAttachments as $index => $attachmentPath)
                                        <div class="flex items-center justify-between gap-2 rounded-lg border border-gray-200 px-3 py-2 text-xs dark:border-gray-700">
                                            <a href="{{ route('helpdesk.rnd-project-tasks.attachments.show', ['path' => $attachmentPath]) }}" target="_blank" class="truncate text-blue-600 underline">Lampiran {{ $index + 1 }} · {{ basename($attachmentPath) }}</a>
                                            <button type="button" wire:click="removeInstructionAttachment({{ $editingTaskId }}, {{ $index }})" wire:confirm="Hapus lampiran ini?" aria-label="Hapus lampiran" class="shrink-0 text-red-500 hover:text-red-700">
                                                <x-heroicon-o-trash class="h-3.5 w-3.5" />
                                            </button>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        @endif
                        <input type="file" wire:model="taskInstructionAttachments" multiple class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                        <p class="mt-1 text-[11px] text-gray-400">Maks. 5 file, JPG/PNG/WEBP/PDF, masing-masing maks. 8 MB.</p>
                        @error('taskInstructionAttachments')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        @error('taskInstructionAttachments.*')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        <div wire:loading wire:target="taskInstructionAttachments" class="mt-1 text-[11px] text-blue-600">Mengunggah...</div>
                    </div>
                </div>

                @unless($editingTaskId)
                    <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                        <div class="mb-3 flex items-center justify-between">
                            <p class="text-sm font-bold text-gray-800 dark:text-gray-100">Branch &amp; PIC *</p>
                            <button type="button" wire:click="addTaskBranchRow" class="inline-flex items-center gap-1 rounded-lg border border-blue-200 bg-blue-50 px-2.5 py-1.5 text-xs font-bold text-blue-700 hover:bg-blue-100 dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-300">
                                <x-heroicon-o-plus class="h-3.5 w-3.5" /> Tambah Branch
                            </button>
                        </div>
                        @error('taskBranchRows')<p class="mb-2 text-xs text-red-600">{{ $message }}</p>@enderror
                        <div class="space-y-3">
                            @foreach($taskBranchRows as $index => $row)
                                <div wire:key="task-branch-row-{{ $index }}" class="grid gap-2 rounded-lg border border-gray-100 bg-gray-50/60 p-3 sm:grid-cols-[1fr_1fr_auto] dark:border-gray-800 dark:bg-gray-800/40">
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold text-gray-600 dark:text-gray-300">Branch</label>
                                        <select wire:model.live="taskBranchRows.{{ $index }}.branch_id" class="w-full rounded-lg border border-gray-300 bg-white px-2.5 py-2 text-xs dark:border-gray-600 dark:bg-gray-900 dark:text-white">
                                            <option value="">Pilih Branch...</option>
                                            @foreach($this->taskFilterBranches() as $option)
                                                <option value="{{ $option->id }}">{{ $option->name }}</option>
                                            @endforeach
                                        </select>
                                        @error("taskBranchRows.$index.branch_id")<p class="mt-1 text-[11px] text-red-600">{{ $message }}</p>@enderror
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold text-gray-600 dark:text-gray-300">PIC (bisa lebih dari satu)</label>
                                        <select multiple wire:model="taskBranchRows.{{ $index }}.user_ids" class="h-20 w-full rounded-lg border border-gray-300 bg-white px-2.5 py-2 text-xs dark:border-gray-600 dark:bg-gray-900 dark:text-white">
                                            @foreach($this->eligiblePicsForBranch($row['branch_id']) as $userId => $label)
                                                <option value="{{ $userId }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        @error("taskBranchRows.$index.user_ids")<p class="mt-1 text-[11px] text-red-600">{{ $message }}</p>@enderror
                                    </div>
                                    <div class="flex items-end">
                                        @if(count($taskBranchRows) > 1)
                                            <button type="button" wire:click="removeTaskBranchRow({{ $index }})" aria-label="Hapus Branch ini" class="rounded-lg border border-red-200 p-2 text-red-600 hover:bg-red-50 dark:border-red-900 dark:hover:bg-red-950/30">
                                                <x-heroicon-o-trash class="h-4 w-4" />
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endunless
            </div>
            <div class="flex justify-end gap-2 border-t border-gray-200 p-5 dark:border-gray-700">
                <button type="button" wire:click="closeTaskModal" class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-bold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800">Batal</button>
                <button type="submit" wire:loading.attr="disabled" wire:target="saveTask" class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-50">
                    <span wire:loading.remove wire:target="saveTask">{{ $editingTaskId ? 'Simpan Perubahan' : 'Bagikan Tugas' }}</span>
                    <span wire:loading wire:target="saveTask">Menyimpan...</span>
                </button>
            </div>
        </form>
    </div>
@endif

@if($viewingTaskId)
    @php
        $detailTask = $this->viewingTask();
    @endphp
    @if($detailTask)
        <div class="fixed inset-0 z-[130] flex items-center justify-center p-4" x-data x-trap.inert.noscroll="true" x-on:keydown.escape.window="$wire.closeTaskDetail()">
            <button type="button" wire:click="closeTaskDetail" class="absolute inset-0 bg-gray-950/60" aria-label="Tutup detail Tugas"></button>
            <x-rnd.picker-modal title="{{ $detailTask->title }}" :description="$detailTask->project->name" max-width="4xl">
                <x-slot name="close">
                    <button type="button" wire:click="closeTaskDetail" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-200" aria-label="Tutup">
                        <x-heroicon-o-x-mark class="h-5 w-5" />
                    </button>
                </x-slot>
                <div class="space-y-4 overflow-y-auto p-5 text-sm">
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-700">
                            <p class="text-[10px] font-bold uppercase tracking-wide text-gray-400">Status</p>
                            <p class="mt-1"><x-filament::badge :color="$detailTask->status->getColor()">{{ $detailTask->status->getLabel() }}</x-filament::badge></p>
                        </div>
                        <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-700">
                            <p class="text-[10px] font-bold uppercase tracking-wide text-gray-400">Prioritas</p>
                            <p class="mt-1"><x-filament::badge :color="$detailTask->priority->getColor()">{{ $detailTask->priority->getLabel() }}</x-filament::badge></p>
                        </div>
                        <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-700">
                            <p class="text-[10px] font-bold uppercase tracking-wide text-gray-400">Deadline</p>
                            <p class="mt-1 font-semibold text-gray-800 dark:text-gray-100">{{ $detailTask->due_date->format('d M Y') }}{{ $detailTask->isOverdue() ? ' · Overdue' : '' }}</p>
                        </div>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-white p-3.5 dark:border-gray-700 dark:bg-gray-900">
                        <p class="text-[10px] font-bold uppercase tracking-wide text-gray-400">Catatan Task</p>
                        <p class="mt-1.5 leading-6 text-gray-700 dark:text-gray-200">{{ $detailTask->description ?: 'Tidak ada catatan.' }}</p>
                    </div>

                    <div>
                        <p class="mb-2 text-xs font-bold uppercase tracking-wide text-gray-500">PIC per Branch</p>
                        <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
                            <table class="min-w-full divide-y divide-gray-200 text-xs dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-800/50">
                                    <tr class="text-left font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                        <th class="px-3 py-2">Branch</th>
                                        <th class="px-3 py-2">PIC</th>
                                        <th class="px-3 py-2">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                    @foreach($detailTask->assignments as $assignment)
                                        <tr>
                                            <td class="px-3 py-2 font-semibold text-gray-800 dark:text-gray-100">{{ $assignment->branch->name }}</td>
                                            <td class="px-3 py-2">{{ $assignment->user?->display_username ?? 'Tidak diketahui' }}</td>
                                            <td class="px-3 py-2"><x-filament::badge :color="$assignment->status->getColor()">{{ $assignment->status->getLabel() }}</x-filament::badge></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    @foreach($detailTask->assignments->where('status', \App\Enums\RndProjectTaskAssignmentStatus::Submitted) as $reviewAssignment)
                        @can('review', $reviewAssignment)
                            <div class="rounded-xl border border-purple-200 bg-purple-50/40 p-3.5 dark:border-purple-900 dark:bg-purple-950/10">
                                <div class="mb-2 flex items-center justify-between">
                                    <p class="text-xs font-bold uppercase tracking-wide text-purple-700 dark:text-purple-300">Review · {{ $reviewAssignment->branch->name }} · {{ $reviewAssignment->user?->display_username ?? 'Tidak diketahui' }}</p>
                                </div>
                                @php
                                    $latestSubmission = $reviewAssignment->followUps->firstWhere('follow_up_type', \App\Enums\RndProjectTaskFollowUpType::Submission);
                                @endphp
                                @if($latestSubmission)
                                    <div class="mb-2 rounded-lg bg-white p-2.5 text-xs dark:bg-gray-900">
                                        <p class="text-gray-700 dark:text-gray-200">{{ $latestSubmission->notes ?: 'Tidak ada catatan dari PIC.' }}</p>
                                        @if($latestSubmission->result_attachments)
                                            <div class="mt-1.5 flex flex-wrap gap-2">
                                                @foreach($latestSubmission->result_attachments as $attachmentPath)
                                                    <a href="{{ route('helpdesk.rnd-project-tasks.attachments.show', ['path' => $attachmentPath]) }}" target="_blank" class="text-blue-600 underline">Lampiran {{ $loop->iteration }}</a>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @endif
                                <textarea wire:model="reviewNote" rows="2" class="w-full resize-none rounded-lg border border-gray-300 bg-white px-2.5 py-2 text-xs dark:border-gray-600 dark:bg-gray-900 dark:text-white" placeholder="Catatan review (wajib jika meminta revisi)..."></textarea>
                                @error('reviewNote')<p class="mt-1 text-[11px] text-red-600">{{ $message }}</p>@enderror
                                <div class="mt-2 flex justify-end gap-2">
                                    <button type="button" wire:click="requestRevision({{ $reviewAssignment->id }})" class="rounded-lg border border-amber-300 px-3 py-2 text-xs font-bold text-amber-700 hover:bg-amber-50 dark:border-amber-800 dark:text-amber-300">Minta Revisi</button>
                                    <button type="button" wire:click="approveFollowUp({{ $reviewAssignment->id }})" class="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-bold text-white hover:bg-emerald-700">Approve</button>
                                </div>
                            </div>
                        @endcan
                    @endforeach

                    @foreach($this->myAssignmentsForTask($detailTask) as $myAssignment)
                        <div class="rounded-xl border border-blue-200 bg-blue-50/40 p-3.5 dark:border-blue-900 dark:bg-blue-950/10">
                            <div class="mb-3 flex items-center justify-between">
                                <p class="text-xs font-bold uppercase tracking-wide text-blue-700 dark:text-blue-300">Tindak Lanjut Saya · {{ $myAssignment->branch->name }}</p>
                                <x-filament::badge :color="$myAssignment->status->getColor()">{{ $myAssignment->status->getLabel() }}</x-filament::badge>
                            </div>

                            @if($myAssignment->status->value === 'assigned')
                                <button type="button" wire:click="startAssignment({{ $myAssignment->id }})" class="rounded-lg bg-blue-600 px-3 py-2 text-xs font-bold text-white hover:bg-blue-700">Mulai Mengerjakan</button>
                            @elseif(in_array($myAssignment->status->value, ['in_progress', 'revision_required'], true))
                                <div class="space-y-2">
                                    @if($myAssignment->status->value === 'revision_required' && $myAssignment->review_note)
                                        <p class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200"><strong>Catatan Revisi:</strong> {{ $myAssignment->review_note }}</p>
                                    @endif
                                    <textarea wire:model="followUpNotes" rows="2" class="w-full resize-none rounded-lg border border-gray-300 bg-white px-2.5 py-2 text-xs dark:border-gray-600 dark:bg-gray-900 dark:text-white" placeholder="Catatan progress / kendala..."></textarea>
                                    @error('followUpNotes')<p class="text-[11px] text-red-600">{{ $message }}</p>@enderror
                                    <div class="grid gap-2 sm:grid-cols-2">
                                        <div>
                                            <label class="mb-1 block text-[11px] font-semibold text-gray-600 dark:text-gray-300">Perkiraan Selesai</label>
                                            <input type="date" wire:model="followUpEstimatedDate" class="w-full rounded-lg border border-gray-300 bg-white px-2.5 py-2 text-xs dark:border-gray-600 dark:bg-gray-900 dark:text-white">
                                            @error('followUpEstimatedDate')<p class="text-[11px] text-red-600">{{ $message }}</p>@enderror
                                        </div>
                                        <div>
                                            <label class="mb-1 block text-[11px] font-semibold text-gray-600 dark:text-gray-300">Attachment Hasil</label>
                                            <input type="file" wire:model="followUpAttachments" multiple class="w-full rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-xs dark:border-gray-600 dark:bg-gray-900 dark:text-white">
                                            @error('followUpAttachments.*')<p class="text-[11px] text-red-600">{{ $message }}</p>@enderror
                                            <div wire:loading wire:target="followUpAttachments" class="mt-1 text-[11px] text-blue-600">Mengunggah...</div>
                                        </div>
                                    </div>
                                    <div class="flex justify-end gap-2 pt-1">
                                        <button type="button" wire:click="saveFollowUp({{ $myAssignment->id }}, 'progress')" wire:loading.attr="disabled" wire:target="saveFollowUp" class="rounded-lg border border-gray-300 px-3 py-2 text-xs font-bold text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:text-gray-200">Simpan Progress</button>
                                        <button type="button" wire:click="saveFollowUp({{ $myAssignment->id }}, 'submission')" wire:loading.attr="disabled" wire:target="saveFollowUp" class="rounded-lg bg-blue-600 px-3 py-2 text-xs font-bold text-white hover:bg-blue-700 disabled:opacity-50">Kirim Tindak Lanjut</button>
                                    </div>
                                </div>
                            @elseif($myAssignment->status->value === 'submitted')
                                <p class="text-xs text-gray-500">Tindak lanjut sudah dikirim, menunggu review.</p>
                            @elseif($myAssignment->status->value === 'approved')
                                <p class="text-xs text-emerald-700 dark:text-emerald-300">Hasil sudah disetujui.</p>
                            @endif

                            @if($myAssignment->followUps->isNotEmpty())
                                <div class="mt-3 space-y-2 border-t border-blue-100 pt-3 dark:border-blue-900">
                                    <p class="text-[10px] font-bold uppercase tracking-wide text-gray-400">Riwayat Tindak Lanjut</p>
                                    @foreach($myAssignment->followUps as $followUp)
                                        <div class="rounded-lg bg-white p-2.5 text-xs dark:bg-gray-900">
                                            <div class="flex items-center justify-between">
                                                <span class="font-semibold text-gray-800 dark:text-gray-100">{{ $followUp->follow_up_type->getLabel() }}</span>
                                                <span class="text-[11px] text-gray-400">{{ $followUp->created_at->format('d M Y H:i') }}</span>
                                            </div>
                                            @if($followUp->notes)<p class="mt-1 text-gray-600 dark:text-gray-300">{{ $followUp->notes }}</p>@endif
                                            @if($followUp->result_attachments)
                                                <div class="mt-1 flex flex-wrap gap-2">
                                                    @foreach($followUp->result_attachments as $attachmentPath)
                                                        <a href="{{ route('helpdesk.rnd-project-tasks.attachments.show', ['path' => $attachmentPath]) }}" target="_blank" class="text-blue-600 underline">Lampiran {{ $loop->iteration }}</a>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach

                    @can('assign', $detailTask)
                        <div class="rounded-xl border border-dashed border-gray-300 p-3.5 dark:border-gray-700">
                            <p class="mb-2 text-xs font-bold uppercase tracking-wide text-gray-500">Tambah / Alihkan PIC</p>
                            <div class="grid gap-2 sm:grid-cols-[1fr_1fr_auto]">
                                <select wire:model="assignBranchId" class="rounded-lg border border-gray-300 bg-white px-2.5 py-2 text-xs dark:border-gray-600 dark:bg-gray-900 dark:text-white">
                                    <option value="">Pilih Branch...</option>
                                    @foreach($detailTask->branches as $branch)
                                        <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                    @endforeach
                                </select>
                                <select wire:model="assignUserId" class="rounded-lg border border-gray-300 bg-white px-2.5 py-2 text-xs dark:border-gray-600 dark:bg-gray-900 dark:text-white">
                                    <option value="">Pilih PIC...</option>
                                    @foreach($this->eligiblePicsForBranch($assignBranchId) as $userId => $label)
                                        <option value="{{ $userId }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                <button type="button" wire:click="assignTaskPic" class="rounded-lg bg-blue-600 px-3 py-2 text-xs font-bold text-white hover:bg-blue-700">Simpan</button>
                            </div>
                            @error('assignBranchId')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            @error('assignUserId')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                    @endcan
                </div>
                <div class="flex justify-end gap-2 border-t border-gray-200 p-5 dark:border-gray-700">
                    @can('cancel', $detailTask)
                        <button type="button" x-on:click="window.BloomeryConfirm.show({ title: 'Batalkan Tugas?', text: 'Tugas ini akan dibatalkan dan seluruh assignment aktif ikut dibatalkan. Histori tetap tersimpan.', confirmText: 'Batalkan Tugas' }).then((confirmed) => { if (confirmed) $wire.cancelTask({{ $detailTask->id }}) })" class="rounded-lg border border-red-200 px-4 py-2.5 text-sm font-bold text-red-700 hover:bg-red-50 dark:border-red-900 dark:text-red-300 dark:hover:bg-red-950/30">Batalkan Tugas</button>
                    @endcan
                    @can('update', $detailTask)
                        <button type="button" wire:click="openEditTaskModal({{ $detailTask->id }})" class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-blue-700">Edit Tugas</button>
                    @endcan
                </div>
            </x-rnd.picker-modal>
        </div>
    @endif
@endif
