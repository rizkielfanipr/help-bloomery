@php
    use App\Enums\ContentRequestStatus;
    use App\Models\Branch;

    $name = $column->getName();
    $label = $column->getLabel();
    $sortable = $column->isSortable() && ! $isReordering;
    $width = match ($name) {
        'code' => '10%',
        'created_at' => '13%',
        'requester.name' => '15%',
        'branch.name' => '13%',
        'judul_konten' => '18%',
        'jenis_konten' => '10%',
        'platform_tujuan' => '12%',
        'status' => '9%',
        default => null,
    };
    $inputClass = 'mt-2 block w-full rounded-md border border-gray-300 bg-white px-2.5 py-2 text-xs font-normal normal-case tracking-normal text-gray-900 shadow-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-900 dark:text-white';
@endphp

@once
    <span
        class="hidden"
        x-init="
            if (window.contentRequestSelectionSyncInstalled) return
            window.contentRequestSelectionSyncInstalled = true
            document.addEventListener('change', (event) => {
                if (! event.target.matches('.fi-ta-record-checkbox')) return
                setTimeout(() => {
                    const root = event.target.closest('[wire\\:id]')
                    const componentId = root?.getAttribute('wire:id')
                    const selectedKeys = [...root.querySelectorAll('.fi-ta-record-checkbox:checked')].map((checkbox) => checkbox.value)
                    if (componentId) Livewire.find(componentId)?.$set('selectedTableRecords', selectedKeys, true)
                }, 0)
            })
        "
    ></span>
    <style>
        .fi-ta-page-checkbox {
            display: none !important;
        }

        .fi-ta-selection-indicator {
            display: none !important;
        }

        .fi-ta-filters-trigger-action-ctn,
        .fi-ta-filter-indicators-trigger-action-ctn,
        .fi-ta-filters-above-content-ctn {
            display: none !important;
        }

        .fi-ta-table {
            width: 100% !important;
            min-width: 1200px;
        }

        .fi-ta-header-cell {
            padding-left: 0.75rem !important;
            padding-right: 0.75rem !important;
        }
    </style>
@endonce

