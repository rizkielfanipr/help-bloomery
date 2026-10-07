<?php

namespace App\Actions\Rnd\Bom;

use App\Http\Controllers\Helpdesk\RndProductBomPdfController;
use App\Http\Controllers\Helpdesk\RndProjectBomPdfController;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\RndBomInstruction;
use App\Models\RndProject;
use App\Models\RndProjectProduct;
use App\Models\StoreSop;
use App\Models\StoreSopCategory;
use App\Models\User;
use App\Services\StoreSopPublisher;
use Barryvdh\DomPDF\PDF;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class ReleaseBomToStoreSopAction
{
    public function __construct(
        private readonly RndProductBomPdfController $productPdfController,
        private readonly RndProjectBomPdfController $projectPdfController,
        private readonly StoreSopPublisher $publisher,
    ) {}

    /**
     * @param  list<int>  $bomIds
     * @param  list<string>  $autoBomKeys
     * @param  array<int, list<string>>  $componentKeys
     * @param  array{code:string,title:string,store_sop_category_id:int,brand_id:int,branch_ids:list<int>,effective_date:string,expires_at:string,summary:?string}  $metadata
     * @return array{sop: StoreSop, created: bool, branch_count: int}
     */
    public function execute(
        User $user,
        RndProject $project,
        ?RndProjectProduct $product,
        string $scope,
        array $bomIds,
        array $autoBomKeys,
        array $componentKeys,
        array $metadata,
    ): array {
        $this->authorize($user, $scope);

        if ($product !== null && (int) $product->rnd_project_id !== $project->id) {
            abort(404);
        }

        $project->loadMissing('products.boms');
        $product?->loadMissing('boms');

        $eligibleBoms = $this->eligibleBoms($project, $product, $scope);
        $selectedBomIds = collect($bomIds)->map(fn ($id): int => (int) $id)->unique()->sort()->values();

        if ($selectedBomIds->isEmpty() || $selectedBomIds->diff($eligibleBoms->pluck('id'))->isNotEmpty()) {
            throw ValidationException::withMessages(['releaseSop' => 'Pilihan BOM tidak valid atau sudah berubah.']);
        }

        $brand = Brand::query()->find($metadata['brand_id']);
        $category = StoreSopCategory::query()->where('is_active', true)->find($metadata['store_sop_category_id']);
        $branchIds = collect($metadata['branch_ids'])->map(fn ($id): int => (int) $id)->unique()->values();
        $validBranchIds = Branch::query()
            ->where('is_active', true)
            ->where('brand_id', $brand?->id)
            ->whereIn('id', $branchIds)
            ->pluck('id');

        if ($brand === null || $category === null || $branchIds->isEmpty() || $validBranchIds->count() !== $branchIds->count()) {
            throw ValidationException::withMessages(['releaseSopBranchIds' => 'Brand, kategori, atau Target Branch tidak valid.']);
        }

        $releaseKey = $this->releaseKey($project, $product, $scope, $selectedBomIds->all(), $autoBomKeys, $componentKeys, $eligibleBoms);
        $existing = StoreSop::query()->where('source_release_key', $releaseKey)->first();

        if ($existing !== null) {
            return ['sop' => $existing, 'created' => false, 'branch_count' => $existing->branches()->count()];
        }

        if (StoreSop::query()->where('code', $metadata['code'])->exists()) {
            throw ValidationException::withMessages(['releaseSopCode' => 'Nomor SOP sudah digunakan.']);
        }

        $document = $this->renderDocument($project, $product, $scope, $selectedBomIds->all(), $componentKeys, $autoBomKeys);
        $originalName = str($metadata['code'])->slug()->upper().'.pdf';
        $path = 'operational/store-sops/rnd/'.$project->id.'/'.Str::uuid().'.pdf';
        $stored = Storage::disk('b2')->put($path, $document['pdf']->output());

        if (! $stored) {
            throw new RuntimeException('Dokumen SOP gagal disimpan.');
        }

        try {
            $sop = DB::transaction(function () use ($metadata, $project, $product, $scope, $releaseKey, $path, $originalName, $user, $validBranchIds): StoreSop {
                $sop = StoreSop::query()->create([
                    'brand_id' => $metadata['brand_id'],
                    'store_sop_category_id' => $metadata['store_sop_category_id'],
                    'source_rnd_project_id' => $project->id,
                    'source_rnd_project_product_id' => $product?->id,
                    'source_scope' => $scope,
                    'source_release_key' => $releaseKey,
                    'code' => $metadata['code'],
                    'title' => $metadata['title'],
                    'summary' => $metadata['summary'],
                    'file_path' => $path,
                    'original_name' => $originalName,
                    'effective_date' => $metadata['effective_date'],
                    'expires_at' => $metadata['expires_at'],
                    'status' => 'draft',
                    'created_by' => $user->id,
                ]);
                $sop->branches()->sync($validBranchIds);

                return $sop;
            });
        } catch (Throwable $exception) {
            Storage::disk('b2')->delete($path);

            throw $exception;
        }

        $branchCount = $this->publisher->publish($sop, $user);

        return ['sop' => $sop->fresh(), 'created' => true, 'branch_count' => $branchCount];
    }

    private function authorize(User $user, string $scope): void
    {
        $exportPermission = match ($scope) {
            'kitchen' => 'export kitchen bill of materials',
            'store' => 'export store bill of materials',
            default => throw ValidationException::withMessages(['releaseSop' => 'Scope PDF tidak valid.']),
        };

        if (! $user->can('view bill of materials')
            || ! $user->can($exportPermission)
            || ! $user->can('create store sops')
            || ! $user->can('publish store sops')) {
            throw new AuthorizationException;
        }
    }

    private function eligibleBoms(RndProject $project, ?RndProjectProduct $product, string $scope): Collection
    {
        $boms = $product?->boms ?? $project->products->flatMap->boms;

        return $boms
            ->filter(fn ($bom): bool => $scope === 'store'
                ? $bom->pivot->usage_type === 'menu'
                : $bom->pivot->usage_type !== 'menu')
            ->unique('id')
            ->values();
    }

    /** @return array{pdf: PDF, filename: string} */
    private function renderDocument(RndProject $project, ?RndProjectProduct $product, string $scope, array $bomIds, array $componentKeys, array $autoBomKeys): array
    {
        if ($product !== null) {
            $product->loadMissing(['boms', 'currentRegionalPrices.region']);

            return $this->productPdfController->renderDocument($project, $product, $scope, $bomIds, $componentKeys ?: null, $autoBomKeys ?: null);
        }

        $project->loadMissing([
            'products.boms',
            'products.currentRegionalPrices.region',
            'products.salesProjections.region',
            'products.salesProjections.targetBranches',
        ]);

        return $this->projectPdfController->renderDocument($project, $scope, $bomIds, $componentKeys ?: null);
    }

    private function releaseKey(RndProject $project, ?RndProjectProduct $product, string $scope, array $bomIds, array $autoBomKeys, array $componentKeys, Collection $eligibleBoms): string
    {
        $bomFingerprint = $eligibleBoms
            ->whereIn('id', $bomIds)
            ->sortBy('id')
            ->map(fn ($bom): array => [
                'id' => $bom->id,
                'updated_at' => $bom->updated_at?->toISOString(),
                'snapshot' => hash('sha256', json_encode($bom->detail_snapshot, JSON_THROW_ON_ERROR)),
            ])
            ->values()
            ->all();
        $instructionTimestamp = RndBomInstruction::query()
            ->where('rnd_project_id', $project->id)
            ->when($product, fn ($query) => $query->where('rnd_project_product_id', $product->id))
            ->max('updated_at');

        return hash('sha256', json_encode([
            'project' => $project->id,
            'product' => $product?->id,
            'scope' => $scope,
            'boms' => $bomFingerprint,
            'auto_boms' => collect($autoBomKeys)->sort()->values()->all(),
            'components' => collect($componentKeys)
                ->sortKeys()
                ->map(fn (array $keys): array => collect($keys)->sort()->values()->all())
                ->all(),
            'instructions_updated_at' => $instructionTimestamp,
        ], JSON_THROW_ON_ERROR));
    }
}
