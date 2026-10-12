<tr draggable="true"
    x-on:dragstart="draggingId = {{ $task->id }}"
    x-on:dragend="draggingId = null; dragoverTarget = null"
    x-on:dragover.prevent="dragoverTarget = {{ $task->id }}"
    x-on:dragleave="dragoverTarget = null"
    x-on:drop.prevent="$wire.reorderTask(draggingId, {{ $task->id }}); draggingId = null; dragoverTarget = null"
    :class="{
        'opacity-40': draggingId === {{ $task->id }},
        'bg-primary-50/40 dark:bg-primary-500/10': dragoverTarget === {{ $task->id }} && draggingId !== {{ $task->id }}
    }"
    class="transition-colors hover:bg-primary-50/30 dark:hover:bg-primary-500/5">

    <td class="w-6 px-2 py-2.5 cursor-grab text-gray-300 dark:text-gray-600 active:cursor-grabbing">
        <x-heroicon-o-bars-3 class="h-4 w-4" />
    </td>

    <td class="px-4 py-2.5">
        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $task->label }}</p>
        @if($task->note_type)
            <p class="text-xs text-gray-400 dark:text-gray-500">{{ $task->note_type }}</p>
        @endif
    </td>

    <td class="hidden px-4 py-2.5 sm:table-cell">
        @php
            $typeBadgeClass = match($task->submission_type->value) {
                'camera_only' => 'bg-sky-50 text-sky-600 ring-sky-500/20 dark:bg-sky-500/10 dark:text-sky-400',
                'photo' => 'bg-indigo-50 text-indigo-600 ring-indigo-500/20 dark:bg-indigo-500/10 dark:text-indigo-400',
                'photo_and_text' => 'bg-amber-50 text-amber-600 ring-amber-500/20 dark:bg-amber-500/10 dark:text-amber-400',
                'text_only' => 'bg-emerald-50 text-emerald-600 ring-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-400',
                default => 'bg-gray-100 text-gray-600 ring-gray-500/20 dark:bg-white/5 dark:text-gray-400',
            };
        @endphp
        <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset dark:ring-white/10 {{ $typeBadgeClass }}">
            <x-dynamic-component :component="$task->submission_type->getIcon()" class="h-3 w-3" />
            {{ $task->submission_type->getLabel() }}
        </span>
    </td>

    <td class="hidden px-4 py-2.5 text-xs text-gray-500 dark:text-gray-400 md:table-cell">
        @if($task->deadline_enabled && $task->deadline_time)
            <span class="inline-flex items-center gap-1">
                <x-heroicon-o-clock class="h-3.5 w-3.5" />
                {{ substr($task->deadline_time, 0, 5) }}
            </span>
        @else
            <span class="text-gray-300 dark:text-gray-600">—</span>
        @endif
    </td>

    <td class="px-4 py-2.5">
        @if($task->is_active)
            <span class="inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
        @else
            <span class="inline-flex h-2 w-2 rounded-full bg-gray-300 dark:bg-gray-600"></span>
        @endif
    </td>

    <td class="w-20 px-4 py-2.5">
        @if($pendingDeleteId === $task->id)
            <div class="flex items-center gap-1.5">
                <span class="text-xs text-red-500">Hapus?</span>
                <button wire:click="deleteTask({{ $task->id }})"
                    class="rounded px-1.5 py-0.5 text-xs font-medium text-white bg-red-500 hover:bg-red-600">Ya</button>
                <button wire:click="cancelDelete"
                    class="rounded px-1.5 py-0.5 text-xs font-medium text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/10">Batal</button>
            </div>
        @else
            <div class="flex items-center gap-1">
                <button type="button" wire:click="startEditTask({{ $task->id }})"
                    class="flex h-7 w-7 items-center justify-center rounded-lg text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-white/10 dark:hover:text-gray-300"
                    title="Edit">
                    <x-heroicon-o-pencil-square class="h-4 w-4" />
                </button>
                <button wire:click="confirmDelete({{ $task->id }})"
                    class="flex h-7 w-7 items-center justify-center rounded-lg text-gray-400 transition-colors hover:bg-red-50 hover:text-red-500 dark:hover:bg-red-500/10 dark:hover:text-red-400"
                    title="Hapus">
                    <x-heroicon-o-trash class="h-4 w-4" />
                </button>
            </div>
        @endif
    </td>
</tr>
