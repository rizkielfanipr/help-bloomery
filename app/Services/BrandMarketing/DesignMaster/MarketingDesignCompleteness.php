<?php

namespace App\Services\BrandMarketing\DesignMaster;

use App\Models\MarketingDesignItem;

/**
 * Informational completeness of an item (PRD §11.8); it never blocks saving. Physical is derived
 * from the item type on the server. Works on the loaded file, so listing items adds no queries.
 */
class MarketingDesignCompleteness
{
    /** @return list<string> */
    public function itemIssues(MarketingDesignItem $item): array
    {
        $issues = [];

        if ($item->file === null) {
            $issues[] = 'File desain belum ada';
        }

        if ($item->isPhysical() && (blank($item->dimensions) || blank($item->material_specification))) {
            $issues[] = 'Spesifikasi dimensi dan material belum diisi';
        }

        return $issues;
    }
}
