<?php

namespace App\Http\Controllers\Helpdesk;

use App\Http\Controllers\Controller;
use App\Models\RndInternalMemo;
use App\Models\RndInternalMemoMenu;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * "Export PDF" of a Memo Internal: the list of Menus to be released, on the same letterhead as the
 * R&D SOP PDF and titled "MEMO INTERNAL {BRAND}". Generated on demand from local data only — no
 * ESB request and no stored document.
 */
class RndInternalMemoReleaseExportController extends Controller
{
    public function __invoke(int $memo): Response
    {
        $memoRecord = RndInternalMemo::query()->with(['creator:id,name,username'])->findOrFail($memo);
        abort_unless(auth()->user()?->can('exportPdf', $memoRecord), 403);

        $menus = $memoRecord->menus()->get()->map(function (RndInternalMemoMenu $menu): array {
            [$category, $detail] = array_pad(explode(' - ', (string) $menu->category_detail, 2), 2, null);

            return [
                'code' => $menu->menu_code ?: 'ID '.$menu->esb_menu_id,
                'name' => $menu->menu_name,
                'category' => filled($category) ? trim($category) : null,
                'category_detail' => filled($detail) ? trim($detail) : null,
                'release_date' => $menu->release_date,
            ];
        });

        $pdf = Pdf::loadView('exports.rnd-internal-memo-release-pdf', [
            'memo' => $memoRecord,
            'brandLabel' => $memoRecord->brandLabel(),
            'menus' => $menus,
            'logo' => 'data:image/png;base64,'.base64_encode(file_get_contents(public_path('images/bloomery-icon-pdf.png'))),
            'generatedAt' => now()->translatedFormat('d M Y H:i'),
        ])->setPaper('a4', 'portrait');

        $pdf->render();
        $domPdf = $pdf->getDomPDF();
        $domPdf->getCanvas()->page_text(470, 817, 'Halaman {PAGE_NUM} / {PAGE_COUNT}', $domPdf->getFontMetrics()->getFont('DejaVu Sans', 'normal'), 7, [0.58, 0.64, 0.72]);

        $number = Str::slug(Str::of($memoRecord->memo_number)->replace(['/', '\\'], ' '));

        return $pdf->download('MEMO-INTERNAL-'.Str::upper($number !== '' ? $number : (string) $memoRecord->id).'.pdf');
    }
}
