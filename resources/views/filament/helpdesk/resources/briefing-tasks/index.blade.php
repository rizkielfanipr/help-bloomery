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

{{-- ═══ TASK TABLE (grouped) ═══ --}}
@else

    @php
        $grouped = $this->tasks->groupBy(fn ($t) => $t->group ?? '');
        // Sort: named groups first (alphabetical), ungrouped last
        $grouped = $grouped->sortKeysUsing(fn ($a, $b) => match(true) {
            $a === '' && $b !== '' => 1,
            $a !== '' && $b === '' => -1,
            default => strcmp($a, $b),
        });
    @endphp

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

                    @if($copySourceBranchId !== null && $copySourceBranchId !== '')
                        @php $existingCount = $this->tasks->count(); @endphp
                        <button
                            wire:click="copyFromBranch"
                            wire:loading.attr="disabled"
                            @class([
                                'inline-flex items-center gap-1.5 rounded-lg px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors disabled:opacity-60',
                                'bg-red-500 hover:bg-red-600' => $existingCount > 0,
                                'bg-amber-500 hover:bg-amber-600' => $existingCount === 0,
                            ])
                        >
                            <x-heroicon-o-arrow-path class="h-4 w-4" />
                            <span wire:loading.remove wire:target="copyFromBranch">
                                @if($existingCount > 0)
                                    Ganti {{ $existingCount }} Poin dengan {{ $this->sourceTasks->count() }} Poin Baru
                                @else
                                    Salin {{ $this->sourceTasks->count() }} Poin
                                @endif
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

                @if($copySourceBranchId !== null && $copySourceBranchId !== '')
                    @if($this->sourceTasks->isEmpty())
                        <p class="mt-3 text-sm text-amber-700 dark:text-amber-400/70">Branch ini tidak memiliki poin.</p>
                    @else
                        @if($existingCount > 0)
                            <p class="mt-3 flex items-center gap-1.5 text-sm font-medium text-red-600 dark:text-red-400">
                                <x-heroicon-o-exclamation-triangle class="h-4 w-4 shrink-0" />
                                {{ $existingCount }} poin yang ada di <strong>{{ $this->selectedBranchName }}</strong> akan dihapus semua dan diganti.
                            </p>
                        @endif
                        <div class="mt-2 flex flex-wrap gap-1.5">
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

        {{-- ─── Grouped task sections ─────────────────────────────────── --}}
        @if($this->tasks->isEmpty())
            <div class="flex flex-col items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white py-16 text-center shadow-sm dark:border-white/10 dark:bg-gray-900">
                <x-heroicon-o-clipboard-document-list class="h-10 w-10 text-gray-300 dark:text-gray-600" />
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Belum ada poin untuk branch ini</p>
                <a
                    href="{{ $this->getCreateUrl() }}"
                    class="mt-1 text-sm font-medium text-primary-600 hover:underline dark:text-primary-400"
                >Tambah poin pertama</a>
            </div>
        @else
            <div class="space-y-3">
                @foreach($grouped as $groupKey => $tasks)
                    @php
                        $firstTask  = $tasks->first();
                        $groupLabel = $firstTask->group_label ?: ($groupKey !== '' ? $groupKey : null);
                        $isUngrouped = $groupKey === '';
                        $sectionId  = 'group_' . ($groupKey ?: 'ungrouped');

                        // Color palette per group index
                        $palette = [
                            ['header' => 'bg-indigo-50 border-indigo-200 dark:bg-indigo-950/30 dark:border-indigo-800', 'icon' => 'text-indigo-500', 'badge' => 'bg-indigo-100 text-indigo-700 ring-indigo-600/20 dark:bg-indigo-500/10 dark:text-indigo-400 dark:ring-indigo-500/20', 'add' => 'text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300'],
                            ['header' => 'bg-emerald-50 border-emerald-200 dark:bg-emerald-950/30 dark:border-emerald-800', 'icon' => 'text-emerald-500', 'badge' => 'bg-emerald-100 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-400 dark:ring-emerald-500/20', 'add' => 'text-emerald-600 hover:text-emerald-800 dark:text-emerald-400 dark:hover:text-emerald-300'],
                            ['header' => 'bg-amber-50 border-amber-200 dark:bg-amber-950/30 dark:border-amber-800', 'icon' => 'text-amber-500', 'badge' => 'bg-amber-100 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-400 dark:ring-amber-500/20', 'add' => 'text-amber-600 hover:text-amber-800 dark:text-amber-400 dark:hover:text-amber-300'],
                            ['header' => 'bg-sky-50 border-sky-200 dark:bg-sky-950/30 dark:border-sky-800', 'icon' => 'text-sky-500', 'badge' => 'bg-sky-100 text-sky-700 ring-sky-600/20 dark:bg-sky-500/10 dark:text-sky-400 dark:ring-sky-500/20', 'add' => 'text-sky-600 hover:text-sky-800 dark:text-sky-400 dark:hover:text-sky-300'],
                            ['header' => 'bg-rose-50 border-rose-200 dark:bg-rose-950/30 dark:border-rose-800', 'icon' => 'text-rose-500', 'badge' => 'bg-rose-100 text-rose-700 ring-rose-600/20 dark:bg-rose-500/10 dark:text-rose-400 dark:ring-rose-500/20', 'add' => 'text-rose-600 hover:text-rose-800 dark:text-rose-400 dark:hover:text-rose-300'],
                        ];
                        $colors = $isUngrouped
                            ? ['header' => 'bg-gray-50 border-gray-200 dark:bg-gray-900/40 dark:border-white/10', 'icon' => 'text-gray-400', 'badge' => 'bg-gray-100 text-gray-600 ring-gray-500/20 dark:bg-white/5 dark:text-gray-400 dark:ring-white/10', 'add' => 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300']
                            : $palette[$loop->index % count($palette)];
                    @endphp

                    <div
                        x-data="{ open: true }"
                        class="overflow-hidden rounded-xl border shadow-sm dark:bg-gray-900"
                        :class="open ? 'border-gray-200 dark:border-white/10' : ''"
                    >
                        {{-- Group header --}}
                        <div
                            class="flex cursor-pointer items-center gap-3 border-b px-4 py-3 {{ $colors['header'] }} select-none"
                            :class="open ? '' : 'border-transparent rounded-xl'"
                            @click="open = !open"
                        >
                            {{-- Toggle icon --}}
                            <x-heroicon-o-folder-open class="h-4 w-4 shrink-0 {{ $colors['icon'] }}" x-show="open" />
                            <x-heroicon-o-folder class="h-4 w-4 shrink-0 {{ $colors['icon'] }}" x-show="!open" />

                            {{-- Group name --}}
                            <span class="flex-1 text-sm font-semibold text-gray-800 dark:text-gray-100">
                                @if($isUngrouped)
                                    <span class="italic text-gray-500 dark:text-gray-400">Tanpa Grup</span>
                                @else
                                    {{ $groupLabel }}
                                @endif
                            </span>

                            {{-- Count badge --}}
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset {{ $colors['badge'] }}">
                                {{ $tasks->count() }} poin
                            </span>

                            {{-- Add to group button --}}
                            @if(! $isUngrouped)
                                <button
                                    wire:click.stop="startQuickAdd('{{ $groupKey }}', '{{ addslashes($groupLabel) }}')"
                                    @class([
                                        'inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium transition-colors hover:bg-white/60 dark:hover:bg-white/5',
                                        $colors['add'],
                                        'opacity-50' => $addingToGroup !== null && $addingToGroup !== $groupKey,
                                    ])
                                    title="Tambah poin ke grup ini"
                                >
                                    <x-heroicon-o-plus class="h-3.5 w-3.5" />
                                    Tambah
                                </button>
                            @endif

                            {{-- Chevron --}}
                            <x-heroicon-o-chevron-down
                                class="h-4 w-4 shrink-0 text-gray-400 transition-transform duration-200 dark:text-gray-500"
                                ::class="open ? 'rotate-0' : '-rotate-90'"
                            />
                        </div>

                        {{-- Task rows --}}
                        <div x-show="open" x-collapse>
                            <table class="w-full text-sm">
                                <tbody class="divide-y divide-gray-50 dark:divide-white/[0.04]">
                                    @foreach($tasks as $task)
                                        <tr class="transition-colors hover:bg-gray-50/60 dark:hover:bg-white/[0.02]">
                                            <td class="w-8 px-4 py-3 text-xs text-gray-400 dark:text-gray-600">{{ $task->sort_order }}</td>

                                            <td class="px-4 py-3">
                                                <p class="font-medium text-gray-900 dark:text-white">{{ $task->label }}</p>
                                                @if($task->note_type)
                                                    <p class="text-xs text-gray-400 dark:text-gray-500">{{ $task->note_type }}</p>
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

                                            <td class="hidden px-4 py-3 sm:table-cell">
                                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 ring-1 ring-inset ring-gray-500/20 dark:bg-white/5 dark:text-gray-400 dark:ring-white/10">
                                                    {{ $task->submission_type->getLabel() }}
                                                </span>
                                            </td>

                                            <td class="hidden px-4 py-3 text-xs text-gray-500 dark:text-gray-400 md:table-cell">
                                                @if($task->deadline_enabled && $task->deadline_time)
                                                    <span class="inline-flex items-center gap-1">
                                                        <x-heroicon-o-clock class="h-3.5 w-3.5" />
                                                        {{ substr($task->deadline_time, 0, 5) }}
                                                    </span>
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

                                            <td class="w-20 px-4 py-3">
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
                                                    <div class="flex items-center gap-1">
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
                                    {{-- ── Inline quick-add row ── --}}
                                    @if($addingToGroup === $groupKey)
                                        <tr class="bg-primary-50/40 dark:bg-primary-900/10">
                                            <td class="px-4 py-2.5" colspan="7">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <input
                                                        wire:model="quickAdd.label"
                                                        wire:keydown.enter="saveQuickTask"
                                                        wire:keydown.escape="cancelQuickAdd"
                                                        type="text"
                                                        placeholder="Nama poin baru…"
                                                        autofocus
                                                        class="flex-1 min-w-40 rounded-lg border border-primary-300 bg-white px-3 py-1.5 text-sm text-gray-800 placeholder-gray-400 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-primary-700 dark:bg-gray-900 dark:text-gray-200 dark:placeholder-gray-500"
                                                    />

                                                    <select
                                                        wire:model="quickAdd.period"
                                                        class="rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-sm text-gray-700 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-white/10 dark:bg-gray-900 dark:text-gray-300"
                                                    >
                                                        <option value="daily">Harian</option>
                                                        <option value="weekly">Mingguan</option>
                                                        <option value="monthly">Bulanan</option>
                                                    </select>

                                                    <select
                                                        wire:model="quickAdd.submission_type"
                                                        class="rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-sm text-gray-700 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-white/10 dark:bg-gray-900 dark:text-gray-300"
                                                    >
                                                        <option value="checkbox">Centang Selesai</option>
                                                        <option value="photo">Foto Bebas</option>
                                                        <option value="camera_only">Foto Kamera</option>
                                                        <option value="photo_and_text">Foto + Teks</option>
                                                        <option value="text_only">Teks Saja</option>
                                                    </select>

                                                    <button
                                                        wire:click="saveQuickTask"
                                                        wire:loading.attr="disabled"
                                                        class="inline-flex items-center gap-1 rounded-lg bg-primary-600 px-3 py-1.5 text-sm font-medium text-white shadow-sm transition-colors hover:bg-primary-700 disabled:opacity-60"
                                                    >
                                                        <span wire:loading.remove wire:target="saveQuickTask">Simpan</span>
                                                        <span wire:loading wire:target="saveQuickTask">…</span>
                                                    </button>

                                                    <button
                                                        wire:click="cancelQuickAdd"
                                                        class="rounded-lg px-2.5 py-1.5 text-sm text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-white/10 dark:hover:text-gray-300"
                                                    >
                                                        Batal
                                                    </button>
                                                </div>
                                                @error('quickAdd.label')
                                                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                                @enderror
                                            </td>
                                        </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

    </div>

@endif

</x-filament-panels::page>
