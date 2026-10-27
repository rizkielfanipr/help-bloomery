<?php

namespace Database\Factories;

use App\Enums\MaterialEsbSyncStatus;
use App\Enums\MaterialPricingStatus;
use App\Enums\MaterialReviewStatus;
use App\Enums\MaterialSourcingStatus;
use App\Models\RndProductEsbMaterial;
use App\Models\RndProject;
use App\Models\RndProjectProduct;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Master Bahan fixtures. The default is a workflow Draft (raw material) with one base unit;
 * `legacy()` reproduces a pre-workflow row. No state performs an ESB call.
 *
 * @extends Factory<RndProductEsbMaterial>
 */
class RndProductEsbMaterialFactory extends Factory
{
    public function definition(): array
    {
        $code = 'BBM-'.Str::upper(Str::random(6));

        return [
            'rnd_project_product_id' => fn (): int => RndProjectProduct::query()->create([
                'rnd_project_id' => RndProject::factory()->create()->id,
                'name' => 'Produk '.fake()->words(2, true),
                'status' => 'development',
            ])->id,
            'category_id' => 11,
            'category_name' => 'Bahan Baku Makanan',
            'sub_category_id' => 21,
            'sub_category_name' => 'Tepung',
            'material_section' => null,
            'uom_id' => 5,
            'uom_name' => 'GR',
            'product_code' => $code,
            'product_name' => 'Bahan '.fake()->unique()->words(2, true),
            'sku' => $code.'-GR',
            'conversion_factor' => 1,
            'base_price' => 100,
            'status' => 'draft',
            'review_status' => MaterialReviewStatus::Draft,
            'submission_version' => 0,
            'esb_sync_status' => MaterialEsbSyncStatus::NotReady,
            'pricing_status' => MaterialPricingStatus::NotReady,
            'sourcing_status' => MaterialSourcingStatus::NotStarted,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (RndProductEsbMaterial $material): void {
            if ($material->units()->exists()) {
                return;
            }

            $material->units()->create([
                'uom_id' => $material->uom_id ?? 5,
                'uom_name' => $material->uom_name,
                'sku' => $material->sku,
                'conversion_factor' => 1,
                'base_price' => $material->base_price,
                'is_base' => true,
                'is_stock' => true,
                'is_purchase' => true,
                'is_transfer' => true,
                'is_sales' => true,
                'flag_active' => true,
            ]);
        });
    }

    public function legacy(): static
    {
        return $this->state(fn (): array => ['review_status' => null, 'esb_sync_status' => null]);
    }

    public function wip(): static
    {
        return $this->state(fn (array $attributes): array => [
            'category_name' => 'Barang WIP',
            'sub_category_name' => 'Adonan',
            'product_code' => 'BW'.Str::upper(Str::random(5)),
            'sku' => 'BW'.Str::upper(Str::random(8)),
        ]);
    }

    public function waitingItReview(): static
    {
        return $this->state(fn (): array => ['review_status' => MaterialReviewStatus::WaitingItReview, 'submission_version' => 1]);
    }

    public function revisionRequired(): static
    {
        return $this->state(fn (): array => ['review_status' => MaterialReviewStatus::RevisionRequired, 'submission_version' => 1]);
    }

    /**
     * Verified ESB identity: Product ID plus Product Detail ID on the base unit.
     */
    public function synced(int $productId = 8100, int $detailId = 9100): static
    {
        return $this->state(fn (): array => [
            'review_status' => MaterialReviewStatus::ApprovedForEsb,
            'submission_version' => 1,
            'status' => 'synced',
            'esb_sync_status' => MaterialEsbSyncStatus::Synced,
            'esb_verified_at' => now(),
            'esb_product_id' => $productId,
            'synced_at' => now(),
        ])->afterCreating(function (RndProductEsbMaterial $material) use ($detailId): void {
            $material->units()->update(['esb_product_detail_id' => $detailId]);
        });
    }

    public function sampleApproved(): static
    {
        return $this->synced()->state(fn (): array => ['sourcing_status' => MaterialSourcingStatus::ApprovedByRnd]);
    }
}
