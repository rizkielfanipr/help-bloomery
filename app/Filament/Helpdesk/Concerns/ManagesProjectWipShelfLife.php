<?php

namespace App\Filament\Helpdesk\Concerns;

use App\Actions\Rnd\ShelfLife\CreateWipShelfLifeAction;
use App\Enums\RndWipShelfLifeSource;
use App\Enums\RndWipShelfLifeStatus;
use App\Exceptions\Rnd\WipShelfLifeAlreadyExistsException;
use App\Models\RndProductEsbShelfLife;
use App\Models\RndProjectBom;
use App\Services\Rnd\ShelfLife\ProjectProductWipCollector;
use App\Services\Rnd\ShelfLife\WipShelfLifeResolver;
use Filament\Notifications\Notification;

/**
 * "Shelf Life WIP" section of the Project Product page (docs/rnd-wip-shelf-life-prd.md §10.2,
 * §10.3, §17). Reads masters for the WIP found by the page's existing mapping and lets an
 * authorized user fill a missing master — never edit an existing one, never store a Project
 * override. Kept out of `ViewProjectProductPage` so the page stays thin.
 *
 * Requires the host to expose `$productRecord`, `$bomComponentDetails`, `$autoWipComponentRecipes`,
 * `$bomComponentsInitialized`, and `$autoWipComponentError`.
 */
trait ManagesProjectWipShelfLife
{
    use ManagesWipShelfLifeForm;

    /**
     * Per-request cache so the section renders with a single master query.
     *
     * @var list<array<string, mixed>>|null
     */
    protected ?array $wipShelfLifeRowsCache = null;

    public function canCreateWipShelfLife(): bool
    {
        return collect(RndWipShelfLifeSource::Project->requiredPermissions())
            ->every(fn (string $permission): bool => auth()->user()?->can($permission) ?? false);
    }

    /**
     * WIP items of this Product with their master and status, built from the already-loaded
     * mapping (no ESB call) plus one bulk master query.
     *
     * @return list<array{key: string, product_detail_id: ?int, product_code: string, product_name: string, uom_name: string, paths: list<string>, recipe_resolved: bool, master: ?RndProductEsbShelfLife, status: RndWipShelfLifeStatus}>
     */
    public function wipShelfLifeRows(): array
    {
        if ($this->wipShelfLifeRowsCache !== null) {
            return $this->wipShelfLifeRowsCache;
        }

        $items = app(ProjectProductWipCollector::class)->fromMapping(
            $this->productRecord->boms->filter(fn (RndProjectBom $bom): bool => $bom->pivot->usage_type === 'main'),
            $this->bomComponentDetails,
            $this->autoWipComponentRecipes,
        );
        $masters = app(WipShelfLifeResolver::class)->masters(collect($items)->pluck('product_detail_id'));

        return $this->wipShelfLifeRowsCache = array_map(function (array $item) use ($masters): array {
            $master = $item['product_detail_id'] !== null ? $masters->get($item['product_detail_id']) : null;

            return [...$item, 'master' => $master, 'status' => RndWipShelfLifeStatus::for($item['product_detail_id'], $master)];
        }, $items);
    }

    public function openWipShelfLifeModal(int $productDetailId): void
    {
        abort_unless($this->canCreateWipShelfLife(), 403);

        $item = $this->verifiedProjectWip($productDetailId);
        $existing = app(WipShelfLifeResolver::class)->masters([$productDetailId])->get($productDetailId);

        if ($existing !== null) {
            $this->wipShelfLifeRowsCache = null;
            Notification::make()->title('Shelf Life WIP sudah tersedia')->body('Perubahan master dilakukan dari menu Shelf Life.')->info()->send();

            return;
        }

        $this->fillShelfLifeForm([
            'product_detail_id' => $productDetailId,
            'product_code' => $item['product_code'] !== '' ? $item['product_code'] : null,
            'product_name' => $item['product_name'],
            'uom_name' => $item['uom_name'] !== '' ? $item['uom_name'] : null,
        ], null);
    }

    public function saveWipShelfLife(): void
    {
        abort_unless($this->canCreateWipShelfLife(), 403);
        abort_if($this->shelfLifeTarget === null, 404);

        $item = $this->verifiedProjectWip($this->shelfLifeTarget['product_detail_id']);
        $values = $this->validatedShelfLifeValues();

        try {
            app(CreateWipShelfLifeAction::class)->execute(RndWipShelfLifeSource::Project, [
                'company_code' => RndProductEsbShelfLife::DEFAULT_COMPANY_CODE,
                'esb_product_detail_id' => $item['product_detail_id'],
                'product_code' => $item['product_code'] !== '' ? $item['product_code'] : null,
                'product_name' => $item['product_name'],
                ...$values,
            ], auth()->user());
        } catch (WipShelfLifeAlreadyExistsException) {
            $this->closeShelfLifeModal();
            $this->wipShelfLifeRowsCache = null;
            Notification::make()->title('Shelf Life WIP sudah diisi pengguna lain')->body('Nilai terbaru ditampilkan dan tidak ditimpa.')->warning()->send();

            return;
        }

        $this->closeShelfLifeModal();
        $this->wipShelfLifeRowsCache = null;
        Notification::make()->title('Shelf Life WIP tersimpan')->body('Master disimpan lokal dan langsung berlaku di menu Shelf Life serta Project lain.')->success()->send();
    }

    /**
     * The Product Detail ID must belong to a WIP of this Product according to the server-side
     * mapping (stored BOM snapshots), never only to browser state (§23).
     *
     * @return array{key: string, product_detail_id: int, product_code: string, product_name: string, uom_name: string, paths: list<string>, recipe_resolved: bool}
     */
    private function verifiedProjectWip(int $productDetailId): array
    {
        $item = collect(app(ProjectProductWipCollector::class)->forProduct($this->productRecord)['items'])
            ->first(fn (array $item): bool => $productDetailId > 0 && $item['product_detail_id'] === $productDetailId);

        abort_if($item === null, 422, 'WIP ini tidak lagi ada pada BOM Product.');

        return $item;
    }
}
