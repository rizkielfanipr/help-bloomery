<?php

namespace App\Services\BrandMarketing\DesignMaster;

use App\Models\MarketingDesignBundleVersion;
use App\Models\MarketingDesignItem;

/**
 * Metadata comparison of a revision with the version it was cloned from (PRD §17.3). It compares
 * item fields and file counts only; binary content is never inspected.
 */
class MarketingDesignVersionComparison
{
    private const COMPARED_FIELDS = [
        'type' => 'Tipe',
        'title' => 'Judul',
        'description' => 'Deskripsi',
        'dimensions' => 'Dimensi',
        'material_specification' => 'Material',
        'finishing' => 'Finishing',
        'color_print_notes' => 'Warna/Cetak',
        'sides' => 'Sisi',
        'production_notes' => 'Catatan Produksi',
    ];

    /**
     * @return array{base: string, added: list<string>, removed: list<string>, changed: list<array{title: string, fields: list<string>}>}|null
     */
    public function compare(MarketingDesignBundleVersion $version): ?array
    {
        $base = $version->clonedFrom;

        if ($base === null) {
            return null;
        }

        $base->loadMissing('items.files');
        $version->loadMissing('items.files');
        $baseItems = $base->items->keyBy('id');
        $changed = [];

        foreach ($version->items as $item) {
            $baseItem = $baseItems->get($item->cloned_from_item_id);

            if ($baseItem !== null && ($fields = $this->changedFields($item, $baseItem)) !== []) {
                $changed[] = ['title' => $item->title, 'fields' => $fields];
            }
        }

        return [
            'base' => $base->label(),
            'added' => $version->items->filter(fn (MarketingDesignItem $item): bool => ! $baseItems->has($item->cloned_from_item_id))->pluck('title')->values()->all(),
            'removed' => $baseItems->except($version->items->pluck('cloned_from_item_id')->filter()->all())->pluck('title')->values()->all(),
            'changed' => $changed,
        ];
    }

    /** @return list<string> */
    private function changedFields(MarketingDesignItem $item, MarketingDesignItem $baseItem): array
    {
        $fields = [];

        foreach (self::COMPARED_FIELDS as $attribute => $label) {
            if ($item->{$attribute} !== $baseItem->{$attribute}) {
                $fields[] = $label;
            }
        }

        if ($item->files->pluck('reused_from_file_id')->filter()->sort()->values()->all() !== $baseItem->files->pluck('id')->sort()->values()->all()) {
            $fields[] = 'File';
        }

        return $fields;
    }
}
