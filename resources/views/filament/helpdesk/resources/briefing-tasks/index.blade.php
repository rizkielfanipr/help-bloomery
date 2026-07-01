<x-filament-panels::page>

{{-- ═══ BRANCH CARDS ═══ --}}
@if($this->selectedBranchId === null)

    <div class="space-y-6">

        <p class="text-sm text-gray-500 dark:text-gray-400">
            Pilih branch untuk melihat dan mengelola poin briefingnya.
        </p>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">

            {{-- Global card --}}
            <button
                wire:click="selectGlobal"
                class="group relative flex flex-col gap-3 rounded-2xl border border-gray-200 bg-white p-5 text-left shadow-sm transition-all duration-150 hover:border-primary-300 hover:shadow-md dark:border-white/10 dark:bg-gray-900 hover:dark:border-primary-700"
            >
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-violet-100 dark:bg-violet-500/10">
                        <x-heroicon-o-globe-alt class="h-5 w-5 text-violet-600 dark:text-violet-400" />
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">Global</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Semua Branch</p>
                    </div>
                </div>
                <div class="flex items-center justify-between">
                    <span class="inline-flex items-center rounded-full bg-violet-50 px-2.5 py-0.5 text-xs font-medium text-violet-700 ring-1 ring-inset ring-violet-600/20 dark:bg-violet-500/10 dark:text-violet-400 dark:ring-violet-500/20">
                        {{ $this->globalTaskCount }} poin
                    </span>
                    <x-heroicon-o-chevron-right class="h-4 w-4 text-gray-300 transition-transform group-hover:translate-x-0.5 dark:text-gray-600" />
                </div>
            </button>

            {{-- Branch cards --}}
            @foreach($this->branches as $branch)
                <button
                    wire:click="selectBranch({{ $branch->id }}, '{{ addslashes($branch->name) }}')"
                    class="group relative flex flex-col gap-3 rounded-2xl border border-gray-200 bg-white p-5 text-left shadow-sm transition-all duration-150 hover:border-primary-300 hover:shadow-md dark:border-white/10 dark:bg-gray-900 hover:dark:border-primary-700"
                >
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary-50 dark:bg-primary-500/10">
                            <x-heroicon-o-building-storefront class="h-5 w-5 text-primary-600 dark:text-primary-400" />
                        </div>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ $branch->name }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Branch</p>
                        </div>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="inline-flex items-center rounded-full bg-primary-50 px-2.5 py-0.5 text-xs font-medium text-primary-700 ring-1 ring-inset ring-primary-600/20 dark:bg-primary-500/10 dark:text-primary-400 dark:ring-primary-500/20">
                            {{ $branch->briefing_tasks_count }} poin
                        </span>
                        <x-heroicon-o-chevron-right class="h-4 w-4 text-gray-300 transition-transform group-hover:translate-x-0.5 dark:text-gray-600" />
                    </div>
                </button>
            @endforeach
        </div>
    </div>

