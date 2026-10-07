@props(['status'])

{{-- WIP Shelf Life data status (docs/rnd-wip-shelf-life-prd.md §16.1): always text + icon, never colour alone. --}}
@php
    $icon = match ($status) {
        \App\Enums\RndWipShelfLifeStatus::Complete => 'heroicon-m-check-circle',
        \App\Enums\RndWipShelfLifeStatus::Missing => 'heroicon-m-exclamation-circle',
        \App\Enums\RndWipShelfLifeStatus::Inactive => 'heroicon-m-pause-circle',
        \App\Enums\RndWipShelfLifeStatus::IdentityIncomplete => 'heroicon-m-x-circle',
    };
@endphp

<x-filament::badge :color="$status->getColor()" :icon="$icon" {{ $attributes }}>{{ $status->getLabel() }}</x-filament::badge>
