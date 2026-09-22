@php
    use App\Enums\MarketingMaterialFulfillmentStatus;
    use App\Models\RndProjectMarketingMaterial;

    $name = $column->getName();
    $label = $column->getLabel();
    $sortable = $column->isSortable() && ! $isReordering;
    $width = match ($name) {
        'product.project.name' => '16%',
        'product.name' => '16%',
        'title' => '19%',
        'type' => '13%',
        'fulfillment_status' => '14%',
        'created_at' => '16%',
        default => null,
    };
    $inputClass = 'mt-2 block w-full rounded-md border border-gray-300 bg-white px-2.5 py-2 text-xs font-normal normal-case tracking-normal text-gray-900 shadow-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-900 dark:text-white';
@endphp

@once
    <style>
        .fi-ta-filters-trigger-action-ctn,
        .fi-ta-filter-indicators-trigger-action-ctn,
        .fi-ta-filters-above-content-ctn {
            display: none !important;
        }

        .fi-ta-table {
            width: 100% !important;
            min-width: 1180px;
        }

        .fi-ta-header-cell {
            padding-left: 0.75rem !important;
            padding-right: 0.75rem !important;
        }
    </style>
@endonce

<th class="fi-ta-header-cell align-top" @if($width) style="width: {{ $width }}" @endif>
    @if($sortable)
        <button type="button" wire:click="sortTable('{{ $name }}')" class="flex items-center gap-1 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
            <span>{{ $label }}</span>
            <x-heroicon-o-chevron-up-down class="h-3.5 w-3.5 text-gray-400" />
        </button>
    @else
        <span class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $label }}</span>
    @endif

    @switch($name)
        @case('product.project.name')
            <input wire:model.live.debounce.500ms="tableFilters.project_name.value" type="search" placeholder="Cari project..." class="{{ $inputClass }}">
            @break

        @case('product.name')
            <input wire:model.live.debounce.500ms="tableFilters.product_name_filter.value" type="search" placeholder="Cari produk..." class="{{ $inputClass }}">
            @break

        @case('title')
            <input wire:model.live.debounce.500ms="tableFilters.material_name.value" type="search" placeholder="Cari material..." class="{{ $inputClass }}">
            @break

        @case('type')
            <select wire:model.live="tableFilters.type.value" class="{{ $inputClass }}">
                <option value="">- Semua Tipe -</option>
                @foreach(RndProjectMarketingMaterial::PHYSICAL_TYPES as $type)
                    <option value="{{ $type }}">{{ RndProjectMarketingMaterial::TYPES[$type] ?? $type }}</option>
                @endforeach
            </select>
            @break

        @case('fulfillment_status')
            <select wire:model.live="tableFilters.fulfillment_status.value" class="{{ $inputClass }}">
                <option value="">- Semua Status -</option>
                @foreach(MarketingMaterialFulfillmentStatus::cases() as $status)
                    <option value="{{ $status->value }}">{{ $status->getLabel() }}</option>
                @endforeach
            </select>
            @break

        @case('created_at')
            <div
                x-data="{
                    open: false,
                    from: $wire.entangle('tableFilters.created_at.from').live,
                    until: $wire.entangle('tableFilters.created_at.until').live,
                    draftFrom: null,
                    draftUntil: null,
                    format(value) {
                        if (! value) return '';
                        const [year, month, day] = value.split('-');
                        return `${day}/${month}/${year}`;
                    },
                    label() {
                        if (! this.from && ! this.until) return 'Pilih rentang tanggal';
                        return `${this.from ? this.format(this.from) : '...'} - ${this.until ? this.format(this.until) : '...'}`;
                    },
                    show() {
                        this.draftFrom = this.from;
                        this.draftUntil = this.until;
                        this.open = true;
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
                        this.open = false;
                    },
                }"
                class="relative mt-2 min-w-48"
            >
                <button type="button" @click.stop="open ? open = false : show()" class="flex w-full items-center justify-between gap-2 rounded-md border border-gray-300 bg-white px-2.5 py-2 text-left text-xs font-normal normal-case tracking-normal text-gray-900 shadow-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-900 dark:text-white">
                    <span class="truncate" x-text="label()"></span>
                    <x-heroicon-o-calendar-days class="h-4 w-4 shrink-0 text-gray-400" />
                </button>

                <div x-show="open" x-cloak @click.outside="open = false" class="absolute right-0 z-50 mt-2 w-72 rounded-xl border border-gray-200 bg-white p-4 shadow-lg dark:border-gray-700 dark:bg-gray-900">
                    <div class="space-y-3">
                        <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300">Dari Tanggal<input x-model="draftFrom" type="date" class="mt-1 block w-full rounded-md border border-gray-300 px-2.5 py-2 text-sm dark:border-gray-600 dark:bg-gray-950"></label>
                        <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300">Sampai Tanggal<input x-model="draftUntil" :min="draftFrom || null" type="date" class="mt-1 block w-full rounded-md border border-gray-300 px-2.5 py-2 text-sm dark:border-gray-600 dark:bg-gray-950"></label>
                    </div>
                    <div class="mt-4 flex justify-end gap-2 border-t border-gray-100 pt-3 dark:border-gray-800">
                        <button type="button" @click="clear()" class="rounded-md border border-gray-300 px-3 py-2 text-xs font-semibold text-gray-600 dark:border-gray-700 dark:text-gray-300">Clear</button>
                        <button type="button" @click="apply()" class="rounded-md bg-blue-600 px-4 py-2 text-xs font-semibold text-white">Apply</button>
                    </div>
                </div>
            </div>
            @break
    @endswitch
</th>
