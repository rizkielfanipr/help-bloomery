@php
    use App\Enums\CustomerComplaintCategory;
    use App\Enums\CustomerComplaintSource;
    use App\Enums\CustomerComplaintStatus;
    use App\Enums\StoreSalesOrderEventType;
    use App\Enums\StoreSalesOrderStatus;

    $name = $column->getName();
    $label = $column->getLabel();
    $sortable = $column->isSortable() && ! $isReordering;
    $width = match ($name) {
        'product_sales_number', 'complaint_number' => '14%',
        'required_date', 'occurred_at' => '16%',
        'customer_name_snapshot', 'customer_name' => '13%',
        'branch.name' => '14%',
        'event_type', 'category', 'source' => '12%',
        'items_summary' => '18%',
        'esb_status_name' => '12%',
        'operational_status', 'status' => '14%',
        'submitter.name' => '12%',
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
            min-width: 1320px;
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
        @case('product_sales_number')
            <input wire:model.live.debounce.500ms="tableFilters.search.value" type="search" placeholder="Cari SO/customer..." aria-label="Cari Store Sales Order" class="{{ $inputClass }}">
            @break

        @case('complaint_number')
            <input wire:model.live.debounce.500ms="tableFilters.search.value" type="search" placeholder="Cari komplain..." aria-label="Cari Customer Complaint" class="{{ $inputClass }}">
            @break

        @case('required_date')
            @include('filament.helpdesk.operational.table-date-range-filter', ['filterName' => 'required_date'])
            @break

        @case('occurred_at')
            @include('filament.helpdesk.operational.table-date-range-filter', ['filterName' => 'occurred_at'])
            @break

        @case('branch.name')
            <select wire:model.live="tableFilters.branch_id.value" aria-label="Filter Branch" class="{{ $inputClass }}">
                <option value="">- Semua Branch -</option>
                @foreach($branchOptions as $branchId => $branchName)
                    <option value="{{ $branchId }}">{{ $branchName }}</option>
                @endforeach
            </select>
            @break

        @case('event_type')
            <select wire:model.live="tableFilters.event_type.value" aria-label="Filter Event Type" class="{{ $inputClass }}">
                <option value="">- Semua Event -</option>
                @foreach(StoreSalesOrderEventType::cases() as $type)
                    <option value="{{ $type->value }}">{{ $type->getLabel() }}</option>
                @endforeach
            </select>
            @break

        @case('esb_status_name')
            <select wire:model.live="tableFilters.esb_status_name.value" aria-label="Filter ESB Status" class="{{ $inputClass }}">
                <option value="">- Semua Status -</option>
                @foreach($esbStatusOptions as $status)
                    <option value="{{ $status }}">{{ $status }}</option>
                @endforeach
            </select>
            @break

        @case('operational_status')
            <select wire:model.live="tableFilters.operational_status.value" aria-label="Filter Operational Status" class="{{ $inputClass }}">
                <option value="">- Semua Status -</option>
                @foreach(StoreSalesOrderStatus::cases() as $status)
                    <option value="{{ $status->value }}">{{ $status->getLabel() }}</option>
                @endforeach
            </select>
            @break

        @case('category')
            <select wire:model.live="tableFilters.category.value" aria-label="Filter Category" class="{{ $inputClass }}">
                <option value="">- Semua Category -</option>
                @foreach(CustomerComplaintCategory::cases() as $category)
                    <option value="{{ $category->value }}">{{ $category->getLabel() }}</option>
                @endforeach
            </select>
            @break

        @case('source')
            <select wire:model.live="tableFilters.source.value" aria-label="Filter Source" class="{{ $inputClass }}">
                <option value="">- Semua Source -</option>
                @foreach(CustomerComplaintSource::cases() as $source)
                    <option value="{{ $source->value }}">{{ $source->getLabel() }}</option>
                @endforeach
            </select>
            @break

        @case('status')
            <select wire:model.live="tableFilters.status.value" aria-label="Filter Complaint Status" class="{{ $inputClass }}">
                <option value="">- Semua Status -</option>
                @foreach(CustomerComplaintStatus::cases() as $status)
                    <option value="{{ $status->value }}">{{ $status->getLabel() }}</option>
                @endforeach
            </select>
            @break
    @endswitch
</th>
