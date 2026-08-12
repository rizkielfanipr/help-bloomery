<?php

namespace App\Http\Controllers\Helpdesk;

use App\Http\Controllers\Controller;
use App\Models\RndProject;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class RndProjectBomPdfController extends Controller
{
    public function __invoke(Request $request, int $project): Response
    {
        $user = auth()->user();
        abort_unless($user?->can('view bill of materials'), 403);

        $scope = (string) $request->query('scope');
        abort_unless(in_array($scope, ['kitchen', 'store'], true), 422);

        $projectRecord = RndProject::query()
            ->with(['products.boms', 'products.currentRegionalPrices.region'])
            ->findOrFail($project);

        abort_unless(
            (int) session()->get(self::sessionKey($user->id, $projectRecord->id), 0) > now()->timestamp,
            403,
            'PIN diperlukan untuk mengunduh dokumen resep project.',
        );

        $products = $projectRecord->products->filter(fn ($product): bool => $product->boms->contains(
            fn ($bom): bool => $scope === 'store'
                ? $bom->pivot->usage_type === 'menu'
                : $bom->pivot->usage_type !== 'menu',
        ));
        abort_if($products->isEmpty(), 422, 'Tidak ada Bill of Material '.ucfirst($scope).' pada project ini.');

        $renderer = app(RndProductBomPdfController::class);
        $projectDocumentNumber = 'BOM-'.strtoupper($scope).'-PROJECT-'.str($projectRecord->name)->slug()->upper();
        $renderedDocuments = $products->values()->map(function ($product, int $index) use ($renderer, $projectRecord, $scope, $products, $projectDocumentNumber): string {
            $data = $renderer->buildExportData($projectRecord, $product, $scope);
            $data['showFooter'] = $index === $products->count() - 1;
            $data['footerDocument'] = $projectDocumentNumber;

            return view('exports.rnd-product-bom-pdf', $data)->render();
        });
        $documents = $renderedDocuments->map(function (string $rendered): string {
            preg_match('/<body>(.*)<\/body>/s', $rendered, $body);

            return $body[1] ?? '';
        })->filter()->values();

        preg_match('/<style>(.*)<\/style>/s', $renderedDocuments->first(), $style);

        $pages = $documents->map(fn (string $body, int $index): string =>
            '<section class="project-product-document'.($index < $documents->count() - 1 ? ' page-break' : '').'">'.$body.'</section>'
        )->implode('');
        $html = '<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><style>'.($style[1] ?? '').'.page-break{page-break-after:always;}</style></head><body>'.$pages.'</body></html>';

        $filename = $projectDocumentNumber.'.pdf';

        return Pdf::loadHTML($html)->setPaper('a4', 'portrait')->download($filename);
    }

    public static function sessionKey(int $userId, int $projectId): string
    {
        return "rnd.project-bom.export.$userId.$projectId";
    }
}
