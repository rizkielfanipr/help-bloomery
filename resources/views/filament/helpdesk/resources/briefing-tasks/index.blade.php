<x-filament-panels::page>

{{-- ═══ BRANCH CARDS ═══ --}}
@if($this->selectedBranchId === null)

    <div class="space-y-4">
        <p class="text-sm text-gray-400 dark:text-gray-500">
            Pilih branch untuk melihat dan mengelola poin briefingnya.
        </p>
        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
            <button wire:click="selectGlobal"
                class="group flex items-center gap-3 rounded-xl bg-gray-50 px-4 py-3.5 text-left transition-colors hover:bg-gray-100 dark:bg-white/[0.04] dark:hover:bg-white/[0.07]">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white dark:bg-white/[0.06]">
                    <x-heroicon-o-globe-alt class="h-4 w-4 text-gray-400 dark:text-gray-500" />
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-gray-800 dark:text-white">Global</p>
                    <p class="text-xs text-gray-400 dark:text-gray-500">{{ $this->globalTaskCount }} poin · Semua Branch</p>
                </div>
                <x-heroicon-o-chevron-right class="h-3.5 w-3.5 shrink-0 text-gray-300 transition-transform group-hover:translate-x-0.5 dark:text-gray-600" />
            </button>

            @foreach($this->branches as $branch)
                <button wire:click="selectBranch({{ $branch->id }}, '{{ addslashes($branch->name) }}')"
                    class="group flex items-center gap-3 rounded-xl bg-gray-50 px-4 py-3.5 text-left transition-colors hover:bg-gray-100 dark:bg-white/[0.04] dark:hover:bg-white/[0.07]">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white dark:bg-white/[0.06]">
                        <x-heroicon-o-building-storefront class="h-4 w-4 text-gray-400 dark:text-gray-500" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold text-gray-800 dark:text-white">{{ $branch->name }}</p>
                        <p class="text-xs text-gray-400 dark:text-gray-500">{{ $branch->briefing_tasks_count }} poin · Branch</p>
                    </div>
                    <x-heroicon-o-chevron-right class="h-3.5 w-3.5 shrink-0 text-gray-300 transition-transform group-hover:translate-x-0.5 dark:text-gray-600" />
                </button>
            @endforeach
        </div>
    </div>

