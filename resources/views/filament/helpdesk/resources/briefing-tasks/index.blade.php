<x-filament-panels::page>

{{-- ═══ HEADER ═══ (docs/daily-briefing-scoring-ui-prd.md §13.1 visual pattern) --}}
@php
    $stats = $this->stats;
@endphp
<div class="mb-6 space-y-6">
    <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
        <div class="flex flex-col gap-5 p-5 sm:p-6 md:flex-row md:items-center md:justify-between">
            <div class="flex min-w-0 items-start gap-4">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-950/40 dark:text-blue-300">
                    <x-heroicon-o-list-bullet class="h-6 w-6" />
                </div>
                <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-blue-600 dark:text-blue-400">Human Resources · Task Master</p>
                    <h2 class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">Kelola Poin Briefing</h2>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-gray-500 dark:text-gray-400">Atur poin, periode, dan jenis input Daily Briefing secara global atau khusus per branch.</p>
                </div>
            </div>
        </div>
        <div class="flex items-start gap-2 border-t border-gray-200 bg-gray-50/60 px-5 py-3 text-xs leading-5 text-gray-500 dark:border-gray-700 dark:bg-gray-800/30 dark:text-gray-400 sm:px-6"><x-heroicon-o-information-circle class="mt-0.5 h-4 w-4 shrink-0 text-blue-500" /><span>Branch peserta tanpa poin khusus memakai poin Global. Poin khusus pada sebuah branch sepenuhnya menggantikan poin Global untuk branch tersebut.</span></div>
    </section>

    <section class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900"><p class="text-xs text-gray-500 dark:text-gray-400">Total Poin</p><p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['total']) }}</p></div>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-900 dark:bg-emerald-950/30"><p class="text-xs text-emerald-700 dark:text-emerald-300">Aktif</p><p class="mt-2 text-2xl font-bold text-emerald-900 dark:text-emerald-100">{{ number_format($stats['active']) }}</p></div>
        <div class="rounded-xl border border-blue-200 bg-blue-50 p-4 dark:border-blue-900 dark:bg-blue-950/30"><p class="text-xs text-blue-700 dark:text-blue-300">Global</p><p class="mt-2 text-2xl font-bold text-blue-900 dark:text-blue-100">{{ number_format($stats['global']) }}</p></div>
        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/40"><p class="text-xs text-gray-500 dark:text-gray-400">Khusus Branch</p><p class="mt-2 text-2xl font-bold text-gray-700 dark:text-gray-200">{{ number_format($stats['branchSpecific']) }}</p></div>
    </section>
</div>

