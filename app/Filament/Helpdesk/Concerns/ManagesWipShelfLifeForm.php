<?php

namespace App\Filament\Helpdesk\Concerns;

use App\Actions\Rnd\ShelfLife\WipShelfLifeInputValidator;
use App\Enums\RndShelfLifeUnit;
use App\Enums\RndStorageCondition;
use App\Models\RndProductEsbShelfLife;
use Livewire\Attributes\Locked;

/**
 * Modal state and presentation validation for the WIP Shelf Life form, shared by BOM Adjustment
 * and the Project Product page (docs/rnd-wip-shelf-life-prd.md §16.3, §17). Hosts decide which
 * WIP may be targeted and call the create/update Actions; this trait never writes anything.
 */
trait ManagesWipShelfLifeForm
{
    public bool $shelfLifeModalOpen = false;

    /**
     * Display identity of the WIP being edited. Locked so the browser cannot swap the target;
     * hosts still re-resolve it server-side before saving.
     *
     * @var array{product_detail_id: int, product_code: ?string, product_name: string, uom_name: ?string, is_existing: bool}|null
     */
    #[Locked]
    public ?array $shelfLifeTarget = null;

    public string $shelfLifeValue = '';

    public string $shelfLifeUnit = 'day';

    public string $shelfLifeStorageCondition = 'dry';

    public string $shelfLifeNotes = '';

    /**
     * @param  array{product_detail_id: int, product_code: ?string, product_name: string, uom_name: ?string}  $identity
     */
    protected function fillShelfLifeForm(array $identity, ?RndProductEsbShelfLife $master): void
    {
        $this->resetValidation();
        $this->shelfLifeTarget = [...$identity, 'is_existing' => $master !== null];
        $this->shelfLifeValue = $master !== null ? rtrim(rtrim((string) $master->shelf_life_value, '0'), '.') : '';
        $this->shelfLifeUnit = $master?->shelfLifeUnit()?->value ?? RndShelfLifeUnit::Day->value;
        $this->shelfLifeStorageCondition = $master?->storageCondition()?->value ?? RndStorageCondition::Dry->value;
        $this->shelfLifeNotes = (string) ($master?->notes ?? '');
        $this->shelfLifeModalOpen = true;
    }

    public function closeShelfLifeModal(): void
    {
        $this->resetValidation();
        $this->shelfLifeModalOpen = false;
        $this->shelfLifeTarget = null;
    }

    /**
     * Validates the form fields (errors stay next to each field) and returns the Action payload.
     *
     * @return array{shelf_life_value: string, shelf_life_unit: string, storage_condition: string, notes: ?string}
     */
    protected function validatedShelfLifeValues(): array
    {
        $validator = app(WipShelfLifeInputValidator::class);
        $fields = ['value' => 'shelfLifeValue', 'unit' => 'shelfLifeUnit', 'storage' => 'shelfLifeStorageCondition', 'notes' => 'shelfLifeNotes'];
        $validated = $this->validate($validator->valueRules($fields), $validator->valueMessages($fields));

        return [
            'shelf_life_value' => (string) $validated['shelfLifeValue'],
            'shelf_life_unit' => $validated['shelfLifeUnit'],
            'storage_condition' => $validated['shelfLifeStorageCondition'],
            'notes' => filled($validated['shelfLifeNotes'] ?? null) ? $validated['shelfLifeNotes'] : null,
        ];
    }

    /** @return array<string, string> */
    public function shelfLifeUnitOptions(): array
    {
        return RndShelfLifeUnit::options();
    }

    /** @return array<string, string> */
    public function shelfLifeStorageOptions(): array
    {
        return RndStorageCondition::options();
    }
}
