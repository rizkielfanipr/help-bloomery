<?php

namespace App\Services\BrandMarketing\DesignMaster;

use App\Enums\MarketingDesignFileRole;
use App\Models\MarketingDesignBundleVersion;
use App\Models\MarketingDesignItem;
use Illuminate\Validation\ValidationException;

/**
 * Review and publish guard (PRD §11.9, §11.12, §11.13). Physical is derived from the item type on
 * the server, never from browser input. Works on loaded relations, so listing items adds no queries.
 */
class MarketingDesignReadiness
{
    /** @return list<string> */
    public function itemIssues(MarketingDesignItem $item): array
    {
        $roles = $item->files->pluck('role');
        $issues = [];

        if (! $roles->contains(MarketingDesignFileRole::Preview)) {
            $issues[] = 'File preview belum ada';
        }

        if (! $item->isPhysical()) {
            return $issues;
        }

        if (blank($item->dimensions) || blank($item->material_specification)) {
            $issues[] = 'Spesifikasi dimensi dan material belum lengkap';
        }

        if (! $roles->contains(MarketingDesignFileRole::Production)) {
            $issues[] = 'File production belum ada';
        }

        return $issues;
    }

    /** @return list<string> */
    public function versionIssues(MarketingDesignBundleVersion $version): array
    {
        $version->loadMissing('items.files');

        if ($version->items->isEmpty()) {
            return ['Version belum mempunyai item.'];
        }

        return $version->items
            ->flatMap(fn (MarketingDesignItem $item): array => array_map(
                fn (string $issue): string => "{$item->title}: {$issue}.",
                $this->itemIssues($item),
            ))
            ->values()
            ->all();
    }

    /** @throws ValidationException */
    public function ensureReady(MarketingDesignBundleVersion $version): void
    {
        $issues = $this->versionIssues($version);

        if ($issues !== []) {
            throw ValidationException::withMessages(['version' => implode(' ', $issues)]);
        }
    }
}
