<x-filament-panels::page>
    @php
        $calendar = $this->calendar;
        $filterBranches = $this->filterBranches;
        $statusOptions = $this->statusOptions;
        $syncSummary = $this->syncSummary;
        $inputClass = 'w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white';
        // prd.md §34, §38: read-only chips with an "ESB" text badge, so colour is never the only cue.
        $chipClass = 'border-indigo-200 bg-indigo-50 text-indigo-900 hover:bg-indigo-100 dark:border-indigo-800 dark:bg-indigo-950/40 dark:text-indigo-100 dark:hover:bg-indigo-950/70';
        $esbBadge = 'rounded bg-indigo-600 px-1 text-[10px] font-bold leading-4 text-white dark:bg-indigo-500';
        $syncMessages = [
            \App\Services\Kitchen\ProductionOrderCalendarQuery::STATE_FAILED => ['Sinkronisasi terakhir gagal. Menampilkan data terakhir yang tersimpan.', 'red'],
            \App\Services\Kitchen\ProductionOrderCalendarQuery::STATE_PARTIAL => ['Sebagian detail Production Order belum berhasil diambil.', 'amber'],
            \App\Services\Kitchen\ProductionOrderCalendarQuery::STATE_STALE => ['Data Production Order mungkin belum terbaru.', 'amber'],
            \App\Services\Kitchen\ProductionOrderCalendarQuery::STATE_RUNNING => ['Sinkronisasi sedang berjalan. Data akan diperbarui otomatis.', 'blue'],
            \App\Services\Kitchen\ProductionOrderCalendarQuery::STATE_NEVER_SYNCED => ['Production Order ESB belum pernah disinkronkan untuk cabang ini.', 'gray'],
            \App\Services\Kitchen\ProductionOrderCalendarQuery::STATE_INCOMPLETE_MAPPING => ['Mapping ESB cabang ini belum lengkap, sehingga Production Order belum dapat diambil.', 'gray'],
            \App\Services\Kitchen\ProductionOrderCalendarQuery::STATE_NO_MAPPING => ['Cabang ini belum terhubung ke ESB. Hubungi IT untuk mengatur mapping cabang.', 'gray'],
        ];
        $toneClass = [
            'red' => 'border-red-200 bg-red-50 text-red-800 dark:border-red-900 dark:bg-red-950/30 dark:text-red-200',
            'amber' => 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200',
            'blue' => 'border-blue-200 bg-blue-50 text-blue-800 dark:border-blue-900 dark:bg-blue-950/30 dark:text-blue-200',
            'gray' => 'border-gray-200 bg-gray-50 text-gray-700 dark:border-gray-700 dark:bg-gray-800/40 dark:text-gray-300',
        ];
        $emptyMessage = $this->hasActiveFilters() ? 'Tidak ada Production Order ESB pada periode dan filter ini.' : 'Belum ada Production Order ESB pada bulan ini.';
        $isSyncing = $this->isSyncing();
    @endphp

    <section @if($isSyncing) wire:poll.5s @endif class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900" aria-labelledby="kitchen-schedule-heading">
        {{-- Header --}}
        <div class="flex items-start gap-3 border-b border-gray-200 p-5 dark:border-gray-700">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950/40 dark:text-indigo-300">
                <x-heroicon-o-calendar-days class="h-6 w-6" />
            </div>
            <div class="min-w-0">
                <h2 id="kitchen-schedule-heading" class="text-lg font-bold text-gray-900 dark:text-white">Kalender Production Order</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">Klik Production Order untuk melihat detail BOM, quantity, dan material.</p>
            </div>
        </div>

        {{-- Toolbar: month navigation and filters --}}
        <div class="flex flex-col gap-3 border-b border-gray-200 px-5 py-3 dark:border-gray-700 lg:flex-row lg:items-end lg:justify-between">
            <div class="flex flex-wrap items-center gap-2">
                <div class="inline-flex h-9 items-stretch divide-x divide-gray-200 overflow-hidden rounded-lg border border-gray-200 bg-white dark:divide-gray-700 dark:border-gray-700 dark:bg-gray-900" role="group" aria-label="Navigasi bulan">
                    <button type="button" wire:click="previousMonth" class="inline-flex w-9 items-center justify-center text-gray-600 transition hover:bg-gray-50 focus:outline-none focus-visible:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-800" aria-label="Bulan sebelumnya"><x-heroicon-o-chevron-left class="h-4 w-4" /></button>
                    <span class="flex min-w-36 items-center justify-center whitespace-nowrap px-3 text-sm font-semibold text-gray-800 dark:text-gray-100" aria-live="polite">{{ $calendar['monthLabel'] }}</span>
                    <button type="button" wire:click="nextMonth" class="inline-flex w-9 items-center justify-center text-gray-600 transition hover:bg-gray-50 focus:outline-none focus-visible:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-800" aria-label="Bulan berikutnya"><x-heroicon-o-chevron-right class="h-4 w-4" /></button>
                </div>
                <button type="button" wire:click="currentMonth" class="inline-flex h-9 items-center rounded-lg border border-gray-200 bg-white px-3 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">Hari Ini</button>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 lg:flex lg:items-end">
                <div>
                    <label for="kitchen-filter-branch" class="mb-1 block text-xs font-semibold text-gray-600 dark:text-gray-300">Branch Kitchen</label>
                    <select id="kitchen-filter-branch" wire:model.live="filterBranchId" class="{{ $inputClass }} lg:w-56">
                        <option value="">Semua Kitchen</option>
                        @foreach($filterBranches as $branch)
                            <option value="{{ $branch->id }}">{{ $branch->name }}{{ $branch->is_active ? '' : ' (nonaktif)' }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="kitchen-filter-status" class="mb-1 block text-xs font-semibold text-gray-600 dark:text-gray-300">Status</label>
                    <select id="kitchen-filter-status" wire:model.live="filterStatus" class="{{ $inputClass }} lg:w-48">
                        <option value="">Semua</option>
                        @foreach($statusOptions as $statusId => $statusLabel)
                            <option value="{{ $statusId }}">{{ $statusLabel }}</option>
                        @endforeach
                    </select>
                </div>
                @if($this->canSync)
                    {{-- prd.md §39: queues a read-only sync; never waits for ESB in the browser request. --}}
                    <button type="button" wire:click="syncNow" wire:loading.attr="disabled" wire:target="syncNow" @disabled($isSyncing) class="inline-flex h-9 items-center justify-center gap-1.5 whitespace-nowrap rounded-lg border border-blue-200 bg-blue-50 px-3 text-sm font-semibold text-blue-700 transition hover:bg-blue-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 disabled:cursor-not-allowed disabled:opacity-60 dark:border-blue-900 dark:bg-blue-950/30 dark:text-blue-300 dark:hover:bg-blue-950/50 sm:col-span-2 lg:col-span-1">
                        <span @class(['inline-flex shrink-0', 'animate-spin' => $isSyncing]) wire:loading.class="animate-spin" wire:target="syncNow" data-sync-icon><x-heroicon-o-arrow-path class="h-4 w-4" /></span>
                        <span>Sync Data</span>
                    </button>
                @endif
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-x-4 gap-y-2 border-b border-gray-200 px-5 py-2.5 text-xs text-gray-600 dark:border-gray-700 dark:text-gray-300" aria-label="Legenda">
            <span class="inline-flex items-center gap-1.5"><span class="inline-flex items-center gap-1 rounded-md border px-1.5 py-0.5 font-semibold {{ $chipClass }}"><span class="{{ $esbBadge }}">ESB</span> Production Order</span> data dari ESB, hanya baca</span>
            <span class="text-gray-400 dark:text-gray-500">· Jumlah item per order, bukan total quantity (satuan berbeda tidak dijumlahkan).</span>
        </div>

        @if($syncSummary !== null && ($syncSummary['groups'] !== [] || $syncSummary['lastSyncedAt']))
            <div class="space-y-2 border-b border-gray-200 px-5 py-3 text-sm dark:border-gray-700" aria-label="Status sinkronisasi Production Order ESB">
                @foreach($syncMessages as $state => [$message, $tone])
                    @if(! empty($syncSummary['groups'][$state]))
                        @php($names = $syncSummary['groups'][$state])
                        <p class="rounded-lg border px-3 py-2 {{ $toneClass[$tone] }}" role="status">
                            {{ $message }}
                            <span class="block text-xs opacity-80 sm:inline">{{ count($names) === 1 ? $names[0] : count($names).' cabang: '.implode(', ', array_slice($names, 0, 5)).(count($names) > 5 ? ', …' : '') }}</span>
                        </p>
                    @endif
                @endforeach
                @if($syncSummary['lastSyncedAt'])
                    <p class="text-xs text-gray-500 dark:text-gray-400">Terakhir disinkronkan {{ $syncSummary['lastSyncedAt']->timezone(config('app.timezone'))->locale('id')->translatedFormat('d M Y H:i') }}.</p>
                @endif
            </div>
        @endif

        {{-- Body --}}
        <div class="relative">
            <div wire:loading.flex wire:target="previousMonth, nextMonth, currentMonth, filterBranchId, filterStatus, resetFilters" class="absolute inset-0 z-10 items-start justify-center bg-white/70 pt-16 dark:bg-gray-900/70" role="status">
                <span class="inline-flex items-center gap-2 text-sm font-semibold text-gray-600 dark:text-gray-300"><x-filament::loading-indicator class="h-5 w-5" /> Memuat kalender...</span>
            </div>

            @if($calendar['error'])
                <div class="p-10 text-center" role="alert">
                    <x-heroicon-o-exclamation-circle class="mx-auto h-8 w-8 text-red-500" />
                    <p class="mt-2 text-sm font-semibold text-gray-800 dark:text-gray-100">Kalender gagal dimuat.</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Coba lagi beberapa saat lagi.</p>
                    <button type="button" wire:click="$refresh" class="mt-3 inline-flex h-9 items-center gap-1.5 rounded-lg border border-gray-200 px-3 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800"><x-heroicon-o-arrow-path class="h-4 w-4" /> Coba Lagi</button>
                </div>
            @elseif($filterBranches->isEmpty())
                <div class="p-10 text-center" role="status">
                    <x-heroicon-o-lock-closed class="mx-auto h-8 w-8 text-gray-400" />
                    <p class="mt-2 text-sm font-semibold text-gray-800 dark:text-gray-100">Belum ada Branch Kitchen yang dapat Anda akses.</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Hubungi administrator untuk memberikan akses Branch Kitchen.</p>
                </div>
            @else
                @if($calendar['total'] === 0)
                    <div class="flex flex-col gap-2 border-b border-gray-200 bg-gray-50 px-5 py-3 text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-800/40 dark:text-gray-300 sm:flex-row sm:items-center sm:justify-between" role="status">
                        <span>{{ $emptyMessage }}</span>
                        @if($this->hasActiveFilters())
                            <button type="button" wire:click="resetFilters" class="text-left text-sm font-semibold text-blue-600 hover:underline dark:text-blue-400">Reset filter</button>
                        @endif
                    </div>
                @endif

                {{-- Desktop: month grid --}}
                <div class="hidden md:block">
                    <div class="grid grid-cols-7 border-b border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800/60">
                        @foreach(['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] as $dayName)
                            <div class="border-r border-gray-200 px-3 py-2 text-center text-xs font-bold uppercase tracking-wide text-gray-500 last:border-r-0 dark:border-gray-700">{{ $dayName }}</div>
                        @endforeach
                    </div>
                    @foreach($calendar['weeks'] as $weekIndex => $week)
                        <div class="grid grid-cols-7 border-b border-gray-200 last:border-b-0 dark:border-gray-700" wire:key="kitchen-week-{{ $month }}-{{ $weekIndex }}">
                            @foreach($week as $day)
                                <div wire:key="kitchen-day-{{ $day['date']->toDateString() }}" x-data="{ expanded: false }" @class(['flex min-h-28 min-w-0 flex-col gap-1 border-r border-gray-100 p-1.5 last:border-r-0 dark:border-gray-800', 'bg-gray-50/70 dark:bg-gray-800/30' => ! $day['isCurrentMonth']])>
                                    <div class="flex justify-end px-1 py-0.5 text-xs">
                                        <span @class(['inline-flex h-6 w-6 items-center justify-center rounded-full', 'bg-blue-600 font-bold text-white' => $day['isToday'], 'text-gray-700 dark:text-gray-200' => $day['isCurrentMonth'] && ! $day['isToday'], 'text-gray-300 dark:text-gray-600' => ! $day['isCurrentMonth']])>{{ $day['date']->day }}</span>
                                    </div>
                                    @foreach($day['orders'] as $index => $order)
                                        <button type="button" wire:key="kitchen-po-chip-{{ $order->id }}" wire:click="openProductionOrder({{ $order->id }})" x-show="expanded || {{ $index < 3 ? 'true' : 'false' }}" title="Production Order {{ $order->production_order_num }} · {{ $order->branch?->name }} · {{ $order->label() }} · {{ $order->details_count }} item" class="w-full rounded-md border px-1.5 py-1 text-left text-[11px] focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 {{ $chipClass }}">
                                            <span class="flex min-w-0 items-center gap-1"><span class="{{ $esbBadge }}">ESB</span><span class="truncate font-bold">{{ $order->production_order_num }}</span></span>
                                            <span class="block truncate opacity-80">{{ $order->branch?->name }} · {{ $order->label() }} · {{ $order->details_synced_at ? $order->details_count.' item' : 'detail diproses' }}</span>
                                        </button>
                                    @endforeach
                                    @if(count($day['orders']) > 3)
                                        <button type="button" x-on:click="expanded = ! expanded" x-text="expanded ? 'Tampilkan lebih sedikit' : '+{{ count($day['orders']) - 3 }} lainnya'" class="rounded-md px-1.5 py-0.5 text-left text-[11px] font-semibold text-blue-600 hover:underline dark:text-blue-400">+{{ count($day['orders']) - 3 }} lainnya</button>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>

                {{-- Mobile: agenda built from the same dataset --}}
                <div class="divide-y divide-gray-100 md:hidden dark:divide-gray-800">
                    @forelse($calendar['agenda'] as $day)
                        <div class="p-4" wire:key="kitchen-agenda-{{ $day['date']->toDateString() }}">
                            <p class="mb-2 text-xs font-bold uppercase tracking-wide text-gray-500">{{ $day['date']->locale('id')->translatedFormat('l, d F Y') }}</p>
                            <div class="space-y-2">
                                @foreach($day['orders'] as $order)
                                    <button type="button" wire:key="kitchen-po-agenda-{{ $order->id }}" wire:click="openProductionOrder({{ $order->id }})" class="block w-full rounded-lg border px-3 py-2 text-left text-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 {{ $chipClass }}">
                                        <span class="flex items-start justify-between gap-2">
                                            <span class="flex min-w-0 items-center gap-1.5"><span class="{{ $esbBadge }}">ESB</span><span class="min-w-0 break-words font-bold">{{ $order->production_order_num }}</span></span>
                                            <span class="shrink-0 text-xs font-semibold">{{ $order->label() }}</span>
                                        </span>
                                        <span class="mt-1 block break-words text-xs opacity-80">{{ $order->branch?->name }} · {{ $order->details_synced_at ? $order->details_count.' item' : 'detail diproses' }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <p class="p-8 text-center text-sm text-gray-500 dark:text-gray-400">{{ $emptyMessage }}</p>
                    @endforelse
                </div>
            @endif
        </div>
    </section>

    {{-- Production Order detail: read-only, close is the only action (prd.md §34.3) --}}
    @if($productionOrderId !== null)
        @php($poDetail = $this->productionOrderDetail)
        <div x-data x-trap.inert.noscroll="true" x-on:keydown.escape.window="$wire.closeModal()" class="fixed inset-0 z-[130] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="kitchen-po-title">
            <button type="button" wire:click="closeModal" class="absolute inset-0 bg-gray-950/60" aria-label="Tutup detail Production Order" tabindex="-1"></button>
            @if(! $poDetail)
                <x-rnd.picker-modal title="Production Order ESB" maxWidth="5xl">
                    <x-slot:close><button type="button" wire:click="closeModal" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800" aria-label="Tutup"><x-heroicon-o-x-mark class="h-5 w-5" /></button></x-slot:close>
                    <h4 id="kitchen-po-title" class="sr-only">Production Order ESB</h4>
                    <p class="p-8 text-center text-sm text-gray-500 dark:text-gray-400">Production Order tidak ditemukan atau Anda tidak lagi memiliki akses.</p>
                </x-rnd.picker-modal>
            @else
                @php($order = $poDetail['order'])
                <x-rnd.picker-modal :title="'Production Order ESB · '.$order->production_order_num" :description="$order->production_order_date->locale('id')->translatedFormat('l, d F Y').' · '.($order->branch?->name ?? '-')" maxWidth="5xl">
                    <x-slot:close><button type="button" wire:click="closeModal" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800" aria-label="Tutup detail Production Order"><x-heroicon-o-x-mark class="h-5 w-5" /></button></x-slot:close>
                    <div class="min-h-0 flex-1 space-y-5 overflow-y-auto p-5">
                        <h4 id="kitchen-po-title" class="sr-only">Detail Production Order {{ $order->production_order_num }}</h4>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded bg-indigo-600 px-1.5 py-0.5 text-xs font-bold text-white dark:bg-indigo-500">ESB</span>
                            <span class="rounded-full bg-indigo-100 px-2.5 py-1 text-xs font-semibold text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-200">{{ $order->label() }}</span>
                            <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-300">Hanya baca — perubahan dilakukan di ESB</span>
                        </div>
                        @if($poDetail['notSeenInLatestSync'])
                            <p class="rounded-lg border px-3 py-2 text-sm {{ $toneClass['amber'] }}" role="status">Tidak terlihat pada sinkronisasi terakhir. Data di bawah adalah data terakhir yang tersimpan.</p>
                        @endif

                        <dl class="grid gap-3 rounded-xl border border-gray-200 p-4 text-sm dark:border-gray-700 sm:grid-cols-2">
                            <div><dt class="text-xs text-gray-500">Tipe BOM</dt><dd class="text-gray-900 dark:text-white">{{ $order->bom_type_name ?? '-' }}</dd></div>
                            <div><dt class="text-xs text-gray-500">Branch ESB</dt><dd class="break-words text-gray-900 dark:text-white">{{ $order->esb_branch_name ?? '-' }} · {{ $order->company_code }}</dd></div>
                            @if($order->created_by_name || $order->esb_created_at)<div><dt class="text-xs text-gray-500">Dibuat di ESB</dt><dd class="text-gray-900 dark:text-white">{{ $order->created_by_name ?? '-' }}{{ $order->esb_created_at ? ' · '.$order->esb_created_at->format('d M Y H:i') : '' }}</dd></div>@endif
                            @if($order->edited_by_name || $order->esb_edited_at)<div><dt class="text-xs text-gray-500">Diubah di ESB</dt><dd class="text-gray-900 dark:text-white">{{ $order->edited_by_name ?? '-' }}{{ $order->esb_edited_at ? ' · '.$order->esb_edited_at->format('d M Y H:i') : '' }}</dd></div>@endif
                            @if($order->authorized_by_name || $order->esb_authorized_at)<div><dt class="text-xs text-gray-500">Diotorisasi di ESB</dt><dd class="text-gray-900 dark:text-white">{{ $order->authorized_by_name ?? '-' }}{{ $order->esb_authorized_at ? ' · '.$order->esb_authorized_at->format('d M Y H:i') : '' }}</dd></div>@endif
                            <div><dt class="text-xs text-gray-500">Terakhir disinkronkan</dt><dd class="text-gray-900 dark:text-white">{{ $order->source_last_seen_at?->timezone(config('app.timezone'))->format('d M Y H:i') ?? '-' }}</dd></div>
                            @if($order->additional_info)<div class="sm:col-span-2"><dt class="text-xs text-gray-500">Informasi Tambahan</dt><dd class="whitespace-pre-line break-words text-gray-900 dark:text-white">{{ $order->additional_info }}</dd></div>@endif
                        </dl>

                        <div>
                            <h4 class="mb-2 text-sm font-semibold text-gray-800 dark:text-gray-100">Detail Produksi ({{ $order->details->count() }})</h4>
                            @if($poDetail['detailsPending'])
                                <p class="rounded-xl border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">Detail Production Order ini sedang diambil dari ESB.</p>
                            @else
                                <div class="divide-y divide-gray-100 overflow-hidden rounded-xl border border-gray-200 dark:divide-gray-800 dark:border-gray-700">
                                    @forelse($order->details as $detail)
                                        <div class="p-3" wire:key="po-detail-{{ $detail->id }}" x-data="{ open: false }">
                                            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                                <div class="min-w-0">
                                                    <p class="break-words text-sm font-semibold text-gray-900 dark:text-white"><span class="mr-1 text-xs text-gray-400">#{{ $detail->sequence }}</span>{{ $detail->bom_name ?? $detail->product_name ?? '-' }}</p>
                                                    <p class="break-words text-xs text-gray-500 dark:text-gray-400">Produk: {{ trim(($detail->product_code ?? '').' · '.($detail->product_name ?? ''), ' ·') ?: '-' }}</p>
                                                    @if($detail->notes)<p class="mt-1 whitespace-pre-line break-words text-xs text-gray-600 dark:text-gray-300">{{ $detail->notes }}</p>@endif
                                                </div>
                                                <div class="flex shrink-0 flex-wrap items-center gap-2 sm:justify-end">
                                                    @if($detail->is_finished !== null)
                                                        <span @class(['rounded-full px-2 py-0.5 text-[11px] font-semibold', 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' => $detail->is_finished, 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300' => ! $detail->is_finished])>{{ $detail->is_finished ? 'Selesai' : 'Belum selesai' }}</span>
                                                    @endif
                                                    <p class="text-sm font-bold tabular-nums text-gray-900 dark:text-white">{{ \App\Filament\Helpdesk\Pages\KitchenProductionSchedulePage::formatQuantity($detail->quantity) }} <span class="text-xs font-semibold text-gray-500">{{ $detail->uom_name }}</span></p>
                                                </div>
                                            </div>
                                            @if(! empty($detail->materials))
                                                <button type="button" x-on:click="open = ! open" x-bind:aria-expanded="open.toString()" aria-controls="po-materials-{{ $detail->id }}" class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-indigo-700 hover:underline dark:text-indigo-300">
                                                    <x-heroicon-o-chevron-down class="h-3.5 w-3.5 transition" x-bind:class="open && 'rotate-180'" /> Material ({{ count($detail->materials) }})
                                                </button>
                                                <div id="po-materials-{{ $detail->id }}" x-show="open" x-cloak class="mt-2 overflow-x-auto rounded-lg border border-gray-100 dark:border-gray-800">
                                                    <table class="min-w-full divide-y divide-gray-100 text-xs dark:divide-gray-800">
                                                        <thead class="bg-gray-50 text-left font-semibold uppercase tracking-wide text-gray-500 dark:bg-gray-800/60"><tr><th class="px-3 py-1.5">Kode</th><th class="px-3 py-1.5">Material</th><th class="px-3 py-1.5 text-right">Qty</th><th class="px-3 py-1.5">Satuan</th></tr></thead>
                                                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                                            @foreach($detail->materials as $material)
                                                                <tr><td class="px-3 py-1.5 text-gray-700 dark:text-gray-300">{{ $material['product_code'] ?? '-' }}</td><td class="px-3 py-1.5 text-gray-900 dark:text-white">{{ $material['product_name'] ?? '-' }}</td><td class="px-3 py-1.5 text-right tabular-nums text-gray-900 dark:text-white">{{ \App\Filament\Helpdesk\Pages\KitchenProductionSchedulePage::formatQuantity($material['quantity'] ?? null) ?: '-' }}</td><td class="px-3 py-1.5 text-gray-700 dark:text-gray-300">{{ $material['uom_name'] ?? '-' }}</td></tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                            @endif
                                        </div>
                                    @empty
                                        <p class="p-4 text-center text-sm text-gray-500 dark:text-gray-400">ESB tidak mengirim detail untuk Production Order ini.</p>
                                    @endforelse
                                </div>
                            @endif
                        </div>
                    </div>
                    <div class="flex flex-col-reverse gap-2 border-t border-gray-200 px-5 py-4 dark:border-gray-700 sm:flex-row sm:justify-end">
                        <button type="button" wire:click="closeModal" class="h-9 rounded-lg border border-gray-300 px-4 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Tutup</button>
                    </div>
                </x-rnd.picker-modal>
            @endif
        </div>
    @endif
</x-filament-panels::page>