<th class="fi-ta-header-cell align-top" @if($width) style="width: {{ $width }}" @endif>
    @if($sortable)
        <button
            type="button"
            wire:click="sortTable('{{ $name }}')"
            class="flex items-center gap-1 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
        >
            <span>{{ $label }}</span>
            <x-heroicon-o-chevron-up-down class="h-3.5 w-3.5 text-gray-400" />
        </button>
    @else
        <span class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $label }}</span>
    @endif

    @switch($name)
        @case('code')
            <input wire:model.live.debounce.500ms="tableFilters.code.value" type="search" placeholder="Cari kode..." class="{{ $inputClass }}">
            @break

        @case('created_at')
            <div
                x-data="{
                    open: false,
                    from: $wire.entangle('tableFilters.created_at.from').live,
                    until: $wire.entangle('tableFilters.created_at.until').live,
                    draftFrom: null,
                    draftUntil: null,
                    hover: null,
                    cursor: new Date(),
                    position: { top: 0, left: 0, width: 832 },
                    format(value) {
                        if (! value) return '';
                        const [year, month, day] = value.split('-');
                        return `${day}/${month}/${year}`;
                    },
                    label() {
                        if (! this.from && ! this.until) return 'Pilih rentang tanggal';
                        if (this.from && ! this.until) return `${this.format(this.from)} - ...`;
                        return `${this.format(this.from)} - ${this.format(this.until)}`;
                    },
                    openCalendar() {
                        this.draftFrom = this.from;
                        this.draftUntil = this.until;
                        this.hover = null;
                        this.cursor = this.from ? new Date(`${this.from}T12:00:00`) : new Date();
                        this.open = true;
                        this.$nextTick(() => this.placeCalendar());
                    },
                    placeCalendar() {
                        const rect = this.$refs.trigger.getBoundingClientRect();
                        const width = Math.min(832, window.innerWidth - 24);
                        this.position = {
                            top: Math.min(rect.bottom + 6, window.innerHeight - 120),
                            left: Math.max(12, Math.min(rect.left, window.innerWidth - width - 12)),
                            width,
                        };
                    },
                    monthDate(offset = 0) {
                        return new Date(this.cursor.getFullYear(), this.cursor.getMonth() + offset, 1);
                    },
                    monthLabel(offset = 0) {
                        return new Intl.DateTimeFormat('id-ID', { month: 'long', year: 'numeric' }).format(this.monthDate(offset));
                    },
                    changeMonth(offset) {
                        this.cursor = new Date(this.cursor.getFullYear(), this.cursor.getMonth() + offset, 1);
                    },
                    valueFor(day, offset = 0) {
                        const date = this.monthDate(offset);
                        const year = date.getFullYear();
                        const month = String(date.getMonth() + 1).padStart(2, '0');
                        return `${year}-${month}-${String(day).padStart(2, '0')}`;
                    },
                    days(monthOffset = 0) {
                        const date = this.monthDate(monthOffset);
                        const year = date.getFullYear();
                        const month = date.getMonth();
                        const leadingOffset = (new Date(year, month, 1).getDay() + 6) % 7;
                        const count = new Date(year, month + 1, 0).getDate();
                        return Array.from({ length: 42 }, (_, index) => {
                            const day = index - leadingOffset + 1;
                            return day > 0 && day <= count ? { day, value: this.valueFor(day, monthOffset) } : { day: null, value: null };
                        });
                    },
                    disabled(value) {
                        return this.draftFrom && ! this.draftUntil && value < this.draftFrom;
                    },
                    inRange(value) {
                        if (! value || ! this.draftFrom) return false;
                        const end = this.draftUntil || this.hover;
                        return end && value >= this.draftFrom && value <= end;
                    },
                    select(value) {
                        if (! value || this.disabled(value)) return;
                        if (! this.draftFrom || this.draftUntil) {
                            this.draftFrom = value;
                            this.draftUntil = null;
                            this.hover = null;
                            return;
                        }
                        this.draftUntil = value;
                        this.hover = null;
                    },
                    apply() {
                        this.from = this.draftFrom;
                        this.until = this.draftUntil;
                        this.open = false;
                    },
                    clear() {
                        this.draftFrom = null;
                        this.draftUntil = null;
                        this.from = null;
                        this.until = null;
                        this.hover = null;
                    },
                }"
                class="relative mt-2 min-w-56"
            >
                <button
                    x-ref="trigger"
                    type="button"
                    @click.stop="open ? open = false : openCalendar()"
                    class="flex w-full items-center justify-between gap-2 rounded-md border border-gray-300 bg-white px-2.5 py-2 text-left text-xs font-normal normal-case tracking-normal text-gray-900 shadow-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                >
                    <span class="truncate" x-text="label()"></span>
                    <x-heroicon-o-calendar-days class="h-4 w-4 shrink-0 text-gray-400" />
                </button>

                <template x-teleport="body">
                    <div
                        x-show="open"
                        x-cloak
                        @click.outside="open = false"
                        :style="`position: fixed; top: ${position.top}px; left: ${position.left}px; width: ${position.width}px; z-index: 99999; max-height: calc(100vh - ${position.top + 12}px); overflow-y: auto;`"
                        class="rounded-xl border border-gray-200 bg-white text-left shadow-lg dark:border-gray-700 dark:bg-gray-900"
                    >
                    <div class="relative grid gap-10 p-6 sm:grid-cols-2">
                        <button type="button" @click="changeMonth(-1)" class="absolute left-4 top-5 rounded-md p-1.5 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800" aria-label="Bulan sebelumnya"><x-heroicon-o-chevron-left class="h-5 w-5" /></button>
                        <button type="button" @click="changeMonth(1)" class="absolute right-4 top-5 rounded-md p-1.5 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800" aria-label="Bulan berikutnya"><x-heroicon-o-chevron-right class="h-5 w-5" /></button>
                        <template x-for="monthOffset in [0, 1]" :key="monthOffset">
                            <div class="min-w-0 px-2">
                                <p class="text-center text-base font-semibold capitalize text-gray-800 dark:text-gray-200" x-text="monthLabel(monthOffset)"></p>
                                <div class="mt-5 grid grid-cols-7 gap-1 text-center text-[11px] font-semibold uppercase text-gray-400">
                                    <span>Sen</span><span>Sel</span><span>Rab</span><span>Kam</span><span>Jum</span><span>Sab</span><span>Min</span>
                                </div>
                                <div class="mt-2 grid grid-cols-7 gap-1">
                                    <template x-for="(cell, index) in days(monthOffset)" :key="index">
                                        <button
                                            type="button"
                                            @click="select(cell.value)"
                                            @mouseenter="hover = disabled(cell.value) ? null : cell.value"
                                            @mouseleave="hover = null"
                                            :disabled="! cell.day || disabled(cell.value)"
                                            :class="{
                                                'text-transparent': ! cell.day,
                                                'cursor-not-allowed text-gray-300': cell.day && disabled(cell.value),
                                                'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300': cell.day && inRange(cell.value),
                                                'bg-blue-600 font-semibold text-white': cell.value && (cell.value === draftFrom || cell.value === draftUntil),
                                                'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800': cell.day && ! inRange(cell.value) && ! disabled(cell.value),
                                            }"
                                            class="h-10 rounded-md text-sm"
                                            x-text="cell.day || ''"
                                        ></button>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                        <div class="flex flex-col gap-3 border-t border-gray-200 px-6 py-4 dark:border-gray-700 sm:flex-row sm:items-center sm:justify-between">
                            <p class="text-sm font-semibold text-blue-600" x-text="draftFrom ? `${format(draftFrom)}${draftUntil ? ` - ${format(draftUntil)}` : ' - ...'}` : 'Pilih rentang tanggal'"></p>
                            <div class="flex justify-end gap-2">
                                <button type="button" @click="clear()" class="rounded-md border border-gray-300 px-3 py-2 text-xs font-semibold text-gray-600 dark:border-gray-700 dark:text-gray-300">Clear</button>
                                <button type="button" @click="apply()" :disabled="! draftFrom || ! draftUntil" class="rounded-md bg-blue-600 px-4 py-2 text-xs font-semibold text-white disabled:cursor-not-allowed disabled:opacity-40">Apply</button>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
            @break

        @case('requester.name')
            <input wire:model.live.debounce.500ms="tableFilters.requester_name.value" type="search" placeholder="Cari pemohon..." class="{{ $inputClass }}">
            @break

        @case('branch.name')
            <select wire:model.live="tableFilters.branch_id.value" class="{{ $inputClass }}">
                <option value="">- Semua Cabang -</option>
                @foreach(Branch::query()->orderBy('name')->pluck('name', 'id') as $branchId => $branchName)
                    <option value="{{ $branchId }}">{{ $branchName }}</option>
                @endforeach
            </select>
            @break

        @case('judul_konten')
            <input wire:model.live.debounce.500ms="tableFilters.judul_konten.value" type="search" placeholder="Cari judul..." class="{{ $inputClass }}">
            @break

        @case('jenis_konten')
            <select wire:model.live="tableFilters.jenis_konten.value" class="{{ $inputClass }}">
                <option value="">- Semua Jenis -</option>
                <option value="photo">Foto</option>
                <option value="video">Video</option>
            </select>
            @break

        @case('platform_tujuan')
            <input wire:model.live.debounce.500ms="tableFilters.platform_tujuan.value" type="search" placeholder="Cari platform..." class="{{ $inputClass }}">
            @break

        @case('status')
            <select wire:model.live="tableFilters.status.value" class="{{ $inputClass }}">
                <option value="">- Semua Status -</option>
                @foreach(ContentRequestStatus::cases() as $status)
                    <option value="{{ $status->value }}">{{ $status->getLabel() }}</option>
                @endforeach
            </select>
            @break
    @endswitch
</th>
