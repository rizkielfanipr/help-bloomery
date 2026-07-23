<?php

namespace App\Filament\Helpdesk\Pages;

use App\Services\EsbCoreService;

class EditBomRecipePage extends CreateBomRecipePage
{
    protected static ?string $slug = 'bill-of-material/{bom}/edit';

    protected static ?string $title = 'Update Bill of Material';

    protected static bool $shouldRegisterNavigation = false;

    public int $bomId;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user?->hasRole('SUPERADMIN')
            || ($user?->can('edit bill of materials') ?? false);
    }

    public function mount(?int $bom = null): void
    {
        parent::mount();

        abort_if(! $bom, 404);
        $this->isEditing = true;
        $this->bomId = $bom;
        $detail = app(EsbCoreService::class)->getBillOfMaterial($bom);

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
        return ViewBomPage::getUrl(['bom' => $bomId]);
    }
}
