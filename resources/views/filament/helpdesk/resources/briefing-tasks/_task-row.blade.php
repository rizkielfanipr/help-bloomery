<tr class="border-b border-gray-50 transition-colors last:border-0 hover:bg-primary-50/30 dark:border-white/[0.03] dark:hover:bg-primary-500/5">
    <td class="w-8 px-4 py-2.5 text-xs text-gray-400 dark:text-gray-600">{{ $task->sort_order }}</td>

    <td class="px-4 py-2.5">
        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $task->label }}</p>
        @if($task->note_type)
            <p class="text-xs text-gray-400 dark:text-gray-500">{{ $task->note_type }}</p>
        @endif
    </td>

    <td class="hidden px-4 py-2.5 sm:table-cell">
        <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 ring-1 ring-inset ring-gray-500/20 dark:bg-white/5 dark:text-gray-400 dark:ring-white/10">
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
                <a href="{{ $this->getEditUrl($task->id) }}"
                    class="flex h-7 w-7 items-center justify-center rounded-lg text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-white/10 dark:hover:text-gray-300"
                    title="Edit">
                    <x-heroicon-o-pencil-square class="h-4 w-4" />
                </a>
                <button wire:click="confirmDelete({{ $task->id }})"
                    class="flex h-7 w-7 items-center justify-center rounded-lg text-gray-400 transition-colors hover:bg-red-50 hover:text-red-500 dark:hover:bg-red-500/10 dark:hover:text-red-400"
                    title="Hapus">
                    <x-heroicon-o-trash class="h-4 w-4" />
                </button>
            </div>
        @endif
    </td>
</tr>
