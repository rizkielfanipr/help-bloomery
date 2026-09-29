<x-filament-panels::page>
    <div class="space-y-6">
        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <div class="flex flex-col justify-between gap-5 p-5 sm:p-6 md:flex-row md:items-center">
                <div class="flex min-w-0 items-start gap-4">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-950/40 dark:text-blue-300">
                        <x-heroicon-o-clipboard-document-list class="h-6 w-6" />
                    </div>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider text-blue-600 dark:text-blue-400">Research &amp; Development</p>
                        <h2 class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">BOM Adjustment</h2>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-gray-500 dark:text-gray-400">Cari dan ubah BOM Assembly langsung tanpa membuat Project, dengan riwayat perubahan yang dapat diaudit.</p>
                    </div>
                </div>
                @if($this->canEdit())
                    <div class="flex flex-col items-end gap-1.5">
                        <button type="button" wire:click="refreshCatalog" wire:loading.attr="disabled" wire:target="refreshCatalog" aria-label="Refresh BOM" title="Refresh BOM" class="inline-flex shrink-0 items-center justify-center rounded-lg border border-gray-300 p-2.5 text-gray-600 transition hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800">
                            <x-heroicon-o-arrow-path class="h-5 w-5" />
                        </button>
                        @php($progress = $this->syncProgress())
                        @if($progress && ($progress['status'] ?? null) === 'running')
                            @php($percent = ($progress['total'] ?? 0) > 0 ? min(100, (int) round(($progress['scanned'] ?? 0) / $progress['total'] * 100)) : 0)
                            <div wire:poll.2s class="w-32">
                                <div class="h-1.5 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                                    <div class="h-full rounded-full bg-blue-600 transition-all" style="width: {{ $percent }}%"></div>
                                </div>
                                <p class="mt-1 text-right text-[11px] text-gray-500 dark:text-gray-400">{{ $percent }}%</p>
                            </div>
                        @elseif($progress && ($progress['status'] ?? null) === 'completed')
                            <p class="text-[11px] text-gray-500 dark:text-gray-400">{{ $progress['succeeded'] ?? 0 }} berhasil, {{ $progress['failed'] ?? 0 }} gagal</p>
                        @endif
                    </div>
                @endif
            </div>
            <div class="flex items-center gap-1 border-t border-gray-200 px-5 dark:border-gray-700 sm:px-6">
                <button type="button" wire:click="setTab('assembly')" class="border-b-2 px-3 py-3 text-sm font-semibold {{ $tab === 'assembly' ? 'border-blue-600 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400' }}">BOM Assembly</button>
                @if($this->canViewHistory())
                    <button type="button" wire:click="setTab('history')" class="border-b-2 px-3 py-3 text-sm font-semibold {{ $tab === 'history' ? 'border-blue-600 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400' }}">Change History</button>
                @endif
            </div>
        </section>

        @if($tab === 'assembly')
            <section class="relative space-y-4 rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900 sm:p-6">
                <div class="flex flex-wrap items-center gap-2">
                    <input type="text" wire:model.live.debounce.400ms="search" placeholder="Cari kode/nama BOM atau produk" aria-label="Search" class="min-w-[200px] flex-1 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                    <select wire:model.live="unitFilter" aria-label="Unit" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                        <option value="">All Unit</option>
                        @foreach($this->availableUnits() as $unit)
                            <option value="{{ $unit }}">{{ $unit }}</option>
                        @endforeach
                    </select>
                    <select wire:model.live="statusFilter" aria-label="Status" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                    <select wire:model.live="syncStatusFilter" aria-label="Sync Status" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                        <option value="">All Sync</option>
                        <option value="synced">Synced</option>
                        <option value="failed">Failed</option>
                        <option value="needs_reconciliation">Needs Reconciliation</option>
                    </select>
                    <select wire:model.live="perPage" aria-label="Per Page" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                        <option value="10">10</option>
                        <option value="20">20</option>
                        <option value="50">50</option>
                    </select>
                    <button type="button" wire:click="resetFilters" aria-label="Reset Filter" title="Reset Filter" class="rounded-lg border border-gray-300 p-2 text-gray-500 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-800">
                        <x-heroicon-o-x-mark class="h-4 w-4" />
                    </button>
                </div>

                @php($rows = $this->catalogRows())
                <div wire:loading.delay.flex wire:target="search,unitFilter,statusFilter,syncStatusFilter,perPage,resetFilters,previousPage,nextPage,goToPage" class="absolute inset-0 z-10 hidden items-center justify-center rounded-b-2xl bg-white/70 dark:bg-gray-900/70">
                    <span class="inline-flex items-center gap-2 text-xs font-semibold text-blue-600 dark:text-blue-400">
                        <span class="h-4 w-4 animate-spin rounded-full border-2 border-blue-200 border-r-blue-600"></span>
                        Memuat data...
                    </span>
                </div>
                @if($rows->isEmpty())
                    <div class="rounded-xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500 dark:border-gray-600 dark:text-gray-400">
                        Tidak ada BOM Assembly yang cocok dengan filter ini.
                    </div>
                @else
                    <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
                        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-800/50">
                                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    <th class="px-4 py-3">BOM</th>
                                    <th class="px-4 py-3">Product Result</th>
                                    <th class="px-4 py-3">Unit</th>
                                    <th class="px-4 py-3">Status</th>
                                    <th class="px-4 py-3">Last Synced</th>
                                    <th class="px-4 py-3">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach($rows as $row)
                                    <tr wire:key="bom-catalog-{{ $row->id }}">
                                        <td class="px-4 py-3">
                                            <p class="font-semibold text-gray-900 dark:text-white">{{ $row->bom_name ?: '-' }}</p>
                                        </td>
                                        <td class="px-4 py-3">
                                            <p class="text-gray-900 dark:text-white">{{ $row->product_code ?: '-' }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $row->product_name ?: '-' }}</p>
                                        </td>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $row->uom_name ?: '-' }}</td>
                                        <td class="px-4 py-3">
                                            <span @class([
                                                'inline-flex rounded-full px-2 py-0.5 text-xs font-semibold',
                                                'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' => $row->is_active,
                                                'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400' => ! $row->is_active,
                                            ])>{{ $row->is_active ? 'Active' : 'Inactive' }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">{{ $row->last_synced_at?->format('d M Y H:i') ?? 'Belum pernah' }}</td>
                                        <td class="px-4 py-3">
                                            @if($this->canEdit())
                                                <a href="{{ \App\Filament\Helpdesk\Pages\EditBomAdjustmentPage::getUrl(['bomId' => $row->esb_bom_id]) }}" aria-label="Edit" title="Edit" class="inline-flex rounded-lg p-1.5 text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-950/40">
                                                    <x-heroicon-o-pencil-square class="h-4 w-4" />
                                                </a>
                                            @else
                                                <span class="text-xs text-gray-400">Detail</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <x-rnd.numbered-pagination :paginator="$rows" previous-method="previousPage" next-method="nextPage" go-to-method="goToPage" label="BOM" />
                @endif
            </section>
        @elseif($tab === 'history' && $this->canViewHistory())
            <section class="relative space-y-4 rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900 sm:p-6">
                <div class="flex flex-wrap items-center gap-2">
                    <input type="text" wire:model.live.debounce.400ms="historySearch" placeholder="Cari BOM, produk, user, alasan" aria-label="Search" class="min-w-[200px] flex-1 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                    <select wire:model.live="historyEventFilter" aria-label="Event" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                        <option value="">All Event</option>
                        @foreach(\App\Enums\RndBomChangeLogEvent::cases() as $event)
                            <option value="{{ $event->value }}">{{ $event->getLabel() }}</option>
                        @endforeach
                    </select>
                    <select wire:model.live="historySourceFilter" aria-label="Source" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                        <option value="">All Source</option>
                        @foreach(\App\Enums\RndBomChangeLogSource::cases() as $source)
                            <option value="{{ $source->value }}">{{ $source->getLabel() }}</option>
                        @endforeach
                    </select>
                    <select wire:model.live="historyStatusFilter" aria-label="Status" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                        <option value="">All Status</option>
                        @foreach(\App\Enums\RndBomChangeLogStatus::cases() as $status)
                            <option value="{{ $status->value }}">{{ $status->getLabel() }}</option>
                        @endforeach
                    </select>
                    <select wire:model.live="historyUserFilter" aria-label="User" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                        <option value="">All User</option>
                        @foreach($this->availableHistoryUsers() as $user)
                            <option value="{{ $user->id }}">{{ $user->display_username }}</option>
                        @endforeach
                    </select>
                    <div x-data="{ open: false }" class="relative">
                        <button type="button" x-on:click="open = !open" aria-label="Date Range" title="Date Range" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-600 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300">
                            <x-heroicon-o-calendar class="h-4 w-4 text-gray-400" />
                            <span>
                                @if($historyDateFrom || $historyDateTo)
                                    {{ $historyDateFrom ?: '…' }} &rarr; {{ $historyDateTo ?: '…' }}
                                @else
                                    Date Range
                                @endif
                            </span>
                        </button>
                        <div x-show="open" x-on:click.outside="open = false" x-cloak class="absolute right-0 z-20 mt-2 w-64 space-y-3 rounded-lg border border-gray-200 bg-white p-3 shadow-lg dark:border-gray-700 dark:bg-gray-900">
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-gray-500 dark:text-gray-400">From</label>
                                <input type="date" wire:model.live="historyDateFrom" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-gray-500 dark:text-gray-400">To</label>
                                <input type="date" wire:model.live="historyDateTo" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                            </div>
                        </div>
                    </div>
                    <button type="button" wire:click="resetHistoryFilters" aria-label="Reset Filter" title="Reset Filter" class="rounded-lg border border-gray-300 p-2 text-gray-500 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-800">
                        <x-heroicon-o-x-mark class="h-4 w-4" />
                    </button>
                </div>

                @php($logs = $this->changeLogRows())
                <div wire:loading.delay.flex wire:target="historySearch,historyEventFilter,historyStatusFilter,historySourceFilter,historyUserFilter,historyDateFrom,historyDateTo,historyPerPage,resetHistoryFilters,previousHistoryPage,nextHistoryPage,goToHistoryPage" class="absolute inset-0 z-10 hidden items-center justify-center rounded-b-2xl bg-white/70 dark:bg-gray-900/70">
                    <span class="inline-flex items-center gap-2 text-xs font-semibold text-blue-600 dark:text-blue-400">
                        <span class="h-4 w-4 animate-spin rounded-full border-2 border-blue-200 border-r-blue-600"></span>
                        Memuat data...
                    </span>
                </div>
                @if($logs->isEmpty())
                    <div class="rounded-xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500 dark:border-gray-600 dark:text-gray-400">
                        Belum ada riwayat perubahan yang cocok dengan filter ini.
                    </div>
                @else
                    <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
                        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-800/50">
                                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    <th class="px-4 py-3">BOM</th>
                                    <th class="px-4 py-3">Event</th>
                                    <th class="px-4 py-3">Source</th>
                                    <th class="px-4 py-3">Status</th>
                                    <th class="px-4 py-3">User</th>
                                    <th class="px-4 py-3">Time</th>
                                    <th class="px-4 py-3">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach($logs as $log)
                                    <tr wire:key="bom-change-log-{{ $log->id }}">
                                        <td class="px-4 py-3">
                                            <p class="font-semibold text-gray-900 dark:text-white">{{ $log->bom_name ?: '-' }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $log->product_name ?: '-' }}</p>
                                        </td>
                                        <td class="px-4 py-3">
                                            <x-filament::badge :color="$log->event->getColor()">{{ $log->event->getLabel() }}</x-filament::badge>
                                        </td>
                                        <td class="px-4 py-3">
                                            <x-filament::badge :color="$log->source->getColor()">{{ $log->source->getLabel() }}</x-filament::badge>
                                        </td>
                                        <td class="px-4 py-3">
                                            <x-filament::badge :color="$log->status->getColor()">{{ $log->status->getLabel() }}</x-filament::badge>
                                        </td>
                                        <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">{{ $log->changedBy?->display_username ?? 'Tidak diketahui' }}</td>
                                        <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">{{ $log->created_at->format('d M Y H:i') }}</td>
                                        <td class="px-4 py-3 space-x-1">
                                            <button type="button" wire:click="showHistoryDetail({{ $log->id }})" aria-label="Detail" title="Detail" class="inline-flex rounded-lg p-1.5 text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-950/40">
                                                <x-heroicon-o-eye class="h-4 w-4" />
                                            </button>
                                            @if($this->canReconcile() && $log->status === \App\Enums\RndBomChangeLogStatus::NeedsReconciliation)
                                                <button type="button" wire:click="reconcile({{ $log->id }})" wire:confirm="Rekonsiliasi record ini dengan data terbaru ESB?" aria-label="Reconcile" title="Reconcile" class="inline-flex rounded-lg p-1.5 text-amber-600 hover:bg-amber-50 dark:text-amber-400 dark:hover:bg-amber-950/40">
                                                    <x-heroicon-o-check-circle class="h-4 w-4" />
                                                </button>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <x-rnd.numbered-pagination :paginator="$logs" previous-method="previousHistoryPage" next-method="nextHistoryPage" go-to-method="goToHistoryPage" label="riwayat" />
                @endif
            </section>

            @if($historyDetailId && ($detailLog = \App\Models\RndBomChangeLog::query()->with(['changedBy', 'reconciledBy'])->find($historyDetailId)))
                <div class="fixed inset-0 z-[130] flex items-center justify-center p-4" x-data x-trap.inert.noscroll="true" x-on:keydown.escape.window="$wire.closeHistoryDetail()">
                    <button type="button" wire:click="closeHistoryDetail" class="absolute inset-0 bg-gray-950/60" aria-label="Tutup detail riwayat"></button>
                    <x-rnd.picker-modal title="Detail Perubahan BOM" max-width="4xl">
                        <x-slot name="close">
                            <button type="button" wire:click="closeHistoryDetail" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-200" aria-label="Tutup">
                                <x-heroicon-o-x-mark class="h-5 w-5" />
                            </button>
                        </x-slot>
                        <div class="space-y-5 overflow-y-auto p-5 text-sm">
                            <div class="flex items-start gap-3 rounded-xl border border-gray-200 bg-gray-50/60 p-4 dark:border-gray-700 dark:bg-gray-800/40">
                                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-blue-200 bg-white text-blue-600 dark:border-blue-800 dark:bg-gray-900 dark:text-blue-300">
                                    <x-heroicon-o-cube class="h-6 w-6" />
                                </div>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-bold text-gray-900 dark:text-white">{{ $detailLog->bom_name ?: 'BOM tanpa nama' }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">ESB BOM ID: <span class="font-mono">{{ $detailLog->esb_bom_id }}</span></p>
                                    @if($detailLog->product_name)
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Product Result: <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $detailLog->product_name }}</span>@if($detailLog->product_code) <span class="font-mono">({{ $detailLog->product_code }})</span>@endif</p>
                                    @endif
                                </div>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-700">
                                    <p class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wide text-gray-400"><x-heroicon-o-tag class="h-3.5 w-3.5" /> Source</p>
                                    <p class="mt-1.5"><x-filament::badge :color="$detailLog->source->getColor()">{{ $detailLog->source->getLabel() }}</x-filament::badge></p>
                                </div>
                                <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-700">
                                    <p class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wide text-gray-400"><x-heroicon-o-bolt class="h-3.5 w-3.5" /> Event</p>
                                    <p class="mt-1.5"><x-filament::badge :color="$detailLog->event->getColor()">{{ $detailLog->event->getLabel() }}</x-filament::badge></p>
                                </div>
                                <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-700">
                                    <p class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wide text-gray-400"><x-heroicon-o-signal class="h-3.5 w-3.5" /> Status</p>
                                    <p class="mt-1.5"><x-filament::badge :color="$detailLog->status->getColor()">{{ $detailLog->status->getLabel() }}</x-filament::badge></p>
                                </div>
                                <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-700">
                                    <p class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wide text-gray-400"><x-heroicon-o-user class="h-3.5 w-3.5" /> User</p>
                                    <p class="mt-1.5 font-semibold text-gray-800 dark:text-gray-100">{{ $detailLog->changedBy?->display_username ?? 'Tidak diketahui' }}</p>
                                </div>
                                <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-700">
                                    <p class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wide text-gray-400"><x-heroicon-o-clock class="h-3.5 w-3.5" /> Waktu</p>
                                    <p class="mt-1.5 font-semibold text-gray-800 dark:text-gray-100">{{ $detailLog->created_at->format('d M Y H:i') }}</p>
                                </div>
                                @if($detailLog->esb_edited_at_before || $detailLog->esb_edited_at_after)
                                    <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-700">
                                        <p class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wide text-gray-400"><x-heroicon-o-arrow-path class="h-3.5 w-3.5" /> Edited Date ESB</p>
                                        <p class="mt-1.5 text-xs text-gray-600 dark:text-gray-300">{{ $detailLog->esb_edited_at_before ?: '-' }} &rarr; {{ $detailLog->esb_edited_at_after ?: '-' }}</p>
                                    </div>
                                @endif
                            </div>

                            <div class="rounded-xl border border-gray-200 bg-white p-3.5 dark:border-gray-700 dark:bg-gray-900">
                                <p class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wide text-gray-400"><x-heroicon-o-chat-bubble-left-right class="h-3.5 w-3.5" /> Alasan Perubahan</p>
                                <p class="mt-1.5 text-sm leading-6 text-gray-700 dark:text-gray-200">{{ $detailLog->reason ?: 'Tidak ada alasan yang dicatat.' }}</p>
                            </div>

                            @if($detailLog->error_message)
                                <div class="flex items-start gap-2.5 rounded-xl border border-red-200 bg-red-50 p-3.5 dark:border-red-900 dark:bg-red-950/30">
                                    <x-heroicon-o-exclamation-triangle class="mt-0.5 h-4 w-4 shrink-0 text-red-600" />
                                    <div class="min-w-0">
                                        <p class="text-xs font-bold text-red-800 dark:text-red-200">Pesan Error @if($detailLog->error_code) <span class="font-mono font-normal">({{ $detailLog->error_code }})</span>@endif</p>
                                        <p class="mt-0.5 text-xs leading-5 text-red-700 dark:text-red-300">{{ $detailLog->error_message }}</p>
                                    </div>
                                </div>
                            @endif
                            {{-- TEMP DEBUG: reconciliation block removed --}}

                            @php($detail = $this->historyDetailDiff($detailLog))
                            @if($detail['diff'] === null)
                                <p class="text-xs text-gray-400">No change data available for this record yet.</p>
                            @else
                                <div class="space-y-3 border-t border-gray-100 pt-4 dark:border-gray-800">
                                    <div class="flex items-center justify-between">
                                        <p class="flex items-center gap-1.5 text-xs font-bold uppercase tracking-wide text-gray-600 dark:text-gray-300"><x-heroicon-o-list-bullet class="h-4 w-4 text-blue-600" /> BOM Changes</p>
                                        <x-filament::badge :color="$detail['is_confirmed'] ? 'success' : 'gray'">
                                            {{ $detail['is_confirmed'] ? 'Verified by ESB' : 'Requested (not yet verified)' }}
                                        </x-filament::badge>
                                    </div>

                                    @if(!empty($detail['diff']['product_result_changed']))
                                        <p class="rounded-lg bg-blue-50 px-3 py-2 text-xs text-blue-800 dark:bg-blue-950/30 dark:text-blue-200">
                                            <strong>Product Result:</strong> {{ data_get($detail['diff'], 'product_result.before.productName') ?: '-' }} &rarr; {{ data_get($detail['diff'], 'product_result.after.productName') ?: '-' }}
                                        </p>
                                    @endif
                                    @if(!empty($detail['diff']['unit_changed']))
                                        <p class="rounded-lg bg-blue-50 px-3 py-2 text-xs text-blue-800 dark:bg-blue-950/30 dark:text-blue-200">
                                            <strong>Unit:</strong> {{ data_get($detail['diff'], 'unit.before') ?: '-' }} &rarr; {{ data_get($detail['diff'], 'unit.after') ?: '-' }}
                                        </p>
                                    @endif
                                    @if(!empty($detail['diff']['status_changed']))
                                        <p class="rounded-lg bg-blue-50 px-3 py-2 text-xs text-blue-800 dark:bg-blue-950/30 dark:text-blue-200">
                                            <strong>BOM Status:</strong> {{ data_get($detail['diff'], 'status.before') ? 'Active' : 'Inactive' }} &rarr; {{ data_get($detail['diff'], 'status.after') ? 'Active' : 'Inactive' }}
                                        </p>
                                    @endif

                                    @if($detail['rows'] === [])
                                        <p class="text-xs text-gray-400">No component changes detected.</p>
                                    @else
                                        <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
                                            <table class="min-w-full divide-y divide-gray-200 text-xs dark:divide-gray-700">
                                                <thead class="bg-gray-50 dark:bg-gray-800/50">
                                                    <tr class="text-left font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                                        <th class="px-3 py-2">Component</th>
                                                        <th class="px-3 py-2">Unit</th>
                                                        <th class="px-3 py-2 text-right">Qty Before</th>
                                                        <th class="px-3 py-2 text-right">Qty After</th>
                                                        <th class="px-3 py-2">Note</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                                    @foreach($detail['rows'] as $row)
                                                        <tr>
                                                            <td class="px-3 py-2 font-semibold text-gray-800 dark:text-gray-100">{{ $row['product'] }}</td>
                                                            <td class="px-3 py-2 text-gray-500">{{ $row['unit'] }}</td>
                                                            <td class="px-3 py-2 text-right text-gray-700 dark:text-gray-300">{{ $row['before'] === null ? '-' : number_format($row['before'], 2, ',', '.') }}</td>
                                                            <td class="px-3 py-2 text-right font-semibold text-gray-900 dark:text-white">{{ $row['after'] === null ? '-' : number_format($row['after'], 2, ',', '.') }}</td>
                                                            <td class="px-3 py-2">
                                                                <span @class([
                                                                    'inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold',
                                                                    'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' => $row['note'] === 'Added',
                                                                    'bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-300' => $row['note'] === 'Removed',
                                                                    'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300' => $row['note'] === 'Qty Changed',
                                                                ])>{{ $row['note'] }}</span>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </x-rnd.picker-modal>
                </div>
            @endif
        @endif
    </div>
</x-filament-panels::page>
