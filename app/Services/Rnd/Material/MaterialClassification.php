<?php

namespace App\Services\Rnd\Material;

use App\Models\RndProductEsbMaterial;

/**
 * Material kind used by the workflow. It reuses the existing section rules (category name, BW/
 * PKG/PAM code prefixes, explicit marketing section): WIP skips sourcing, every other section
 * needs sourcing and a sample (docs/material-sourcing-lifecycle-prd.md §8, §32 item 4).
 */
class MaterialClassification
{
    public static function label(RndProductEsbMaterial $material): string
    {
        return match ($material->materialSection()) {
            'wip' => 'WIP',
            'packaging' => 'Packaging',
            'marketing' => 'Marketing',
            default => 'Raw Material',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return ['raw' => 'Raw Material', 'packaging' => 'Packaging', 'marketing' => 'Marketing', 'wip' => 'WIP'];
    }
}
