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
                    @if(filled($lockedTaskProjectName ?? null))
                        <div class="rounded-xl border border-gray-200 bg-gray-50 px-3.5 py-2.5 dark:border-gray-700 dark:bg-gray-800/60">
                            <p class="text-[10px] font-bold uppercase tracking-wide text-gray-400">Project</p>
                            <p class="mt-0.5 text-sm font-semibold text-gray-800 dark:text-gray-100">{{ $lockedTaskProjectName }}</p>
                        </div>
                    @else
                        <div>
                            <label for="task-project-id" class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Project *</label>
                            <select id="task-project-id" wire:model="taskProjectId" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                                <option value="">Pilih project...</option>
                                @foreach($this->taskFilterProjects() as $option)
                                    <option value="{{ $option->id }}">{{ $option->name }}</option>
                                @endforeach
                            </select>
                            @error('taskProjectId')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                    @endif
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
                    @include('filament.helpdesk.rnd-projects.partials.task-branch-rows', ['rowsProperty' => 'taskBranchRows', 'rows' => $taskBranchRows])
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
