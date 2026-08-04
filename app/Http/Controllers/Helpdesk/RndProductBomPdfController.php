<?php

namespace App\Http\Controllers\Helpdesk;

use App\Http\Controllers\Controller;
use App\Models\RndBomInstruction;
use App\Models\RndProject;
use App\Services\EsbCoreService;
use App\Services\EsbService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Throwable;

class RndProductBomPdfController extends Controller
{
    public function __invoke(int $project, int $product): Response
    {
        $user = auth()->user();
        abort_unless($user?->can('view bill of materials'), 403);

        $projectRecord = RndProject::query()->findOrFail($project);
        $productRecord = $projectRecord->products()->with('boms')->findOrFail($product);
        abort_unless(
            (int) session()->get(self::sessionKey($user->id, $projectRecord->id, $productRecord->id), 0) > now()->timestamp,
            403,
            'PIN diperlukan untuk mengunduh dokumen resep.',
        );

        $esb = app(EsbCoreService::class);
        $details = $productRecord->boms->mapWithKeys(fn ($bom): array => [
            $bom->id => $bom->detail_snapshot ?: $esb->getBillOfMaterial($bom->esb_bom_id),
        ]);
        $instructions = RndBomInstruction::query()
            ->where('rnd_project_id', $projectRecord->id)
            ->where('rnd_project_product_id', $productRecord->id)
            ->get()
            ->mapWithKeys(fn (RndBomInstruction $instruction): array => [
                $instruction->esb_bom_id => [
                    'content_html' => $this->inlineStoredImages((string) $instruction->content_html),
                    'images' => collect($instruction->image_paths ?? [])->map(function (string $path): ?string {
                        $contents = Storage::disk('b2')->get($path);
                        if (! is_string($contents) || $contents === '') {
                            return null;
                        }
                        $mime = Storage::disk('b2')->mimeType($path) ?: 'image/jpeg';

                        return "data:$mime;base64,".base64_encode($contents);
                    })->filter()->values()->all(),
                ],
            ]);
        $mainBoms = $productRecord->boms->where('pivot.usage_type', 'main');
        $autoWipBoms = $this->autoWipBoms($mainBoms, $details, $esb);
        $resultUnitMap = $this->resultUnitMap($details, $autoWipBoms, app(EsbService::class));
        $mainIds = $mainBoms->pluck('id');
        $unassigned = $productRecord->boms->filter(fn ($bom) => $bom->pivot->usage_type !== 'main'
            && (! $bom->pivot->parent_rnd_project_bom_id || ! $mainIds->contains($bom->pivot->parent_rnd_project_bom_id))
        );
        $logo = 'data:image/png;base64,'.base64_encode(
            file_get_contents(public_path('images/bloomery-icon-pdf.png')),
        );
        $documentNumber = sprintf('RND/%03d/%03d/%s', $projectRecord->id, $productRecord->id, now()->format('Y'));

        $pdf = Pdf::loadView('exports.rnd-product-bom-pdf', compact(
            'projectRecord',
            'productRecord',
            'details',
            'instructions',
            'mainBoms',
            'autoWipBoms',
            'resultUnitMap',
            'unassigned',
            'logo',
            'documentNumber',
        ))->setPaper('a4', 'portrait');

        $filename = 'BOM-'.str($productRecord->product_code ?: $productRecord->name)->slug()->upper().'.pdf';

        return $pdf->download($filename);
    }

    public static function sessionKey(int $userId, int $projectId, int $productId): string
    {
        return "rnd.bom.export.$userId.$projectId.$productId";
    }

    private function inlineStoredImages(string $html): string
    {
        if ($html === '') {
            return $html;
        }

        return preg_replace_callback(
            '#<img\b([^>]*?)\bsrc\s*=\s*(["\'])(?:https?://[^/"\']+)?/rnd-bom-instruction-images/(rnd/bom-instructions/\d+/\d+/\d+/inline/[A-Za-z0-9\-]+\.jpg)\2([^>]*)>#i',
            function (array $match): string {
                $contents = Storage::disk('b2')->get($match[3]);
                if (! is_string($contents) || $contents === '') {
                    return '';
                }

                return '<img'.$match[1].'src="data:image/jpeg;base64,'.base64_encode($contents).'"'.$match[4].'>';
            },
            $html,
        ) ?? $html;
    }

    /**
     * @return array<int, array<string, mixed>> keyed by productDetailID
     */
    private function resultUnitMap($details, array $autoWipBoms, EsbService $products): array
    {
        $codes = collect($details)->pluck('productCode')->filter();
        foreach ($autoWipBoms as $recipes) {
            $codes = $codes->merge(collect($recipes)->pluck('productCode')->filter());
        }
        $codes = $codes->unique()->values()->all();

        if ($codes === []) {
            return [];
        }

        try {
            return $products->getActiveProductDetailsByCodes($codes);
        } catch (Throwable) {
            return [];
        }
    }

    private function autoWipBoms($mainBoms, $details, EsbCoreService $esb): array
    {
        $products = app(EsbService::class);
        $result = [];

        foreach ($mainBoms as $mainBom) {
            $recipes = [];
            foreach (($details[$mainBom->id]['bomDetails'] ?? []) as $component) {
                $productDetailId = (int) ($component['productDetailID'] ?? 0);
                $product = $products->findActiveProductDetail(
                    $productDetailId,
                    (string) ($component['productCode'] ?? ''),
                    (string) ($component['productName'] ?? ''),
                );
                if (mb_strtolower(trim((string) ($product['categoryName'] ?? ''))) !== 'barang wip') {
                    continue;
                }

                $candidates = $esb->getBillOfMaterials([
                    'productName' => $product['productName'] ?? $component['productName'] ?? '',
                    'limit' => 100,
                ]);
                foreach ($candidates['data'] as $candidate) {
                    $bomId = (int) ($candidate['bomID'] ?? 0);
                    if ($bomId < 1 || $bomId === $mainBom->esb_bom_id) {
                        continue;
                    }
                    $detail = $esb->getBillOfMaterial($bomId);
                    $sameDetail = (int) ($detail['productDetailID'] ?? 0) === $productDetailId;
                    $sameProduct = (int) ($product['productID'] ?? 0) > 0
                        && (int) ($detail['productID'] ?? 0) === (int) $product['productID'];
                    if ($sameDetail || $sameProduct) {
                        $recipes[$bomId] = $detail;
                    }
                }
            }
            $result[$mainBom->id] = array_values($recipes);
        }

        return $result;
    }
}
