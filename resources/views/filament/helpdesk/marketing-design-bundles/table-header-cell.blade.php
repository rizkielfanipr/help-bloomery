@php
    use App\Enums\MarketingDesignBundleCategory;
    use App\Enums\MarketingDesignBundleStatus;
    use App\Enums\MarketingDesignVersionStatus;
    use App\Models\RndProjectMarketingMaterial;

    $name = $column->getName();
    $label = $column->getLabel();
    $sortable = $column->isSortable() && ! $isReordering;
    $inputClass = 'mt-2 block w-full rounded-md border border-gray-300 bg-white px-2.5 py-2 text-xs font-normal normal-case tracking-normal text-gray-900 shadow-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-900 dark:text-white';
    $minWidth = match ($name) {
        'code' => '13rem',
        'brand.name', 'valid_from' => '10rem',
        'category', 'items_total', 'status', 'current_version_status' => '9rem',
        default => null,
    };
@endphp

@once
    <style>
        .fi-ta-filters-trigger-action-ctn,
        .fi-ta-filter-indicators-trigger-action-ctn,
        .fi-ta-filters-above-content-ctn {
            display: none !important;
        }

        .fi-ta-header-cell {
            padding-left: 0.75rem !important;
            padding-right: 0.75rem !important;
        }
    </style>
@endonce

<th class="fi-ta-header-cell align-top" @if($minWidth) style="min-width: {{ $minWidth }}" @endif>
    @if($sortable)
        <button type="button" wire:click="sortTable('{{ $name }}')" class="flex items-center gap-1 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
            <span>{{ $label }}</span>
            <x-heroicon-o-chevron-up-down class="h-3.5 w-3.5 text-gray-400" />
        </button>
    @else
        <span class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $label }}</span>
    @endif

    @switch($name)
        @case('brand.name')
            <select wire:model.live="tableFilters.brand_id.value" aria-label="Filter Brand" class="{{ $inputClass }}">
                <option value="">Semua Brand</option>
                @foreach($brandOptions as $id => $brandName)
                    <option value="{{ $id }}">{{ $brandName }}</option>
                @endforeach
            </select>
            @break

        @case('code')
            <input wire:model.live.debounce.500ms="tableFilters.search.value" type="search" aria-label="Cari kode atau nama" placeholder="Cari kode atau nama..." class="{{ $inputClass }}">
            @break

        @case('category')
            <select wire:model.live="tableFilters.category.value" aria-label="Filter kategori" class="{{ $inputClass }}">
                <option value="">Semua Kategori</option>
                @foreach(MarketingDesignBundleCategory::cases() as $category)
                    <option value="{{ $category->value }}">{{ $category->getLabel() }}</option>
                @endforeach
            </select>
            @break

        @case('items_total')
            <select wire:model.live="tableFilters.nature.value" aria-label="Filter fisik atau digital" class="{{ $inputClass }}">
                <option value="">Fisik & Digital</option>
                <option value="physical">Fisik</option>
                <option value="digital">Digital</option>
            </select>
            <select wire:model.live="tableFilters.item_type.value" aria-label="Filter tipe item" class="{{ $inputClass }}">
                <option value="">Semua Tipe</option>
                @foreach(RndProjectMarketingMaterial::TYPES as $type => $typeLabel)
                    <option value="{{ $type }}">{{ $typeLabel }}</option>
                @endforeach
            </select>
            @break

        @case('status')
            @if($isInternal)
                <select wire:model.live="tableFilters.status.value" aria-label="Filter status bundle" class="{{ $inputClass }}">
                    <option value="">Semua Status</option>
                    @foreach(MarketingDesignBundleStatus::cases() as $status)
                        <option value="{{ $status->value }}">{{ $status->getLabel() }}</option>
                    @endforeach
                </select>
            @endif
            @break

        @case('current_version_status')
            @if($isInternal)
                <select wire:model.live="tableFilters.version_status.value" aria-label="Filter status versi" class="{{ $inputClass }}">
                    <option value="">Semua Status</option>
                    @foreach(MarketingDesignVersionStatus::cases() as $status)
                        <option value="{{ $status->value }}">{{ $status->getLabel() }}</option>
                    @endforeach
                </select>
            @endif
            @break

        @case('valid_from')
            <input wire:model.live="tableFilters.period.from" type="date" aria-label="Periode dari" class="{{ $inputClass }}">
            <input wire:model.live="tableFilters.period.until" type="date" aria-label="Periode sampai" class="{{ $inputClass }}">
            @break
    @endswitch
</th>
