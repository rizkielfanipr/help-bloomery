<?php

namespace App\Http\Controllers\Helpdesk;

use App\Http\Controllers\Controller;
use App\Models\RndProject;
use App\Models\RndProjectProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class RndBomInstructionImageController extends Controller
{
    public function store(Request $request, int $project, int $product, int $bom): JsonResponse
    {
        $user = $request->user();
        abort_unless($user?->can('edit rnd projects'), 403);

        $projectRecord = RndProject::query()->findOrFail($project);
        $productRecord = $projectRecord->products()->findOrFail($product);
        abort_unless(
            $productRecord->boms()->where('rnd_project_boms.esb_bom_id', $bom)->exists()
                || $this->isDiscoveredWipRecipe($productRecord, $bom),
            422,
            'BOM tidak terpasang pada product ini.',
        );

        $validated = $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ]);

        $filename = Str::uuid()->toString().'.jpg';
        $path = "rnd/bom-instructions/$project/$product/$bom/inline/$filename";

        $stored = Storage::disk('b2')->put($path, file_get_contents($validated['image']->getRealPath()));
        abort_unless($stored, 500, 'Gambar gagal diunggah ke Cloudflare R2.');

        return response()->json([
            'path' => $path,
            'url' => route('helpdesk.rnd-products.bom-instruction-images.show', ['path' => $path]),
        ], 201);
    }

    public function show(Request $request, string $path): Response
    {
        abort_unless($request->user()?->can('view rnd projects'), 403);
        abort_unless(
            preg_match('#^rnd/bom-instructions/(\d+)/(\d+)/\d+/inline/[A-Za-z0-9\-]+\.jpg$#', $path, $matches) === 1,
            404,
        );

        $projectRecord = RndProject::query()->findOrFail((int) $matches[1]);
        $projectRecord->products()->findOrFail((int) $matches[2]);

        abort_unless(Storage::disk('b2')->exists($path), 404);

        try {
            return Storage::disk('b2')->response($path, null, ['Cache-Control' => 'private, max-age=3600']);
        } catch (Throwable) {
            abort(404);
        }
    }

    private function isDiscoveredWipRecipe(RndProjectProduct $productRecord, int $bomId): bool
    {
        $mainBoms = $productRecord->boms()->wherePivot('usage_type', 'main')->pluck('rnd_project_boms.esb_bom_id');

        foreach ($mainBoms as $mainBomId) {
            if (cache()->has("rnd.wip-recipes.v4.$mainBomId")) {
                $recipes = cache()->get("rnd.wip-recipes.v4.$mainBomId", []);
                foreach ($recipes as $recipe) {
                    if ((int) ($recipe['bomID'] ?? 0) === $bomId) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