{{-- ═══ TASK VIEW (period → group) ═══ --}}
@else

    @php
        $periods = [
            ['value' => 'daily',   'label' => 'Harian',   'dot' => 'bg-blue-400'],
            ['value' => 'weekly',  'label' => 'Mingguan', 'dot' => 'bg-amber-400'],
            ['value' => 'monthly', 'label' => 'Bulanan',  'dot' => 'bg-emerald-400'],
        ];
        // badge for quick-add row (reused in partial)
        $periodBadge = [
            'daily'   => 'bg-blue-50 text-blue-600 ring-blue-200 dark:bg-blue-500/10 dark:text-blue-400 dark:ring-blue-500/20',
            'weekly'  => 'bg-amber-50 text-amber-600 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-400 dark:ring-amber-500/20',
            'monthly' => 'bg-emerald-50 text-emerald-600 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:ring-emerald-500/20',
        ];
    @endphp

    <div class="space-y-3">

        {{-- ── Top bar ──────────────────────────────────────────────────── --}}
        <div class="flex items-center justify-between">
            <button wire:click="clearSelection"
                class="inline-flex items-center gap-1.5 text-sm text-gray-500 transition-colors hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                <x-heroicon-o-arrow-left class="h-4 w-4" />
                Semua Branch
            </button>
            <div class="flex items-center gap-2">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ $this->selectedBranchName }}</span>
                <button wire:click="toggleCopyPanel"
                    @class(['inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-sm font-medium transition-colors',
                        'border-amber-300 bg-amber-50 text-amber-700 hover:bg-amber-100 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-400' => $copyPanelOpen,
                        'border-gray-200 bg-white text-gray-600 hover:border-gray-300 hover:text-gray-800 dark:border-white/[0.08] dark:bg-white/[0.03] dark:text-gray-400 dark:hover:border-white/20' => ! $copyPanelOpen])>
                    <x-heroicon-o-document-duplicate class="h-4 w-4" />
                    Salin Poin
                </button>
            </div>
        </div>

        {{-- ── Copy panel ───────────────────────────────────────────────── --}}
        @if($copyPanelOpen)
            <div class="rounded-xl border border-gray-100 bg-white p-4 dark:border-white/[0.06] dark:bg-gray-900">
                <p class="mb-3 text-sm text-gray-600 dark:text-gray-400">
                    Salin poin dari branch lain ke <span class="font-medium text-gray-800 dark:text-gray-200">{{ $this->selectedBranchName }}</span>
                </p>
                <div class="flex flex-wrap items-start gap-2">
                    <div class="flex-1 min-w-48">
                        <select wire:model.live="copySourceBranchId"
                            class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-primary-400 focus:outline-none focus:ring-1 focus:ring-primary-400 dark:border-white/[0.08] dark:bg-gray-900 dark:text-gray-300">
                            <option value="">— Pilih sumber —</option>
                            @foreach($this->copySourceOptions as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if($copySourceBranchId !== null && $copySourceBranchId !== '')
                        @php $existingCount = $this->tasks->count(); @endphp
                        <button wire:click="copyFromBranch" wire:loading.attr="disabled"
                            @class(['inline-flex items-center gap-1.5 rounded-lg border px-3 py-2 text-sm font-medium transition-colors disabled:opacity-50',
                                'border-red-200 bg-red-50 text-red-700 hover:bg-red-100 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-400' => $existingCount > 0,
                                'border-gray-200 bg-gray-50 text-gray-700 hover:bg-gray-100 dark:border-white/[0.08] dark:bg-white/[0.03] dark:text-gray-300' => $existingCount === 0])>
                            <x-heroicon-o-arrow-path class="h-4 w-4" />
                            <span wire:loading.remove wire:target="copyFromBranch">
                                @if($existingCount > 0) Ganti {{ $existingCount }} → {{ $this->sourceTasks->count() }} Poin
                                @else Salin {{ $this->sourceTasks->count() }} Poin
                                @endif
                            </span>
                            <span wire:loading wire:target="copyFromBranch">Menyalin…</span>
                        </button>
                    @endif
                    <button wire:click="toggleCopyPanel"
                        class="rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-500 transition-colors hover:bg-gray-50 dark:border-white/[0.08] dark:hover:bg-white/[0.04]">
                        Batal
                    </button>
                </div>
                @if(isset($existingCount) && $existingCount > 0)
                    <p class="mt-3 flex items-center gap-1.5 text-xs text-red-500 dark:text-red-400">
                        <x-heroicon-o-exclamation-triangle class="h-3.5 w-3.5 shrink-0" />
                        {{ $existingCount }} poin yang ada akan dihapus semua dan diganti.
                    </p>
                @endif
                @if($copySourceBranchId !== null && $copySourceBranchId !== '' && ! $this->sourceTasks->isEmpty())
                    <div class="mt-2 flex flex-wrap gap-1.5">
                        @foreach($this->sourceTasks as $t)
                            <span class="rounded border border-gray-100 px-2 py-0.5 text-xs text-gray-500 dark:border-white/[0.06] dark:text-gray-400">{{ $t->label }}</span>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        {{-- ── Period sections ──────────────────────────────────────────── --}}
        @foreach($periods as $period)
            @php
                $pTasks          = $this->tasks->filter(fn ($t) => $t->period->value === $period['value']);
                $ungrouped        = $pTasks->filter(fn ($t) => ! $t->group)->values();
                $grouped          = $pTasks->filter(fn ($t) => $t->group)->groupBy('group');
                $pendingForPeriod = $pendingGroups[$period['value']] ?? [];
                $isAddingHere     = ($addingToPeriod === $period['value'] && ! $addingToGroup)
                                 || $creatingGroupForPeriod === $period['value']
                                 || ($addingToGroup !== null && $quickAdd['period'] === $period['value'])
                                 || ! empty($pendingForPeriod);
                $totalCount       = $pTasks->count();
                $badge            = $periodBadge[$period['value']];
            @endphp

            <div x-data="{ open: {{ ($totalCount > 0 || $isAddingHere) ? 'true' : 'false' }} }"
                class="overflow-hidden rounded-xl border border-gray-100 bg-white dark:border-white/[0.06] dark:bg-gray-900">

                {{-- Period header --}}
                <div @click="open = !open"
                    class="flex cursor-pointer select-none items-center gap-2.5 border-b border-gray-100 px-4 py-3 dark:border-white/[0.06]"
                    :class="open ? '' : 'border-transparent'">
                    <span class="inline-block h-2 w-2 rounded-full {{ $period['dot'] }}"></span>
                    <span class="flex-1 text-sm font-semibold text-gray-700 dark:text-gray-200">{{ $period['label'] }}</span>
                    @if($totalCount > 0)
                        <span class="text-xs text-gray-400 dark:text-gray-500">{{ $totalCount }} poin</span>
                    @endif
                    <x-heroicon-o-chevron-down class="h-3.5 w-3.5 shrink-0 text-gray-300 transition-transform duration-200 dark:text-gray-600"
                        ::class="open ? 'rotate-0' : '-rotate-90'" />
                </div>

                <div x-show="open" x-collapse>

                    {{-- Ungrouped tasks --}}
                    @if($ungrouped->isNotEmpty() || ($addingToPeriod === $period['value'] && ! $addingToGroup))
                        <table class="w-full text-sm">
                            <tbody class="divide-y divide-gray-50 dark:divide-white/[0.03]">
                                @foreach($ungrouped as $task)
                                    @include('filament.helpdesk.resources.briefing-tasks._task-row', ['task' => $task])
                                @endforeach
                                @if($addingToPeriod === $period['value'] && ! $addingToGroup)
                                    @include('filament.helpdesk.resources.briefing-tasks._quick-add-row', ['period' => $period, 'badge' => $badge])
                                @endif
                            </tbody>
                        </table>
                    @endif

                    {{-- Named groups --}}
                    @foreach($grouped as $groupKey => $groupTasks)
                        @php $gLabel = $groupTasks->first()->group_label ?: $groupKey; @endphp
                        <div x-data="{ gopen: true }" class="border-t border-gray-50 dark:border-white/[0.03]">
                            <div @click="gopen = !gopen"
                                class="flex cursor-pointer select-none items-center gap-2 border-b border-gray-50 bg-gray-50/50 px-4 py-2 dark:border-white/[0.03] dark:bg-white/[0.02]"
                                :class="gopen ? '' : 'border-transparent'">
                                <x-heroicon-o-folder-open class="h-3.5 w-3.5 shrink-0 text-gray-300 dark:text-gray-600" x-show="gopen" />
                                <x-heroicon-o-folder class="h-3.5 w-3.5 shrink-0 text-gray-300 dark:text-gray-600" x-show="!gopen" />
                                <span class="flex-1 text-xs font-medium text-gray-600 dark:text-gray-400">{{ $gLabel }}</span>
                                <span class="text-xs text-gray-300 dark:text-gray-600">{{ $groupTasks->count() }}</span>
                                <button
                                    wire:click.stop="startGroupQuickAdd('{{ $groupKey }}', '{{ addslashes($gLabel) }}', '{{ $period['value'] }}')"
                                    class="rounded px-1.5 py-0.5 text-xs text-gray-400 transition-colors hover:bg-gray-100 hover:text-primary-600 dark:hover:bg-white/[0.06] dark:hover:text-primary-400">
                                    + Tambah
                                </button>
                                <x-heroicon-o-chevron-down class="h-3 w-3 shrink-0 text-gray-300 transition-transform duration-200 dark:text-gray-600"
                                    ::class="gopen ? 'rotate-0' : '-rotate-90'" />
                            </div>
                            <div x-show="gopen" x-collapse>
                                <table class="w-full text-sm">
                                    <tbody class="divide-y divide-gray-50 dark:divide-white/[0.03]">
                                        @foreach($groupTasks as $task)
                                            @include('filament.helpdesk.resources.briefing-tasks._task-row', ['task' => $task])
                                        @endforeach
                                        @if($addingToGroup === $groupKey && $quickAdd['period'] === $period['value'])
                                            @include('filament.helpdesk.resources.briefing-tasks._quick-add-row', ['period' => $period, 'badge' => $badge])
                                        @endif
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endforeach

                    {{-- Pending (new, empty) groups --}}
                    @foreach($pendingForPeriod as $pg)
                        <div class="border-t border-gray-50 dark:border-white/[0.03]">
                            <div class="flex items-center gap-2 border-b border-gray-50 bg-gray-50/50 px-4 py-2 dark:border-white/[0.03] dark:bg-white/[0.02]">
                                <x-heroicon-o-folder-open class="h-3.5 w-3.5 shrink-0 text-gray-300 dark:text-gray-600" />
                                <span class="flex-1 text-xs font-medium text-gray-600 dark:text-gray-400">{{ $pg['label'] }}</span>
                                <span class="text-xs italic text-gray-300 dark:text-gray-600">baru</span>
                            </div>
                            <table class="w-full text-sm">
                                <tbody>
                                    @if($addingToGroup === $pg['key'] && $quickAdd['period'] === $period['value'])
                                        @include('filament.helpdesk.resources.briefing-tasks._quick-add-row', ['period' => $period, 'badge' => $badge])
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    @endforeach

                    {{-- New-group input --}}
                    @if($creatingGroupForPeriod === $period['value'])
                        <div class="border-t border-gray-50 px-4 py-3 dark:border-white/[0.03]">
                            <div class="flex items-center gap-2">
                                <x-heroicon-o-folder-plus class="h-4 w-4 shrink-0 text-gray-300 dark:text-gray-600" />
                                <input
                                    wire:model="newGroupLabel"
                                    wire:keydown.enter="saveNewGroup"
                                    wire:keydown.escape="cancelNewGroup"
                                    type="text"
                                    placeholder="Nama grup baru…"
                                    autofocus
                                    class="flex-1 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm text-gray-800 placeholder-gray-400 focus:border-primary-400 focus:outline-none focus:ring-1 focus:ring-primary-400 dark:border-white/[0.08] dark:bg-gray-900 dark:text-gray-200"
                                />
                                <button wire:click="saveNewGroup"
                                    class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 transition-colors hover:border-gray-300 hover:bg-gray-50 dark:border-white/[0.08] dark:text-gray-300 dark:hover:bg-white/[0.04]">
                                    Buat
                                </button>
                                <button wire:click="cancelNewGroup"
                                    class="rounded-lg px-2.5 py-1.5 text-sm text-gray-400 transition-colors hover:text-gray-600 dark:hover:text-gray-300">
                                    Batal
                                </button>
                            </div>
                            @error('newGroupLabel')
                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                            @enderror
                        </div>
                    @endif

                    {{-- Period footer --}}
                    <div class="flex items-center gap-1 border-t border-gray-50 px-3 py-2 dark:border-white/[0.03]">
                        <button wire:click="startPeriodQuickAdd('{{ $period['value'] }}')"
                            class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs text-gray-400 transition-colors hover:bg-gray-50 hover:text-gray-600 dark:hover:bg-white/[0.04] dark:hover:text-gray-300">
                            <x-heroicon-o-plus class="h-3.5 w-3.5" /> Tambah Poin
                        </button>
                        <span class="text-gray-100 dark:text-gray-800">|</span>
                        <button wire:click="startNewGroup('{{ $period['value'] }}')"
                            class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs text-gray-400 transition-colors hover:bg-gray-50 hover:text-gray-600 dark:hover:bg-white/[0.04] dark:hover:text-gray-300">
                            <x-heroicon-o-folder-plus class="h-3.5 w-3.5" /> Buat Grup
                        </button>
                    </div>

                </div>
            </div>
        @endforeach

    </div>
@endif

</x-filament-panels::page>
