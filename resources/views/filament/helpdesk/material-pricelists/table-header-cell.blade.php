@php
    use App\Enums\MaterialPricingStatus;
    use App\Filament\Helpdesk\Resources\MaterialPricelists\MaterialPricelistResource;
    use App\Models\RndMaterialPricingRequest;
    use App\Services\Rnd\Material\MaterialClassification;

    $name = $column->getName();
    $label = $column->getLabel();
    $sortable = $column->isSortable() && ! $isReordering;
    $width = match ($name) {
        'material.product_code' => '11%',
        'material.product_name' => '14%',
        'material.category_name' => '11%',
        'origin' => '8%',
        'supplier_name' => '11%',
        'uom_name' => '8%',
        'offer_price', 'price' => '8%',
        'company_code' => '8%',
        'status' => '11%',
        'queued_at' => '12%',
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
            min-width: 1600px;
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
        @case('material.product_code')
            <select wire:model.live="tableFilters.section.value" aria-label="Filter Jenis" class="{{ $inputClass }}">
                <option value="">- Semua Jenis -</option>
                @foreach(MaterialClassification::options() as $value => $optionLabel)
                    <option value="{{ $value }}">{{ $optionLabel }}</option>
                @endforeach
            </select>
            @break

        @case('material.product_name')
            <input wire:model.live.debounce.500ms="tableFilters.search.value" type="search" placeholder="Cari kode/nama..." aria-label="Cari bahan" class="{{ $inputClass }}">
            @break

        @case('material.category_name')
            <select wire:model.live="tableFilters.category.value" aria-label="Filter Kategori" class="{{ $inputClass }}">
                <option value="">- Semua Kategori -</option>
                @foreach($categoryOptions as $category)
                    <option value="{{ $category }}">{{ $category }}</option>
                @endforeach
            </select>
            @break

        @case('origin')
            <select wire:model.live="tableFilters.origin.value" aria-label="Filter Asal" class="{{ $inputClass }}">
                <option value="">- Semua -</option>
                <option value="{{ RndMaterialPricingRequest::ORIGIN_SOURCING }}">Sourcing</option>
                <option value="{{ RndMaterialPricingRequest::ORIGIN_WIP }}">WIP</option>
            </select>
            @break

        @case('supplier_name')
            <select wire:model.live="tableFilters.supplier_name.value" aria-label="Filter Supplier" class="{{ $inputClass }}">
                <option value="">- Semua Supplier -</option>
                @foreach($supplierOptions as $supplier)
                    <option value="{{ $supplier }}">{{ $supplier }}</option>
                @endforeach
            </select>
            @break

        @case('company_code')
            <select wire:model.live="tableFilters.company_code.value" aria-label="Filter Company" class="{{ $inputClass }}">
                <option value="">- Semua -</option>
                @foreach(MaterialPricelistResource::companyFilterOptions() as $code => $codeLabel)
                    <option value="{{ $code }}">{{ $codeLabel }}</option>
                @endforeach
            </select>
            @break

        @case('status')
            <select wire:model.live="tableFilters.status.value" aria-label="Filter Status Pricelist" class="{{ $inputClass }}">
                <option value="">- All Status -</option>
                @foreach(MaterialPricingStatus::options() as $value => $optionLabel)
                    <option value="{{ $value }}">{{ $optionLabel }}</option>
                @endforeach
            </select>
            @break

        @case('queued_at')
            @include('filament.helpdesk.operational.table-date-range-filter', ['filterName' => 'queued_at'])
            @break
    @endswitch
</th>
