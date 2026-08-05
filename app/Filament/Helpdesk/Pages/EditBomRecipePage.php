<?php

namespace App\Filament\Helpdesk\Pages;

use App\Models\RndProjectBom;
use App\Services\EsbCoreService;

class EditBomRecipePage extends CreateBomRecipePage
{
    protected static ?string $slug = 'rnd-projects/{project}/products/{product}/bom/{bom}/edit';

    protected static ?string $title = 'Update Bill of Material';

    protected static bool $shouldRegisterNavigation = false;

    public int $bomId;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('edit bill of materials') ?? false;
    }

    public function mount(?int $project = null, ?int $product = null, ?int $bom = null): void
    {
        parent::mount($project, $product);

        abort_if(! $bom, 404);
        abort_unless(
            RndProjectBom::query()
                ->where('rnd_project_id', $this->projectId)
                ->where('esb_bom_id', $bom)
                ->whereHas('products', fn ($query) => $query->where('rnd_project_products.id', $this->productId))
                ->exists(),
            404,
        );
        $this->isEditing = true;
        $this->bomId = $bom;
        $detail = app(EsbCoreService::class)->getBillOfMaterial($bom);
        $this->usageType = mb_strtolower(trim((string) ($detail['bomTypeName'] ?? ''))) === 'menu' ? 'menu' : 'main';

        $this->data = [
            'bomName' => (string) ($detail['bomName'] ?? ''),
            'bomCode' => (string) ($detail['bomCode'] ?? ''),
            'productDetailID' => (int) ($detail['productDetailID'] ?? 0),
            'notes' => (string) ($detail['notes'] ?? ''),
            'bomCostTotal' => (float) ($detail['bomCostTotal'] ?? 0),
            'accessType' => (int) ($detail['accessType'] ?? 0),
            'selectedUserAccess' => [],
            'bomDetails' => array_map(
                fn (array $item): array => $this->materialFromBomDetail($item),
                $detail['bomDetails'] ?? [],
            ),
        ];

        $this->rememberBomProduct([
            'productDetailID' => $detail['productDetailID'] ?? 0,
            'productName' => $detail['productName'] ?? '',
            'productCode' => $detail['productCode'] ?? '',
            'uomName' => $detail['uomName'] ?? '',
        ]);

        foreach ($detail['bomDetails'] ?? [] as $item) {
            $this->rememberBomProduct($item);
        }

        if ($this->data['bomDetails'] === []) {
            $this->data['bomDetails'] = [$this->emptyMaterial()];
        }
    }

    protected function persistBom(array $payload): int
    {
        app(EsbCoreService::class)->updateBillOfMaterial($this->bomId, $payload);

        return $this->bomId;
    }

    protected function successRedirectUrl(int $bomId): string
    {
        return ViewProjectProductPage::getUrl([
            'project' => $this->projectId,
            'product' => $this->productId,
        ]);
    }
}
