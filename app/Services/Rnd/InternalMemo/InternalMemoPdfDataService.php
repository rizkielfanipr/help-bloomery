<?php

namespace App\Services\Rnd\InternalMemo;

use App\Models\RndInternalMemo;

/**
 * docs/rnd-internal-memo-prd.md §16. Builds the PDF's view data purely from what is already
 * persisted on a Finalized memo (Menu and Material rows, which are themselves the snapshot);
 * this never calls ESB, matching the requirement that PDF generation makes no ESB calls.
 */
class InternalMemoPdfDataService
{
    /** @return array<string, mixed> */
    public function build(RndInternalMemo $memo): array
    {
        $menus = $memo->menus()->with(['materials' => fn ($query) => $query->orderBy('depth')->orderBy('id')])->get();
        $consolidation = app(InternalMemoConsolidationService::class)->consolidate($memo);

        return [
            'memo' => $memo,
            'menus' => $menus,
            'consolidatedRows' => $consolidation['rows'],
            'generatedAt' => now(),
        ];
    }
}