{{-- ═══ TASK TABLE ═══ --}}
@else

    <div class="space-y-4">

        {{-- Header bar --}}
        <div class="flex items-center justify-between">
            <button
                wire:click="clearSelection"
                class="inline-flex items-center gap-1.5 text-sm font-medium text-gray-500 transition-colors hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200"
            >
                <x-heroicon-o-arrow-left class="h-4 w-4" />
                Semua Branch
            </button>

            <div class="flex items-center gap-2">
                <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">
                    {{ $this->selectedBranchName }}
                </span>

                <button
                    wire:click="toggleCopyPanel"
                    @class([
                        'inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium shadow-sm transition-colors',
                        'bg-amber-500 text-white hover:bg-amber-600' => $copyPanelOpen,
                        'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-white/10 dark:text-gray-300 dark:hover:bg-white/15' => ! $copyPanelOpen,
                    ])
                >
                    <x-heroicon-o-document-duplicate class="h-4 w-4" />
                    Salin Poin
                </button>

                <a
                    href="{{ $this->getCreateUrl() }}"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-3 py-1.5 text-sm font-medium text-white shadow-sm transition-colors hover:bg-primary-700"
                >
                    <x-heroicon-o-plus class="h-4 w-4" />
                    Tambah Poin
                </a>
            </div>
        </div>

        {{-- ─── Copy panel ─────────────────────────────────────────────── --}}
        @if($copyPanelOpen)
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-500/20 dark:bg-amber-500/5">
                <p class="mb-3 text-sm font-semibold text-amber-800 dark:text-amber-400">
                    Salin poin dari branch lain ke <span class="font-bold">{{ $this->selectedBranchName }}</span>
                </p>

                <div class="flex flex-wrap items-start gap-3">
                    {{-- Source select --}}
                    <div class="flex-1 min-w-48">
                        <select
                            wire:model.live="copySourceBranchId"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-white/10 dark:bg-gray-900 dark:text-gray-300"
                        >
                            <option value="">— Pilih sumber branch —</option>
                            @foreach($this->copySourceOptions as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Action buttons --}}
                    @if($copySourceBranchId !== null && $copySourceBranchId !== '')
                        <button
                            wire:click="copyFromBranch"
                            wire:loading.attr="disabled"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-amber-500 px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors hover:bg-amber-600 disabled:opacity-60"
                        >
                            <x-heroicon-o-document-duplicate class="h-4 w-4" />
                            <span wire:loading.remove wire:target="copyFromBranch">
                                Salin {{ $this->sourceTasks->count() }} Poin
                            </span>
                            <span wire:loading wire:target="copyFromBranch">Menyalin...</span>
                        </button>
                    @endif

                    <button
                        wire:click="toggleCopyPanel"
                        class="rounded-lg px-3 py-2 text-sm text-gray-500 transition-colors hover:bg-white hover:text-gray-700 dark:hover:bg-white/10 dark:hover:text-gray-300"
                    >
                        Batal
                    </button>
                </div>

                {{-- Preview tasks --}}
                @if($copySourceBranchId !== null && $copySourceBranchId !== '')
                    @if($this->sourceTasks->isEmpty())
                        <p class="mt-3 text-sm text-amber-700 dark:text-amber-400/70">Branch ini tidak memiliki poin.</p>
                    @else
                        <div class="mt-3 flex flex-wrap gap-1.5">
                            @foreach($this->sourceTasks as $t)
                                <span class="inline-flex items-center rounded-md bg-white px-2 py-0.5 text-xs text-gray-600 ring-1 ring-inset ring-gray-200 dark:bg-white/5 dark:text-gray-400 dark:ring-white/10">
                                    {{ $t->label }}
                                </span>
                            @endforeach
                        </div>
                    @endif
                @endif
            </div>
        @endif

        {{-- Task table --}}
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">

            @if($this->tasks->isEmpty())
                <div class="flex flex-col items-center justify-center gap-2 py-16 text-center">
                    <x-heroicon-o-clipboard-document-list class="h-10 w-10 text-gray-300 dark:text-gray-600" />
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Belum ada poin untuk branch ini</p>
                    <a
                        href="{{ $this->getCreateUrl() }}"
                        class="mt-1 text-sm font-medium text-primary-600 hover:underline dark:text-primary-400"
                    >Tambah poin pertama</a>
                </div>
            @else
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 dark:border-white/[0.06]">
                            <th class="w-8 px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">#</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Nama Poin</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Periode</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Jenis Input</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Deadline</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Aktif</th>
                            <th class="w-20 px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50 dark:divide-white/[0.04]">
                        @foreach($this->tasks as $task)
                            <tr class="group transition-colors hover:bg-gray-50/60 dark:hover:bg-white/[0.02]">
                                <td class="px-4 py-3 text-xs text-gray-400 dark:text-gray-600">{{ $task->sort_order }}</td>

                                <td class="px-4 py-3">
                                    <p class="font-medium text-gray-900 dark:text-white">{{ $task->label }}</p>
                                    @if($task->note_type)
                                        <p class="text-xs text-gray-400 dark:text-gray-500">{{ $task->note_type }}</p>
                                    @endif
                                    @if($task->group_label)
                                        <span class="mt-0.5 inline-block text-[11px] text-gray-400 dark:text-gray-600">{{ $task->group_label }}</span>
                                    @endif
                                </td>

                                <td class="px-4 py-3">
                                    @php
                                        $periodColor = match($task->period->value) {
                                            'daily'   => 'bg-blue-50 text-blue-700 ring-blue-600/20 dark:bg-blue-500/10 dark:text-blue-400 dark:ring-blue-500/20',
                                            'weekly'  => 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-400 dark:ring-amber-500/20',
                                            'monthly' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-400 dark:ring-emerald-500/20',
                                            default   => 'bg-gray-100 text-gray-700 ring-gray-600/20 dark:bg-gray-500/10 dark:text-gray-400',
                                        };
                                    @endphp
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset {{ $periodColor }}">
                                        {{ $task->period->getLabel() }}
                                    </span>
                                </td>

                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 ring-1 ring-inset ring-gray-500/20 dark:bg-white/5 dark:text-gray-400 dark:ring-white/10">
                                        {{ $task->submission_type->getLabel() }}
                                    </span>
                                </td>

                                <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">
                                    @if($task->deadline_enabled && $task->deadline_time)
                                        {{ substr($task->deadline_time, 0, 5) }}
                                    @else
                                        <span class="text-gray-300 dark:text-gray-600">—</span>
                                    @endif
                                </td>

                                <td class="px-4 py-3">
                                    @if($task->is_active)
                                        <span class="inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
                                    @else
                                        <span class="inline-flex h-2 w-2 rounded-full bg-gray-300 dark:bg-gray-600"></span>
                                    @endif
                                </td>

                                <td class="px-4 py-3">
                                    @if($pendingDeleteId === $task->id)
                                        <div class="flex items-center gap-1.5">
                                            <span class="text-xs text-red-500">Hapus?</span>
                                            <button
                                                wire:click="deleteTask({{ $task->id }})"
                                                class="rounded px-1.5 py-0.5 text-xs font-medium text-white bg-red-500 hover:bg-red-600"
                                            >Ya</button>
                                            <button
                                                wire:click="cancelDelete"
                                                class="rounded px-1.5 py-0.5 text-xs font-medium text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/10"
                                            >Batal</button>
                                        </div>
                                    @else
                                        <div class="flex items-center gap-1 opacity-0 transition-opacity group-hover:opacity-100">
                                            <a
                                                href="{{ $this->getEditUrl($task->id) }}"
                                                class="flex h-7 w-7 items-center justify-center rounded-lg text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-white/10 dark:hover:text-gray-300"
                                                title="Edit"
                                            >
                                                <x-heroicon-o-pencil-square class="h-4 w-4" />
                                            </a>
                                            <button
                                                wire:click="confirmDelete({{ $task->id }})"
                                                class="flex h-7 w-7 items-center justify-center rounded-lg text-gray-400 transition-colors hover:bg-red-50 hover:text-red-500 dark:hover:bg-red-500/10 dark:hover:text-red-400"
                                                title="Hapus"
                                            >
                                                <x-heroicon-o-trash class="h-4 w-4" />
                                            </button>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

@endif

</x-filament-panels::page>