{{-- ═══ BRANCH CARDS ═══ --}}
@if($this->selectedBranchId === null)

    <section class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900 sm:p-6">
        <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
            Pilih branch untuk melihat dan mengelola poin briefingnya.
        </p>
        <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2 lg:grid-cols-3">
            <button wire:click="selectGlobal"
                class="group flex items-center gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3.5 text-left transition-all hover:border-blue-200 hover:bg-blue-50/30 dark:border-gray-700 dark:bg-gray-900 dark:hover:border-blue-500/30 dark:hover:bg-blue-500/5">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-blue-50 dark:bg-blue-500/10">
                    <x-heroicon-o-globe-alt class="h-4 w-4 text-blue-500 dark:text-blue-400" />
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-gray-800 dark:text-white">Global</p>
                    <p class="text-xs text-gray-400 dark:text-gray-500">{{ $this->globalTaskCount }} poin · Semua Branch</p>
                </div>
                <x-heroicon-o-chevron-right class="h-3.5 w-3.5 shrink-0 text-gray-300 transition-transform group-hover:translate-x-0.5 group-hover:text-blue-400 dark:text-gray-600" />
            </button>

            @foreach($this->branches as $branch)
                <button wire:click="selectBranch({{ $branch->id }}, '{{ addslashes($branch->name) }}')"
                    class="group flex items-center gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3.5 text-left transition-all hover:border-blue-200 hover:bg-blue-50/30 dark:border-gray-700 dark:bg-gray-900 dark:hover:border-blue-500/30 dark:hover:bg-blue-500/5">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-blue-50 dark:bg-blue-500/10">
                        <x-heroicon-o-building-storefront class="h-4 w-4 text-blue-500 dark:text-blue-400" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold text-gray-800 dark:text-white">{{ $branch->name }}</p>
                        <p class="text-xs text-gray-400 dark:text-gray-500">{{ $branch->briefing_tasks_count }} poin · Branch</p>
                    </div>
                    <x-heroicon-o-chevron-right class="h-3.5 w-3.5 shrink-0 text-gray-300 transition-transform group-hover:translate-x-0.5 group-hover:text-blue-400 dark:text-gray-600" />
                </button>
            @endforeach
        </div>
    </section>

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

    <div class="space-y-4">

        {{-- ── Top bar ──────────────────────────────────────────────────── --}}
        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5">
                <div class="flex min-w-0 items-center gap-3">
                    <button wire:click="clearSelection"
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-300"
                        aria-label="Kembali ke Semua Branch" title="Kembali ke Semua Branch">
                        <x-heroicon-o-arrow-left class="h-4 w-4" />
                    </button>
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-950/40 dark:text-blue-300">
                        @if($this->selectedBranchId === 0)
                            <x-heroicon-o-globe-alt class="h-5 w-5" />
                        @else
                            <x-heroicon-o-building-storefront class="h-5 w-5" />
                        @endif
                    </div>
                    <div class="min-w-0">
                        <p class="truncate text-base font-bold text-gray-900 dark:text-white">{{ $this->selectedBranchName }}</p>
                        <p class="text-xs text-gray-400 dark:text-gray-500">{{ $this->tasks->count() }} poin dikelola di sini</p>
                    </div>
                </div>
                <button wire:click="toggleCopyPanel"
                    @class(['inline-flex shrink-0 items-center justify-center gap-1.5 rounded-lg border px-3 py-2 text-sm font-semibold transition-colors',
                        'border-amber-300 bg-amber-50 text-amber-700 hover:bg-amber-100 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-400' => $copyPanelOpen,
                        'border-gray-200 bg-white text-gray-600 hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-blue-500/30 dark:hover:bg-blue-500/10 dark:hover:text-blue-400' => ! $copyPanelOpen])>
                    <x-heroicon-o-document-duplicate class="h-4 w-4" />
                    Salin Poin
                </button>
            </div>
        </section>

        {{-- ── Copy modal ───────────────────────────────────────────────── --}}
        @if($copyPanelOpen)
            <div x-data x-trap.inert.noscroll="true" x-on:keydown.escape.window="$wire.toggleCopyPanel()" class="fixed inset-0 z-[130] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-label="Salin Poin Briefing">
                <button type="button" wire:click="toggleCopyPanel" class="absolute inset-0 bg-gray-950/60" aria-label="Tutup"></button>
                <x-rnd.picker-modal title="Salin Poin Briefing" description="Tujuan: {{ $this->selectedBranchName }}" max-width="xl">
                    <x-slot:close>
                        <button type="button" wire:click="toggleCopyPanel" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800" aria-label="Tutup"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                    </x-slot:close>
                    <div class="max-h-[70vh] space-y-4 overflow-y-auto p-5">
                        <div>
                            <label for="copy-source-branch" class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Salin Dari Branch</label>
                            <select id="copy-source-branch" wire:model.live="copySourceBranchId"
                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                                <option value="">— Pilih sumber —</option>
                                @foreach($this->copySourceOptions as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>

                        @if($copySourceBranchId !== null && $copySourceBranchId !== '')
                            @php $existingCount = $this->tasks->count(); @endphp

                            @if($existingCount > 0)
                                <p class="flex items-center gap-1.5 rounded-lg bg-red-50 px-3 py-2 text-xs font-medium text-red-600 dark:bg-red-950/30 dark:text-red-400">
                                    <x-heroicon-o-exclamation-triangle class="h-3.5 w-3.5 shrink-0" />
                                    {{ $existingCount }} poin yang ada di {{ $this->selectedBranchName }} akan dihapus semua dan diganti.
                                </p>
                            @endif

                            @if(! $this->sourceTasks->isEmpty())
                                <div>
                                    <p class="mb-1.5 text-sm font-semibold text-gray-700 dark:text-gray-200">Poin yang akan disalin ({{ $this->sourceTasks->count() }})</p>
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach($this->sourceTasks as $t)
                                            <span class="rounded-md border border-gray-200 bg-white px-2 py-0.5 text-xs text-gray-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400">{{ $t->label }}</span>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        @endif
                    </div>
                    <div class="flex items-center justify-end gap-2 border-t border-gray-200 px-5 py-4 dark:border-gray-700">
                        <button type="button" wire:click="toggleCopyPanel"
                            class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-600 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800">
                            Batal
                        </button>
                        @if($copySourceBranchId !== null && $copySourceBranchId !== '')
                            <button type="button" wire:click="copyFromBranch" wire:loading.attr="disabled" wire:target="copyFromBranch"
                                @class(['inline-flex items-center gap-1.5 rounded-lg px-4 py-2 text-sm font-bold text-white transition-colors disabled:opacity-50',
                                    'bg-red-600 hover:bg-red-700' => $existingCount > 0,
                                    'bg-blue-600 hover:bg-blue-700' => $existingCount === 0])>
                                <x-heroicon-o-arrow-path class="h-4 w-4" />
                                <span wire:loading.remove wire:target="copyFromBranch">
                                    @if($existingCount > 0) Ganti {{ $existingCount }} → {{ $this->sourceTasks->count() }} Poin
                                    @else Salin {{ $this->sourceTasks->count() }} Poin
                                    @endif
                                </span>
                                <span wire:loading wire:target="copyFromBranch">Menyalin…</span>
                            </button>
                        @endif
                    </div>
                </x-rnd.picker-modal>
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

            <div x-data="{ open: true }"
                class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">

                {{-- Period header --}}
                <div @click="open = !open"
                    class="flex cursor-pointer select-none items-center gap-2.5 border-b border-gray-200 px-4 py-3 dark:border-gray-700"
                    :class="open ? '' : 'border-transparent'">
                    <span class="inline-block h-2 w-2 rounded-full {{ $period['dot'] }}"></span>
                    <span class="flex-1 text-sm font-semibold text-gray-700 dark:text-gray-200">{{ $period['label'] }}</span>
                    @if($totalCount > 0)
                        <span class="text-xs text-gray-400 dark:text-gray-500">{{ $totalCount }} poin</span>
                    @else
                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500 dark:bg-gray-800 dark:text-gray-400">Pakai Global</span>
                    @endif
                    <x-heroicon-o-chevron-down class="h-3.5 w-3.5 shrink-0 text-gray-300 transition-transform duration-200 dark:text-gray-600"
                        ::class="open ? 'rotate-0' : '-rotate-90'" />
                </div>

                <div x-show="open" x-collapse>

                    {{-- Empty state: no tasks here yet and nothing currently being added --}}
                    @if($totalCount === 0 && ! $isAddingHere)
                        <div class="flex items-start gap-3 px-4 py-4">
                            <x-heroicon-o-information-circle class="mt-0.5 h-4 w-4 shrink-0 text-blue-400" />
                            <div class="min-w-0 flex-1">
                                <p class="text-sm text-gray-600 dark:text-gray-300">Belum ada poin khusus {{ $period['label'] }} untuk branch ini.</p>
                                <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">Branch ini memakai Poin Global untuk periode ini sampai poin khusus ditambahkan.</p>
                            </div>
                        </div>
                    @endif

                    {{-- Ungrouped tasks --}}
                    @if($ungrouped->isNotEmpty())
                        <table class="w-full text-sm">
                            <tbody x-data="{ draggingId: null, dragoverTarget: null }" x-on:dragover.prevent>
                                @foreach($ungrouped as $task)
                                    @include('filament.helpdesk.resources.briefing-tasks._task-row', ['task' => $task])
                                @endforeach
                            </tbody>
                        </table>
                    @endif

                    {{-- Named groups --}}
                    @foreach($grouped as $groupKey => $groupTasks)
                        @php $gLabel = $groupTasks->first()->group_label ?: $groupKey; @endphp
                        <div x-data="{ gopen: true }" class="mx-3 mb-2 overflow-hidden rounded-lg bg-gray-50/70 dark:bg-white/[0.03]">
                            <div @click="gopen = !gopen"
                                class="flex cursor-pointer select-none items-center gap-2 px-3 py-2">
                                <x-heroicon-o-folder-open class="h-3.5 w-3.5 shrink-0 text-gray-300 dark:text-gray-600" x-show="gopen" />
                                <x-heroicon-o-folder class="h-3.5 w-3.5 shrink-0 text-gray-300 dark:text-gray-600" x-show="!gopen" />
                                <span class="flex-1 text-xs font-medium text-gray-500 dark:text-gray-400">{{ $gLabel }}</span>
                                <span class="text-xs text-gray-300 dark:text-gray-600">{{ $groupTasks->count() }}</span>
                                <button
                                    wire:click.stop="startGroupQuickAdd('{{ $groupKey }}', '{{ addslashes($gLabel) }}', '{{ $period['value'] }}')"
                                    class="rounded px-1.5 py-0.5 text-xs text-gray-400 transition-colors hover:bg-white hover:text-primary-600 dark:hover:bg-white/[0.06] dark:hover:text-primary-400">
                                    + Tambah
                                </button>
                                <x-heroicon-o-chevron-down class="h-3 w-3 shrink-0 text-gray-300 transition-transform duration-200 dark:text-gray-600"
                                    ::class="gopen ? 'rotate-0' : '-rotate-90'" />
                            </div>
                            <div x-show="gopen" x-collapse>
                                <table class="w-full text-sm">
                                    <tbody x-data="{ draggingId: null, dragoverTarget: null }" x-on:dragover.prevent>
                                        @foreach($groupTasks as $task)
                                            @include('filament.helpdesk.resources.briefing-tasks._task-row', ['task' => $task])
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endforeach

                    {{-- Pending (new, empty) groups --}}
                    @foreach($pendingForPeriod as $pg)
                        <div class="mx-3 mb-2 overflow-hidden rounded-lg bg-gray-50/70 dark:bg-white/[0.03]">
                            <div class="flex items-center gap-2 px-3 py-2">
                                <x-heroicon-o-folder-open class="h-3.5 w-3.5 shrink-0 text-gray-300 dark:text-gray-600" />
                                <span class="flex-1 text-xs font-medium text-gray-500 dark:text-gray-400">{{ $pg['label'] }}</span>
                                <span class="text-xs italic text-gray-300 dark:text-gray-600">baru, belum ada poin</span>
                                <button
                                    wire:click.stop="startGroupQuickAdd('{{ $pg['key'] }}', '{{ addslashes($pg['label']) }}', '{{ $period['value'] }}')"
                                    class="rounded px-1.5 py-0.5 text-xs text-gray-400 transition-colors hover:bg-white hover:text-blue-600 dark:hover:bg-white/[0.06] dark:hover:text-blue-400">
                                    + Tambah
                                </button>
                            </div>
                        </div>
                    @endforeach

                    {{-- Period footer --}}
                    <div class="flex items-center gap-2 border-t border-gray-100 px-3 py-2.5 dark:border-gray-800">
                        <button wire:click="startPeriodQuickAdd('{{ $period['value'] }}')"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs font-semibold text-gray-600 transition-colors hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700 dark:border-gray-700 dark:text-gray-300 dark:hover:border-blue-500/30 dark:hover:bg-blue-500/10 dark:hover:text-blue-400">
                            <x-heroicon-o-plus class="h-3.5 w-3.5" /> Tambah Poin
                        </button>
                        <button wire:click="startNewGroup('{{ $period['value'] }}')"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs font-semibold text-gray-600 transition-colors hover:border-gray-300 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
                            <x-heroicon-o-folder-plus class="h-3.5 w-3.5" /> Buat Grup
                        </button>
                    </div>

                </div>
            </div>
        @endforeach

        {{-- ── Tambah Poin modal ────────────────────────────────────────── --}}
        @if($addingToPeriod !== null || $addingToGroup !== null)
            @php
                $quickAddPeriodValue = $quickAdd['period'];
                $quickAddPeriodLabel = collect($periods)->firstWhere('value', $quickAddPeriodValue)['label'] ?? $quickAddPeriodValue;
                $quickAddBadge = $periodBadge[$quickAddPeriodValue] ?? '';
            @endphp
            <div x-data x-trap.inert.noscroll="true" x-on:keydown.escape.window="$wire.cancelQuickAdd()" class="fixed inset-0 z-[130] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-label="Tambah Poin Briefing">
                <button type="button" wire:click="cancelQuickAdd" class="absolute inset-0 bg-gray-950/60" aria-label="Tutup"></button>
                <x-rnd.picker-modal
                    title="Tambah Poin Briefing"
                    description="{{ $addingToGroup ? 'Grup: '.$addingGroupLabel : 'Tanpa grup' }} · Periode {{ $quickAddPeriodLabel }}"
                    max-width="xl"
                >
                    <x-slot:close>
                        <button type="button" wire:click="cancelQuickAdd" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800" aria-label="Tutup"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                    </x-slot:close>
                    <div class="max-h-[70vh] space-y-4 overflow-y-auto p-5">
                        <div>
                            <label for="quick-add-label" class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Nama Poin <span class="text-red-500">*</span></label>
                            <input id="quick-add-label" wire:model="quickAdd.label" wire:keydown.enter="saveQuickTask" type="text" autofocus
                                placeholder="Nama poin baru…"
                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white" />
                            @error('quickAdd.label')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <span class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Periode</span>
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset {{ $quickAddBadge }}">{{ $quickAddPeriodLabel }}</span>
                            <span class="ml-2 text-xs text-gray-400 dark:text-gray-500">Ditentukan dari tombol yang diklik.</span>
                        </div>
                        <div>
                            <span class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Jenis Input</span>
                            <x-briefing.submission-type-picker model="quickAdd.submission_type" :selected="$quickAdd['submission_type']" />
                        </div>

                        @if($quickAddPeriodValue === 'daily')
                            <div class="rounded-lg bg-gray-50/70 p-3 dark:bg-white/[0.03]">
                                <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-200">
                                    <input type="checkbox" wire:model.live="quickAdd.deadline_enabled"
                                        class="rounded border-gray-300 text-blue-600 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800" />
                                    Aktifkan Deadline
                                </label>
                                @if($quickAdd['deadline_enabled'] ?? false)
                                    <div class="mt-2">
                                        <label for="quick-add-deadline-time" class="mb-1.5 block text-xs font-medium text-gray-500 dark:text-gray-400">Jam Batas</label>
                                        <input id="quick-add-deadline-time" wire:model="quickAdd.deadline_time" type="time"
                                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white" />
                                    </div>
                                @endif
                            </div>
                        @else
                            <p class="text-xs text-gray-400 dark:text-gray-500">Deadline untuk periode {{ $quickAddPeriodLabel }} otomatis diterapkan jam 23:59.</p>
                        @endif
                    </div>
                    <div class="flex items-center justify-end gap-2 border-t border-gray-200 px-5 py-4 dark:border-gray-700">
                        <button type="button" wire:click="cancelQuickAdd"
                            class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-600 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800">
                            Batal
                        </button>
                        <button type="button" wire:click="saveQuickTask" wire:loading.attr="disabled" wire:target="saveQuickTask"
                            class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-bold text-white transition-colors hover:bg-blue-700 disabled:opacity-50">
                            <span wire:loading.remove wire:target="saveQuickTask">Simpan</span>
                            <span wire:loading wire:target="saveQuickTask">Menyimpan…</span>
                        </button>
                    </div>
                </x-rnd.picker-modal>
            </div>
        @endif

        {{-- ── Buat Grup modal ─────────────────────────────────────────── --}}
        @if($creatingGroupForPeriod !== null)
            @php
                $newGroupPeriodLabel = collect($periods)->firstWhere('value', $creatingGroupForPeriod)['label'] ?? $creatingGroupForPeriod;
            @endphp
            <div x-data x-trap.inert.noscroll="true" x-on:keydown.escape.window="$wire.cancelNewGroup()" class="fixed inset-0 z-[130] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-label="Buat Grup Baru">
                <button type="button" wire:click="cancelNewGroup" class="absolute inset-0 bg-gray-950/60" aria-label="Tutup"></button>
                <x-rnd.picker-modal title="Buat Grup Baru" description="Periode {{ $newGroupPeriodLabel }}" max-width="xl">
                    <x-slot:close>
                        <button type="button" wire:click="cancelNewGroup" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800" aria-label="Tutup"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                    </x-slot:close>
                    <div class="max-h-[70vh] space-y-4 overflow-y-auto p-5">
                        <div>
                            <label for="new-group-label" class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Nama Grup <span class="text-red-500">*</span></label>
                            <input id="new-group-label" wire:model="newGroupLabel" type="text" autofocus
                                placeholder="Nama grup baru…"
                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white" />
                            @error('newGroupLabel')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div class="border-t border-gray-100 pt-4 dark:border-gray-800">
                            <div class="mb-3 flex items-center justify-between">
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">Poin di Grup Ini</p>
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset {{ $periodBadge[$creatingGroupForPeriod] ?? '' }}">{{ $newGroupPeriodLabel }}</span>
                            </div>

                            <div class="space-y-3">
                                @foreach($newGroupTasks as $i => $row)
                                    <div class="flex items-start gap-2 rounded-lg bg-gray-50/70 p-3 dark:bg-white/[0.03]" wire:key="new-group-task-{{ $i }}">
                                        <div class="flex-1 space-y-2">
                                            <input wire:model="newGroupTasks.{{ $i }}.label" wire:keydown.enter="saveNewGroup" type="text"
                                                placeholder="Nama poin #{{ $i + 1 }}…"
                                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white" />
                                            @error("newGroupTasks.{$i}.label")<p class="text-xs text-red-600">{{ $message }}</p>@enderror

                                            <x-briefing.submission-type-picker :model="\"newGroupTasks.{$i}.submission_type\"" :selected="$row['submission_type']" />
                                        </div>
                                        <button type="button" wire:click="removeNewGroupTaskRow({{ $i }})"
                                            class="mt-1 shrink-0 rounded-lg p-2 text-gray-400 transition-colors hover:bg-white hover:text-red-600 dark:hover:bg-white/[0.06] dark:hover:text-red-400"
                                            aria-label="Hapus poin ini">
                                            <x-heroicon-o-trash class="h-4 w-4" />
                                        </button>
                                    </div>
                                @endforeach
                            </div>

                            <button type="button" wire:click="addNewGroupTaskRow"
                                class="mt-3 inline-flex items-center gap-1.5 rounded-lg px-2 py-1.5 text-sm font-semibold text-blue-600 transition-colors hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-500/10">
                                <x-heroicon-o-plus class="h-4 w-4" />
                                Tambah Poin Lain
                            </button>
                        </div>
                    </div>
                    <div class="flex items-center justify-end gap-2 border-t border-gray-200 px-5 py-4 dark:border-gray-700">
                        <button type="button" wire:click="cancelNewGroup"
                            class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-600 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800">
                            Batal
                        </button>
                        <button type="button" wire:click="saveNewGroup" wire:loading.attr="disabled" wire:target="saveNewGroup"
                            class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-bold text-white transition-colors hover:bg-blue-700 disabled:opacity-50">
                            <span wire:loading.remove wire:target="saveNewGroup">Buat Grup &amp; Simpan Poin</span>
                            <span wire:loading wire:target="saveNewGroup">Menyimpan…</span>
                        </button>
                    </div>
                </x-rnd.picker-modal>
            </div>
        @endif

        {{-- ── Edit Poin modal ─────────────────────────────────────────── --}}
        @if($editingTaskId !== null)
            @php
                $editPeriodValue = $editTask['period'];
                $editPeriodLabel = collect($periods)->firstWhere('value', $editPeriodValue)['label'] ?? $editPeriodValue;
            @endphp
            <div x-data x-trap.inert.noscroll="true" x-on:keydown.escape.window="$wire.cancelEditTask()" class="fixed inset-0 z-[130] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-label="Edit Poin Briefing">
                <button type="button" wire:click="cancelEditTask" class="absolute inset-0 bg-gray-950/60" aria-label="Tutup"></button>
                <x-rnd.picker-modal title="Edit Poin Briefing" description="Key: {{ $editTask['key'] }}" max-width="xl">
                    <x-slot:close>
                        <button type="button" wire:click="cancelEditTask" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800" aria-label="Tutup"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                    </x-slot:close>
                    <div class="max-h-[70vh] space-y-4 overflow-y-auto p-5">
                        <div>
                            <label for="edit-task-label" class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Nama Poin <span class="text-red-500">*</span></label>
                            <input id="edit-task-label" wire:model="editTask.label" wire:keydown.enter="saveEditTask" type="text" autofocus
                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white" />
                            @error('editTask.label')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="edit-task-period" class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Periode</label>
                            <select id="edit-task-period" wire:model.live="editTask.period"
                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                                @foreach($periods as $p)
                                    <option value="{{ $p['value'] }}">{{ $p['label'] }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <span class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Jenis Input</span>
                            <x-briefing.submission-type-picker model="editTask.submission_type" :selected="$editTask['submission_type']" />
                        </div>

                        <div>
                            <label for="edit-task-note" class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Keterangan Singkat</label>
                            <input id="edit-task-note" wire:model="editTask.note_type" type="text"
                                placeholder="Misal: Foto Selfie Briefing"
                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white" />
                            @error('editTask.note_type')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>

                        @if($editingTaskHasGroup)
                            <div>
                                <label for="edit-task-group-label" class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Nama Grup</label>
                                <input id="edit-task-group-label" wire:model="editTask.group_label" type="text"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white" />
                                @error('editTask.group_label')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                        @endif

                        <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-200">
                            <input type="checkbox" wire:model="editTask.is_active"
                                class="rounded border-gray-300 text-blue-600 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800" />
                            Aktif
                        </label>

                        @if($editPeriodValue === 'daily')
                            <div class="rounded-lg bg-gray-50/70 p-3 dark:bg-white/[0.03]">
                                <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-200">
                                    <input type="checkbox" wire:model.live="editTask.deadline_enabled"
                                        class="rounded border-gray-300 text-blue-600 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800" />
                                    Aktifkan Deadline
                                </label>
                                @if($editTask['deadline_enabled'] ?? false)
                                    <div class="mt-2">
                                        <label for="edit-task-deadline-time" class="mb-1.5 block text-xs font-medium text-gray-500 dark:text-gray-400">Jam Batas</label>
                                        <input id="edit-task-deadline-time" wire:model="editTask.deadline_time" type="time"
                                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white" />
                                    </div>
                                @endif
                            </div>
                        @else
                            <p class="text-xs text-gray-400 dark:text-gray-500">Deadline untuk periode {{ $editPeriodLabel }} otomatis diterapkan jam 23:59.</p>
                        @endif
                    </div>
                    <div class="flex items-center justify-between gap-2 border-t border-gray-200 px-5 py-4 dark:border-gray-700">
                        <button type="button" wire:click="deleteFromEditModal({{ $editingTaskId }})"
                            class="rounded-lg px-3 py-2 text-sm font-semibold text-red-600 transition-colors hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10">
                            Hapus Poin
                        </button>
                        <div class="flex items-center gap-2">
                            <button type="button" wire:click="cancelEditTask"
                                class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-600 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800">
                                Batal
                            </button>
                            <button type="button" wire:click="saveEditTask" wire:loading.attr="disabled" wire:target="saveEditTask"
                                class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-bold text-white transition-colors hover:bg-blue-700 disabled:opacity-50">
                                <span wire:loading.remove wire:target="saveEditTask">Simpan</span>
                                <span wire:loading wire:target="saveEditTask">Menyimpan…</span>
                            </button>
                        </div>
                    </div>
                </x-rnd.picker-modal>
            </div>
        @endif

    </div>
@endif

</x-filament-panels::page>
