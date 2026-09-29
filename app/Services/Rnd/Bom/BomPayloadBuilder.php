<?php

namespace App\Services\Rnd\Bom;

/**
 * Builds the ESB `PUT /product/bom/{id}` payload from the freshly re-fetched detail plus the
 * edited component draft (docs/rnd-bom-adjustment-prd.md §13 invariant: "Field API yang tidak
 * diedit tetap dipertahankan dari response terbaru"). Extracted from the Project inline editor
 * so BOM Adjustment and Project send byte-identical payloads through the same builder.
 */
class BomPayloadBuilder
{
    /**
     * BOM Menu (bomTypeID 3) has a leaner bomDetails schema than Assembly per ESB's API — no
     * `tolerancePercent` field — and no top-level "Product Hasil" (result product) concept.
     *
     * @param  array<string, mixed>  $detail
     */
    public function isMenu(array $detail): bool
    {
        return (int) ($detail['bomTypeID'] ?? 0) === 3
            || mb_strtolower(trim((string) ($detail['bomTypeName'] ?? ''))) === 'menu';
    }

    /**
     * @param  array<string, mixed>  $latest  freshly re-fetched ESB BOM detail
     * @param  array<string, mixed>  $draft  edited component/result fields
     * @return array<string, mixed>
     */
    public function build(array $latest, array $draft): array
    {
        $isMenu = $this->isMenu($latest);

        $payload = [
            'bomTypeID' => (int) ($latest['bomTypeID'] ?? 1),
            'bomName' => (string) ($latest['bomName'] ?? ''),
            'bomCode' => (string) ($latest['bomCode'] ?? ''),
            'notes' => (string) ($latest['notes'] ?? ''),
            'bomCostTotal' => (float) ($latest['bomCostTotal'] ?? 0),
            'accessType' => (int) ($latest['accessType'] ?? 0),
            'selectedUserAccess' => is_array($latest['selectedUserAccess'] ?? null) ? $latest['selectedUserAccess'] : [],
            'bomDetails' => array_map(function (array $item) use ($isMenu): array {
                $row = [
                    'ID' => (int) $item['ID'],
                    'productDetailID' => (int) $item['productDetailID'],
                    'lastHPP' => (float) $item['lastHPP'],
                    'qty' => (float) $item['qty'],
                    'yieldPercent' => (float) $item['yieldPercent'],
                    'printGroup' => (string) ($item['printGroup'] ?? ''),
                    'subtitution' => is_array($item['subtitution'] ?? null) ? $item['subtitution'] : [],
                ];

                if (! $isMenu) {
                    $row['tolerancePercent'] = (float) ($item['tolerancePercent'] ?? 0);
                }

                return $row;
            }, $draft['bomDetails']),
            'bomCosts' => is_array($latest['bomCosts'] ?? null) ? $latest['bomCosts'] : [],
        ];

        if (! $isMenu) {
            $payload['productDetailID'] = (int) $draft['productDetailID'];
        }

        return $payload;
    }
}
