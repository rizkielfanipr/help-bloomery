<?php

namespace App\Http\Controllers\Helpdesk;

use App\Http\Controllers\Controller;
use App\Models\RndProject;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

class RndProjectBomPdfController extends Controller
{
    public function __invoke(Request $request, int $project): Response
    {
        $user = auth()->user();
        abort_unless($user?->can('view bill of materials'), 403);

        $scope = (string) $request->query('scope');
        abort_unless(in_array($scope, ['kitchen', 'store'], true), 422);
        abort_unless($user?->can(match ($scope) {
            'kitchen' => 'export kitchen bill of materials',
            'store' => 'export store bill of materials',
        }), 403);

        $projectRecord = RndProject::query()
            ->with([
                'products.boms',
                'products.currentRegionalPrices.region',
                'products.salesProjections.region',
                'products.salesProjections.targetBranches',
            ])
            ->findOrFail($project);

        $selectedBomIds = $scope !== 'store' && $request->filled('bom_ids')
            ? collect(explode(',', (string) $request->query('bom_ids')))
                ->filter(fn (string $id): bool => ctype_digit($id) && (int) $id > 0)
                ->map(fn (string $id): int => (int) $id)
                ->unique()->values()->all()
            : null;
        $selectedComponents = session()->get(self::componentSessionKey($user->id, $projectRecord->id));
        $selectedComponents = is_array($selectedComponents) ? $selectedComponents : null;

        $document = $this->renderDocument($projectRecord, $scope, $selectedBomIds, $selectedComponents);

        return $request->boolean('preview')
            ? $document['pdf']->stream($document['filename'])
            : $document['pdf']->download($document['filename']);
    }

    /**
     * @param  list<int>|null  $selectedBomIds
     * @param  array<int, list<string>>|null  $selectedComponents
     * @return array{pdf: \Barryvdh\DomPDF\PDF, filename: string}
     */
    public function renderDocument(RndProject $projectRecord, string $scope, ?array $selectedBomIds = null, ?array $selectedComponents = null): array
    {
        abort_unless(in_array($scope, ['kitchen', 'store'], true), 422);

        $products = $projectRecord->products->filter(fn ($product): bool => $product->boms->contains(
            fn ($bom): bool => $scope === 'store'
                ? $bom->pivot->usage_type === 'menu' && ($selectedBomIds === null || in_array($bom->id, $selectedBomIds, true))
                : $bom->pivot->usage_type !== 'menu' && ($selectedBomIds === null || in_array($bom->id, $selectedBomIds, true)),
        ));
        abort_if($products->isEmpty(), 422, 'Tidak ada Bill of Material '.ucfirst($scope).' pada project ini.');

        $renderer = app(RndProductBomPdfController::class);
        $projectDocumentNumber = 'BOM-'.strtoupper($scope).'-PROJECT-'.str($projectRecord->name)->slug()->upper();
        $renderedDocuments = $products->values()->map(function ($product, int $index) use ($renderer, $projectRecord, $scope, $products, $projectDocumentNumber, $selectedBomIds, $selectedComponents): string {
            $data = $renderer->buildExportData($projectRecord, $product, $scope, $selectedBomIds, $selectedComponents);
            $data['showHeader'] = $index === 0;
            $data['showFooter'] = $index === $products->count() - 1;
            $data['footerDocument'] = $projectDocumentNumber;
            $data['projectSalesProjectionProducts'] = $scope === 'store' && $index === 0
                ? $products->values()
                : null;

            return view('exports.rnd-product-bom-pdf', $data)->render();
        });
        $html = $this->combineRenderedDocuments($renderedDocuments);

        $filename = $projectDocumentNumber.'.pdf';

        $pdf = Pdf::loadHTML($html)->setPaper('a4', 'portrait');
        $this->addPageNumbers($pdf);

        return compact('pdf', 'filename');
    }

    public static function componentSessionKey(int $userId, int $projectId): string
    {
        return "rnd.project-bom.export.components.$userId.$projectId";
    }

    private function addPageNumbers($pdf): void
    {
        $pdf->render();
        $domPdf = $pdf->getDomPDF();
        $font = $domPdf->getFontMetrics()->getFont('DejaVu Sans', 'normal');
        $domPdf->getCanvas()->page_text(470, 817, 'Halaman {PAGE_NUM} / {PAGE_COUNT}', $font, 7, [0.58, 0.64, 0.72]);
    }

    /** @param Collection<int, string> $renderedDocuments */
    private function combineRenderedDocuments(Collection $renderedDocuments): string
    {
        $firstDocument = (string) $renderedDocuments->first();
        $additionalPages = $renderedDocuments->skip(1)
            ->map(function (string $document): string {
                if (preg_match('/<body\b[^>]*>(.*?)<\/body>/is', $document, $body) !== 1) {
                    return '';
                }

                return '<div style="page-break-before: always;">'.$body[1].'</div>';
            })
            ->filter()
            ->implode('');

        if ($additionalPages === '') {
            return $firstDocument;
        }

        return preg_replace('/<\/body>/i', $additionalPages.'</body>', $firstDocument, 1) ?? $firstDocument;
    }
}
