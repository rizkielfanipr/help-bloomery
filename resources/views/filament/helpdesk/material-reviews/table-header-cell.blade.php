@php
    use App\Enums\MaterialEsbSyncStatus;
    use App\Enums\MaterialReviewStatus;
    use App\Services\Rnd\Material\MaterialClassification;

    $name = $column->getName();
    $label = $column->getLabel();
    $sortable = $column->isSortable() && ! $isReordering;
    $width = match ($name) {
        'product_code' => '15%',
        'product_name' => '22%',
        'review_status' => '15%',
        'esb_sync_status' => '13%',
        'latestReview.submitter.name' => '14%',
        'latestReview.submitted_at' => '15%',
        'submission_version' => '6%',
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
        <button type="button" wire:click="sortTable('{{ $name }}')" class="flex items-center gap-1 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
            <span>{{ $label }}</span>
            <x-heroicon-o-chevron-up-down class="h-3.5 w-3.5 text-gray-400" />
        </button>
    @else
        <span class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $label }}</span>
    @endif

    @switch($name)
        @case('product_code')
            <input wire:model.live.debounce.500ms="tableFilters.search.value" type="search" placeholder="Cari kode/nama..." aria-label="Cari Master Bahan" class="{{ $inputClass }}">
            @break

        @case('product_name')
            <select wire:model.live="tableFilters.section.value" aria-label="Filter Jenis" class="{{ $inputClass }}">
                <option value="">- Semua Jenis -</option>
                @foreach(MaterialClassification::options() as $value => $optionLabel)
                    <option value="{{ $value }}">{{ $optionLabel }}</option>
                @endforeach
            </select>
            @break

        @case('review_status')
            <select wire:model.live="tableFilters.review_status.value" aria-label="Filter Status Review" class="{{ $inputClass }}">
                <option value="">- All Status -</option>
                @foreach(MaterialReviewStatus::options() as $value => $optionLabel)
                    <option value="{{ $value }}">{{ $optionLabel }}</option>
                @endforeach
            </select>
            @break

        @case('esb_sync_status')
            <select wire:model.live="tableFilters.esb_sync_status.value" aria-label="Filter Status ESB" class="{{ $inputClass }}">
                <option value="">- All Status -</option>
                @foreach(MaterialEsbSyncStatus::options() as $value => $optionLabel)
                    <option value="{{ $value }}">{{ $optionLabel }}</option>
                @endforeach
            </select>
            @break

        @case('latestReview.submitter.name')
            <select wire:model.live="tableFilters.submitter.value" aria-label="Filter Pengaju" class="{{ $inputClass }}">
                <option value="">- Semua Pengaju -</option>
                @foreach($submitterOptions as $userId => $userName)
                    <option value="{{ $userId }}">{{ $userName }}</option>
                @endforeach
            </select>
            @break

        @case('latestReview.submitted_at')
            @include('filament.helpdesk.operational.table-date-range-filter', ['filterName' => 'submitted_at'])
            @break
    @endswitch
</th>
