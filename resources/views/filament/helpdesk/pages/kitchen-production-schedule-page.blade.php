<x-filament-panels::page>
    @php
        $calendar = $this->calendar;
        $filterBranches = $this->filterBranches;
        $canCreate = $this->canCreate;
        $statuses = \App\Enums\KitchenProductionScheduleStatus::cases();
        $inputClass = 'w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white';
        $chipClass = fn (\App\Enums\KitchenProductionScheduleStatus $status): string => match ($status) {
            \App\Enums\KitchenProductionScheduleStatus::Scheduled => 'border-emerald-200 bg-emerald-50 text-emerald-800 hover:bg-emerald-100 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200 dark:hover:bg-emerald-950/70',
            \App\Enums\KitchenProductionScheduleStatus::Cancelled => 'border-red-200 bg-red-50 text-red-700 line-through decoration-red-300 hover:bg-red-100 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200 dark:hover:bg-red-950/70',
        };
        $badgeClass = fn (\App\Enums\KitchenProductionScheduleStatus $status): string => match ($status) {
            \App\Enums\KitchenProductionScheduleStatus::Scheduled => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
            \App\Enums\KitchenProductionScheduleStatus::Cancelled => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
        };
    @endphp

    <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900" aria-labelledby="kitchen-schedule-heading">
        {{-- Header: identity, primary action --}}
        <div class="flex flex-col gap-4 border-b border-gray-200 p-5 dark:border-gray-700 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex min-w-0 items-start gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-950/40 dark:text-blue-300">
                    <x-heroicon-o-calendar-days class="h-6 w-6" />
                </div>
                <div class="min-w-0">
                    <h2 id="kitchen-schedule-heading" class="text-lg font-bold text-gray-900 dark:text-white">Kalender Produksi</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Klik tanggal untuk membuat jadwal, atau klik jadwal untuk melihat detail.</p>
                </div>
            </div>
            @if($canCreate)
                <button type="button" wire:click="openCreate" wire:loading.attr="disabled" wire:target="openCreate" class="inline-flex h-9 shrink-0 items-center justify-center gap-1.5 whitespace-nowrap rounded-lg bg-blue-600 px-3 text-sm font-semibold text-white transition hover:bg-blue-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 disabled:opacity-60 dark:focus-visible:ring-offset-gray-900">
                    <x-heroicon-o-plus class="h-4 w-4 shrink-0" /> Buat Jadwal
                </button>
            @endif
        </div>

        {{-- Toolbar: month navigation, filters, legend --}}
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
                        <option value="">Semua Branch Kitchen</option>
                        @foreach($filterBranches as $branch)
                            <option value="{{ $branch->id }}">{{ $branch->name }}{{ $branch->is_active ? '' : ' (nonaktif)' }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="kitchen-filter-status" class="mb-1 block text-xs font-semibold text-gray-600 dark:text-gray-300">Status</label>
                    <select id="kitchen-filter-status" wire:model.live="filterStatus" class="{{ $inputClass }} lg:w-44">
                        <option value="">Semua</option>
                        @foreach($statuses as $status)
                            <option value="{{ $status->value }}">{{ $status->getLabel() }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-x-4 gap-y-2 border-b border-gray-200 px-5 py-2.5 text-xs text-gray-600 dark:border-gray-700 dark:text-gray-300" aria-label="Legenda status">
            @foreach($statuses as $status)
                <span class="inline-flex items-center gap-1.5"><span class="rounded-full px-2 py-0.5 font-semibold {{ $badgeClass($status) }}">{{ $status->getLabel() }}</span></span>
            @endforeach
            <span class="text-gray-400 dark:text-gray-500">· Jumlah BOM per jadwal, bukan total quantity (satuan berbeda tidak dijumlahkan).</span>
        </div>

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
                    <p class="mt-2 text-sm font-semibold text-gray-800 dark:text-gray-100">Belum ada Branch aktif yang dapat Anda akses.</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Hubungi administrator untuk memberikan akses Branch atau memastikan Branch masih aktif.</p>
                </div>
            @else
                @if($calendar['total'] === 0)
                    <div class="flex flex-col gap-2 border-b border-gray-200 bg-gray-50 px-5 py-3 text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-800/40 dark:text-gray-300 sm:flex-row sm:items-center sm:justify-between" role="status">
                        @if($this->hasActiveFilters())
                            <span>Tidak ada jadwal yang cocok dengan filter pada bulan ini.</span>
                            <button type="button" wire:click="resetFilters" class="text-left text-sm font-semibold text-blue-600 hover:underline dark:text-blue-400">Reset filter</button>
                        @else
                            <span>Belum ada jadwal produksi pada bulan ini.@if($canCreate) Klik tanggal untuk membuat jadwal.@endif</span>
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
                                @php($dateKey = $day['date']->toDateString())
                                <div wire:key="kitchen-day-{{ $dateKey }}" x-data="{ expanded: false }" @class(['flex min-h-28 min-w-0 flex-col gap-1 border-r border-gray-100 p-1.5 last:border-r-0 dark:border-gray-800', 'bg-gray-50/70 dark:bg-gray-800/30' => ! $day['isCurrentMonth']])>
                                    @if($canCreate)
                                        <button type="button" wire:click="openCreate('{{ $dateKey }}')" class="group flex items-center justify-end rounded-md px-1 py-0.5 text-xs hover:bg-blue-50/60 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 dark:hover:bg-blue-950/20" aria-label="Buat jadwal pada {{ $day['date']->locale('id')->translatedFormat('d F Y') }}">
                                            <x-heroicon-o-plus class="mr-auto h-3.5 w-3.5 text-blue-500 opacity-0 group-hover:opacity-100 group-focus-visible:opacity-100" />
                                            <span @class(['inline-flex h-6 w-6 items-center justify-center rounded-full', 'bg-blue-600 font-bold text-white' => $day['isToday'], 'text-gray-700 dark:text-gray-200' => $day['isCurrentMonth'] && ! $day['isToday'], 'text-gray-300 dark:text-gray-600' => ! $day['isCurrentMonth']])>{{ $day['date']->day }}</span>
                                        </button>
                                    @else
                                        <div class="flex justify-end px-1 py-0.5 text-xs">
                                            <span @class(['inline-flex h-6 w-6 items-center justify-center rounded-full', 'bg-blue-600 font-bold text-white' => $day['isToday'], 'text-gray-700 dark:text-gray-200' => $day['isCurrentMonth'] && ! $day['isToday'], 'text-gray-300 dark:text-gray-600' => ! $day['isCurrentMonth']])>{{ $day['date']->day }}</span>
                                        </div>
                                    @endif
                                    @foreach($day['schedules'] as $index => $schedule)
                                        <button type="button" wire:key="kitchen-chip-{{ $schedule->id }}" wire:click="openDetail({{ $schedule->id }})" x-show="expanded || {{ $index < 3 ? 'true' : 'false' }}" title="{{ $schedule->branch?->name }} · {{ $schedule->status->getLabel() }} · {{ $schedule->items_count }} BOM Assembly" class="w-full rounded-md border px-1.5 py-1 text-left text-[11px] focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 {{ $chipClass($schedule->status) }}">
                                            <span class="block truncate font-bold">{{ $schedule->branch?->name }}</span>
                                            <span class="block truncate opacity-80">{{ $schedule->status->getLabel() }} · {{ $schedule->items_count }} BOM Assembly</span>
                                        </button>
                                    @endforeach
                                    @if(count($day['schedules']) > 3)
                                        <button type="button" x-on:click="expanded = ! expanded" x-text="expanded ? 'Tampilkan lebih sedikit' : '+{{ count($day['schedules']) - 3 }} lainnya'" class="rounded-md px-1.5 py-0.5 text-left text-[11px] font-semibold text-blue-600 hover:underline dark:text-blue-400">+{{ count($day['schedules']) - 3 }} lainnya</button>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>

                {{-- Mobile: agenda built from the same dataset --}}
                <div class="divide-y divide-gray-100 md:hidden dark:divide-gray-800">
                    @if($canCreate)
                        <div class="p-4">
                            <button type="button" wire:click="openCreate" class="inline-flex h-10 w-full items-center justify-center gap-1.5 rounded-lg border border-dashed border-blue-300 text-sm font-semibold text-blue-700 hover:bg-blue-50 dark:border-blue-800 dark:text-blue-300 dark:hover:bg-blue-950/30"><x-heroicon-o-plus class="h-4 w-4" /> Buat Jadwal</button>
                        </div>
                    @endif
                    @forelse($calendar['agenda'] as $day)
                        <div class="p-4" wire:key="kitchen-agenda-{{ $day['date']->toDateString() }}">
                            <p class="mb-2 text-xs font-bold uppercase tracking-wide text-gray-500">{{ $day['date']->locale('id')->translatedFormat('l, d F Y') }}</p>
                            <div class="space-y-2">
                                @foreach($day['schedules'] as $schedule)
                                    <button type="button" wire:click="openDetail({{ $schedule->id }})" class="block w-full rounded-lg border px-3 py-2 text-left text-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 {{ $chipClass($schedule->status) }}">
                                        <span class="flex items-start justify-between gap-2">
                                            <span class="min-w-0 break-words font-bold">{{ $schedule->branch?->name }}</span>
                                            <span class="shrink-0 text-xs font-semibold">{{ $schedule->status->getLabel() }}</span>
                                        </span>
                                        <span class="mt-1 block text-xs opacity-80">{{ $schedule->items_count }} BOM Assembly</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <p class="p-8 text-center text-sm text-gray-500 dark:text-gray-400">{{ $this->hasActiveFilters() ? 'Tidak ada jadwal yang cocok dengan filter pada bulan ini.' : 'Belum ada jadwal produksi pada bulan ini.' }}</p>
                    @endforelse
                </div>
            @endif
        </div>
    </section>

    {{-- Editor dialog (form ↔ BOM picker as steps of the same dialog) --}}
    @if($modal === 'editor')
        @php($schedulableBranches = $this->schedulableBranches)
        <div x-data x-trap.inert.noscroll="true" x-on:keydown.escape.window="{{ $step === 'picker' ? '$wire.closePicker()' : '$wire.closeModal()' }}" class="fixed inset-0 z-[130] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="kitchen-editor-title">
            <button type="button" wire:click="closeModal" class="absolute inset-0 bg-gray-950/60" aria-label="Tutup editor jadwal" tabindex="-1"></button>

            @if($step === 'picker')
                @php($results = $this->pickerResults)
                <x-rnd.picker-modal title="Pilih BOM Assembly" description="Hanya BOM Assembly aktif dan tersinkron yang dapat dipilih. Pilihan tetap tersimpan saat berpindah halaman." maxWidth="6xl">
                    <x-slot:close><button type="button" wire:click="closePicker" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800" aria-label="Kembali ke editor"><x-heroicon-o-x-mark class="h-5 w-5" /></button></x-slot:close>
                    <div class="border-b border-gray-200 px-5 py-3 dark:border-gray-700">
                        <label for="kitchen-picker-search" class="sr-only">Cari BOM</label>
                        <input id="kitchen-picker-search" type="search" wire:model.live.debounce.400ms="pickerSearch" placeholder="Cari kode/nama BOM atau produk hasil..." class="{{ $inputClass }}" autofocus>
                    </div>
                    <div class="relative min-h-0 flex-1 overflow-y-auto">
                        <div wire:loading.flex wire:target="pickerSearch, goToPickerPage" class="absolute inset-0 z-10 items-start justify-center bg-white/70 pt-10 dark:bg-gray-900/70"><x-filament::loading-indicator class="h-5 w-5" /></div>
                        @if($results->isEmpty())
                            <p class="p-10 text-center text-sm text-gray-500 dark:text-gray-400">{{ filled($pickerSearch) ? 'Tidak ada BOM Assembly yang cocok dengan pencarian.' : 'Belum ada BOM Assembly yang dapat dipilih. Sinkronkan katalog terlebih dahulu.' }}</p>
                        @else
                            @php($alreadyAdded = collect($form['items'])->pluck('rnd_bom_catalog_id')->all())
                            <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                                <thead class="sticky top-0 bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-gray-800">
                                    <tr><th class="w-10 px-4 py-2"><span class="sr-only">Pilih</span></th><th class="px-3 py-2">BOM</th><th class="hidden px-3 py-2 sm:table-cell">Produk Hasil</th><th class="px-3 py-2">Satuan</th><th class="hidden px-3 py-2 lg:table-cell">Komponen</th><th class="hidden px-3 py-2 lg:table-cell">Sinkron Terakhir</th></tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                    @foreach($results as $catalog)
                                        @php($isAdded = in_array($catalog->id, $alreadyAdded, true))
                                        <tr wire:key="picker-row-{{ $catalog->id }}" @class(['opacity-60' => $isAdded])>
                                            <td class="px-4 py-2">
                                                @if($isAdded)
                                                    <x-heroicon-o-check class="h-4 w-4 text-gray-400" />
                                                @else
                                                    <input type="checkbox" wire:click="togglePick({{ $catalog->id }})" @checked(in_array($catalog->id, $pickerSelected, true)) aria-label="Pilih {{ $catalog->bom_code }}" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600">
                                                @endif
                                            </td>
                                            <td class="px-3 py-2"><span class="block font-semibold text-gray-900 dark:text-white">{{ $catalog->bom_code }}</span><span class="block text-xs text-gray-500 dark:text-gray-400">{{ $catalog->bom_name }}</span>@if($isAdded)<span class="mt-0.5 inline-block rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-semibold text-gray-600 dark:bg-gray-800 dark:text-gray-300">Sudah ditambahkan</span>@endif</td>
                                            <td class="hidden px-3 py-2 sm:table-cell"><span class="block text-gray-800 dark:text-gray-200">{{ $catalog->product_code }}</span><span class="block text-xs text-gray-500 dark:text-gray-400">{{ $catalog->product_name }}</span></td>
                                            <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ $catalog->uom_name }}</td>
                                            <td class="hidden px-3 py-2 tabular-nums text-gray-700 lg:table-cell dark:text-gray-300">{{ $catalog->component_count }}</td>
                                            <td class="hidden px-3 py-2 text-xs text-gray-500 lg:table-cell dark:text-gray-400">{{ $catalog->last_synced_at?->timezone(config('app.timezone'))->format('d M Y H:i') ?? '-' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>
                    <div class="flex flex-col gap-3 border-t border-gray-200 px-5 py-3 dark:border-gray-700 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                            <button type="button" wire:click="goToPickerPage({{ $results->currentPage() - 1 }})" @disabled($results->onFirstPage()) class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 disabled:opacity-40 dark:border-gray-700" aria-label="Halaman sebelumnya"><x-heroicon-o-chevron-left class="h-4 w-4" /></button>
                            <span>{{ $results->total() === 0 ? '0' : $results->firstItem().'–'.$results->lastItem() }} dari {{ $results->total() }}</span>
                            <button type="button" wire:click="goToPickerPage({{ $results->currentPage() + 1 }})" @disabled(! $results->hasMorePages()) class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 disabled:opacity-40 dark:border-gray-700" aria-label="Halaman berikutnya"><x-heroicon-o-chevron-right class="h-4 w-4" /></button>
                        </div>
                        <div class="flex justify-end gap-2">
                            <button type="button" wire:click="closePicker" class="h-9 rounded-lg border border-gray-300 px-4 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Kembali</button>
                            <button type="button" wire:click="addPicked" wire:loading.attr="disabled" wire:target="addPicked" @disabled($pickerSelected === []) class="h-9 rounded-lg bg-blue-600 px-4 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50">Tambahkan {{ count($pickerSelected) }} BOM</button>
                        </div>
                    </div>
                </x-rnd.picker-modal>
            @else
                <x-rnd.picker-modal :title="$editingId ? 'Ubah Jadwal Produksi' : 'Buat Jadwal Produksi'" description="Pilih BOM Assembly dan isi jumlah produksi untuk tanggal ini." maxWidth="5xl">
                    <x-slot:close><button type="button" wire:click="closeModal" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800" aria-label="Tutup editor jadwal"><x-heroicon-o-x-mark class="h-5 w-5" /></button></x-slot:close>
                    <form wire:submit="saveSchedule" class="flex min-h-0 flex-1 flex-col">
                        <div class="min-h-0 flex-1 space-y-5 overflow-y-auto p-5">
                            @error('form')<p class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-300" role="alert">{{ $message }}</p>@enderror
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label for="kitchen-form-date" class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Tanggal Produksi <span class="text-red-500">*</span></label>
                                    <input id="kitchen-form-date" type="date" wire:model="form.production_date" class="{{ $inputClass }}">
                                    @error('form.production_date')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="kitchen-form-branch" class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Branch Kitchen <span class="text-red-500">*</span></label>
                                    <select id="kitchen-form-branch" wire:model="form.branch_id" class="{{ $inputClass }}">
                                        <option value="">— Pilih Branch Kitchen —</option>
                                        @foreach($schedulableBranches as $branch)
                                            <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('form.branch_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                </div>
                                <div class="sm:col-span-2">
                                    <label for="kitchen-form-notes" class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Catatan Jadwal</label>
                                    <textarea id="kitchen-form-notes" wire:model="form.notes" rows="2" maxlength="2000" class="{{ $inputClass }}"></textarea>
                                    @error('form.notes')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                </div>
                            </div>

                            <div>
                                <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                                    <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-100">BOM Assembly <span class="font-normal text-gray-500">({{ count($form['items']) }})</span></h4>
                                    <button type="button" wire:click="openPicker" class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-blue-200 bg-blue-50 px-3 text-xs font-semibold text-blue-700 hover:bg-blue-100 dark:border-blue-900 dark:bg-blue-950/30 dark:text-blue-300"><x-heroicon-o-plus class="h-4 w-4" /> Tambah BOM Assembly</button>
                                </div>
                                @error('form.items')<p class="mb-2 text-xs text-red-600">{{ $message }}</p>@enderror
                                @forelse($form['items'] as $index => $item)
                                    <div wire:key="editor-item-{{ $item['rnd_bom_catalog_id'] }}" class="mb-2 rounded-xl border border-gray-200 p-3 dark:border-gray-700">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <p class="text-xs font-semibold text-gray-400">#{{ $index + 1 }}</p>
                                                <p class="break-words text-sm font-semibold text-gray-900 dark:text-white">{{ $item['label'] }}</p>
                                                <p class="break-words text-xs text-gray-500 dark:text-gray-400">Hasil: {{ $item['product'] }}</p>
                                            </div>
                                            <div class="flex shrink-0 items-center gap-1">
                                                <button type="button" wire:click="moveItem({{ $index }}, -1)" @disabled($index === 0) class="rounded-md p-1.5 text-gray-500 hover:bg-gray-100 disabled:opacity-30 dark:hover:bg-gray-800" aria-label="Naikkan urutan {{ $item['label'] }}"><x-heroicon-o-arrow-up class="h-4 w-4" /></button>
                                                <button type="button" wire:click="moveItem({{ $index }}, 1)" @disabled($index === count($form['items']) - 1) class="rounded-md p-1.5 text-gray-500 hover:bg-gray-100 disabled:opacity-30 dark:hover:bg-gray-800" aria-label="Turunkan urutan {{ $item['label'] }}"><x-heroicon-o-arrow-down class="h-4 w-4" /></button>
                                                <button type="button" wire:click="removeItem({{ $index }})" class="rounded-md p-1.5 text-red-500 hover:bg-red-50 dark:hover:bg-red-950/30" aria-label="Hapus {{ $item['label'] }}"><x-heroicon-o-trash class="h-4 w-4" /></button>
                                            </div>
                                        </div>
                                        @if($item['notice'])
                                            <div class="mt-2 flex flex-col gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200 sm:flex-row sm:items-center sm:justify-between">
                                                <span>{{ $item['notice'] }}</span>
                                            </div>
                                        @endif
                                        @error("form.items.{$index}.rnd_bom_catalog_id")<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
                                        <div class="mt-3 grid gap-3 sm:grid-cols-[12rem_minmax(0,1fr)]">
                                            <div>
                                                <label for="kitchen-item-qty-{{ $index }}" class="mb-1 block text-xs font-semibold text-gray-600 dark:text-gray-300">Jumlah Produksi <span class="text-red-500">*</span></label>
                                                <div class="flex items-stretch">
                                                    <input id="kitchen-item-qty-{{ $index }}" type="text" inputmode="decimal" wire:model="form.items.{{ $index }}.planned_quantity" class="{{ $inputClass }} rounded-r-none" placeholder="0">
                                                    <span class="inline-flex items-center rounded-r-lg border border-l-0 border-gray-300 bg-gray-50 px-2.5 text-xs font-semibold text-gray-600 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ $item['uom'] }}</span>
                                                </div>
                                                @error("form.items.{$index}.planned_quantity")<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                            </div>
                                            <div>
                                                <label for="kitchen-item-notes-{{ $index }}" class="mb-1 block text-xs font-semibold text-gray-600 dark:text-gray-300">Catatan BOM</label>
                                                <input id="kitchen-item-notes-{{ $index }}" type="text" maxlength="1000" wire:model="form.items.{{ $index }}.notes" class="{{ $inputClass }}">
                                                @error("form.items.{$index}.notes")<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <p class="rounded-xl border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">Belum ada BOM. Klik <span class="font-semibold">Tambah BOM Assembly</span> untuk memilih dari katalog.</p>
                                @endforelse
                            </div>
                        </div>
                        <div class="flex flex-col-reverse gap-2 border-t border-gray-200 px-5 py-4 dark:border-gray-700 sm:flex-row sm:justify-end">
                            <button type="button" wire:click="closeModal" class="h-9 rounded-lg border border-gray-300 px-4 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Batal</button>
                            <button type="submit" wire:loading.attr="disabled" wire:target="saveSchedule" class="h-9 rounded-lg bg-blue-600 px-4 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60">{{ $editingId ? 'Simpan Perubahan' : 'Simpan' }}</button>
                        </div>
                    </form>
                </x-rnd.picker-modal>
            @endif
        </div>
    @endif

    {{-- Detail dialog (detail ↔ cancel confirmation as steps of the same dialog) --}}
    @if($modal === 'detail')
        @php($detail = $this->detail)
        <div x-data x-trap.inert.noscroll="true" x-on:keydown.escape.window="{{ $step === 'view' ? '$wire.closeModal()' : '$wire.showDetailStep(\'view\')' }}" class="fixed inset-0 z-[130] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="kitchen-detail-title">
            <button type="button" wire:click="closeModal" class="absolute inset-0 bg-gray-950/60" aria-label="Tutup detail jadwal" tabindex="-1"></button>
            @if(! $detail)
                <x-rnd.picker-modal title="Detail Jadwal" maxWidth="5xl">
                    <x-slot:close><button type="button" wire:click="closeModal" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800" aria-label="Tutup"><x-heroicon-o-x-mark class="h-5 w-5" /></button></x-slot:close>
                    <p class="p-8 text-center text-sm text-gray-500 dark:text-gray-400">Jadwal tidak ditemukan atau Anda tidak lagi memiliki akses.</p>
                </x-rnd.picker-modal>
            @else
                @php($schedule = $detail['schedule'])
                <x-rnd.picker-modal :title="'Jadwal Produksi · '.($schedule->branch?->name ?? '-')" :description="$schedule->production_date->locale('id')->translatedFormat('l, d F Y')" maxWidth="5xl">
                    <x-slot:close><button type="button" wire:click="closeModal" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800" aria-label="Tutup detail jadwal"><x-heroicon-o-x-mark class="h-5 w-5" /></button></x-slot:close>
                    <div class="min-h-0 flex-1 space-y-5 overflow-y-auto p-5">
                        @if($step === 'cancel')
                            <div class="space-y-3">
                                <h4 id="kitchen-detail-title" class="text-base font-bold text-gray-900 dark:text-white">Batalkan jadwal ini?</h4>
                                <p class="text-sm text-gray-600 dark:text-gray-300">Jadwal tidak dihapus. Item, snapshot, dan riwayat tetap tersimpan dan dapat dilihat melalui filter Cancelled.</p>
                                <label for="kitchen-cancel-reason" class="block text-sm font-semibold text-gray-700 dark:text-gray-200">Alasan Pembatalan <span class="text-red-500">*</span></label>
                                <textarea id="kitchen-cancel-reason" wire:model="cancelReason" rows="3" maxlength="1000" class="{{ $inputClass }}"></textarea>
                                @error('cancelReason')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                        @else
                            <h4 id="kitchen-detail-title" class="sr-only">Detail jadwal</h4>
                        @endif

                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $badgeClass($schedule->status) }}">{{ $schedule->status->getLabel() }}</span>
                            @if(! $schedule->branch?->is_active)<span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">Branch tidak lagi aktif</span>@endif
                        </div>

                        <dl class="grid gap-3 rounded-xl border border-gray-200 p-4 text-sm dark:border-gray-700 sm:grid-cols-2">
                            <div><dt class="text-xs text-gray-500">Dibuat</dt><dd class="text-gray-900 dark:text-white">{{ $schedule->creator?->name ?? '-' }} · {{ $schedule->created_at?->timezone(config('app.timezone'))->format('d M Y H:i') }}</dd></div>
                            <div><dt class="text-xs text-gray-500">Terakhir diubah</dt><dd class="text-gray-900 dark:text-white">{{ $schedule->updater?->name ?? '-' }} · {{ $schedule->updated_at?->timezone(config('app.timezone'))->format('d M Y H:i') }}</dd></div>
                            @if($schedule->cancelled_at)<div><dt class="text-xs text-gray-500">Cancelled</dt><dd class="text-gray-900 dark:text-white">{{ $schedule->canceller?->name ?? '-' }} · {{ $schedule->cancelled_at->timezone(config('app.timezone'))->format('d M Y H:i') }}</dd></div>@endif
                            @if($schedule->cancellation_reason)<div class="sm:col-span-2"><dt class="text-xs text-gray-500">Alasan Pembatalan</dt><dd class="whitespace-pre-line break-words text-gray-900 dark:text-white">{{ $schedule->cancellation_reason }}</dd></div>@endif
                            @if($schedule->notes)<div class="sm:col-span-2"><dt class="text-xs text-gray-500">Catatan</dt><dd class="whitespace-pre-line break-words text-gray-900 dark:text-white">{{ $schedule->notes }}</dd></div>@endif
                        </dl>

                        <div>
                            <h4 class="mb-2 text-sm font-semibold text-gray-800 dark:text-gray-100">BOM Assembly ({{ count($detail['items']) }})</h4>
                            <div class="divide-y divide-gray-100 overflow-hidden rounded-xl border border-gray-200 dark:divide-gray-800 dark:border-gray-700">
                                @forelse($detail['items'] as $row)
                                    @php($item = $row['item'])
                                    <div class="flex flex-col gap-2 p-3 sm:flex-row sm:items-start sm:justify-between" wire:key="detail-item-{{ $item->id }}">
                                        <div class="min-w-0">
                                            <p class="text-sm font-semibold text-gray-900 dark:text-white"><span class="mr-1 text-xs text-gray-400">#{{ $item->sequence }}</span>{{ $item->bom_code }} · {{ $item->bom_name }}</p>
                                            <p class="break-words text-xs text-gray-500 dark:text-gray-400">Hasil: {{ $item->product_code }} · {{ $item->product_name }}</p>
                                            @if($item->notes)<p class="mt-1 whitespace-pre-line break-words text-xs text-gray-600 dark:text-gray-300">{{ $item->notes }}</p>@endif
                                            @if($row['notice'])<p class="mt-1 text-xs font-medium text-amber-700 dark:text-amber-300">{{ $row['notice'] }}</p>@endif
                                        </div>
                                        <p class="shrink-0 text-sm font-bold tabular-nums text-gray-900 dark:text-white">{{ \App\Filament\Helpdesk\Pages\KitchenProductionSchedulePage::formatQuantity($item->planned_quantity) }} <span class="text-xs font-semibold text-gray-500">{{ $item->uom_name }}</span></p>
                                    </div>
                                @empty
                                    <p class="p-4 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada BOM pada jadwal ini.</p>
                                @endforelse
                            </div>
                        </div>

                    </div>
                    <div class="flex flex-col-reverse gap-2 border-t border-gray-200 px-5 py-4 dark:border-gray-700 sm:flex-row sm:justify-end">
                        @if($step === 'cancel')
                            <button type="button" wire:click="showDetailStep('view')" class="h-9 rounded-lg border border-gray-300 px-4 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Kembali</button>
                            <button type="button" wire:click="confirmCancel" wire:loading.attr="disabled" wire:target="confirmCancel" class="h-9 rounded-lg bg-red-600 px-4 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-60">Batalkan Jadwal</button>
                        @else
                            <button type="button" wire:click="closeModal" class="h-9 rounded-lg border border-gray-300 px-4 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Tutup</button>
                            @if($detail['canCancel'])
                                <button type="button" wire:click="showDetailStep('cancel')" class="h-9 rounded-lg border border-red-200 px-4 text-sm font-semibold text-red-700 hover:bg-red-50 dark:border-red-900 dark:text-red-300 dark:hover:bg-red-950/30">Batalkan</button>
                            @endif
                            @if($detail['canUpdate'])
                                <button type="button" wire:click="openEdit({{ $schedule->id }})" class="h-9 rounded-lg border border-blue-200 px-4 text-sm font-semibold text-blue-700 hover:bg-blue-50 dark:border-blue-900 dark:text-blue-300 dark:hover:bg-blue-950/30">Ubah Jadwal</button>
                            @endif
                        @endif
                    </div>
                </x-rnd.picker-modal>
            @endif
        </div>
    @endif
</x-filament-panels::page>
