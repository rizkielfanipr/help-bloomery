<?php

namespace App\Actions\Rnd\ShelfLife;

use App\Enums\RndShelfLifeUnit;
use App\Enums\RndStorageCondition;
use App\Models\RndProductEsbShelfLife;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Domain validation shared by the create/update Actions and the BOM Adjustment / Project forms
 * (docs/rnd-wip-shelf-life-prd.md §12, §24), so both entry points enforce identical rules.
 */
class WipShelfLifeInputValidator
{
    /**
     * Rules for the editable Shelf Life values, keyed by the given field names.
     *
     * @param  array{value: string, unit: string, storage: string, notes: string}  $fields
     * @return array<string, list<mixed>>
     */
    public function valueRules(array $fields = ['value' => 'shelf_life_value', 'unit' => 'shelf_life_unit', 'storage' => 'storage_condition', 'notes' => 'notes']): array
    {
        return [
            $fields['value'] => ['required', 'numeric', 'gt:0', 'max:'.RndProductEsbShelfLife::MAX_SHELF_LIFE_VALUE],
            $fields['unit'] => ['required', Rule::enum(RndShelfLifeUnit::class)],
            $fields['storage'] => ['required', Rule::enum(RndStorageCondition::class)],
            $fields['notes'] => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @param  array{value: string, unit: string, storage: string, notes: string}  $fields
     * @return array<string, string>
     */
    public function valueMessages(array $fields = ['value' => 'shelf_life_value', 'unit' => 'shelf_life_unit', 'storage' => 'storage_condition', 'notes' => 'notes']): array
    {
        return [
            "{$fields['value']}.required" => 'Masa simpan wajib diisi.',
            "{$fields['value']}.numeric" => 'Masa simpan harus berupa angka.',
            "{$fields['value']}.gt" => 'Masa simpan harus lebih besar dari 0.',
            "{$fields['value']}.max" => 'Masa simpan terlalu besar.',
            "{$fields['unit']}.required" => 'Satuan wajib dipilih.',
            "{$fields['unit']}.enum" => 'Satuan tidak valid.',
            "{$fields['storage']}.required" => 'Kondisi penyimpanan wajib dipilih.',
            "{$fields['storage']}.enum" => 'Kondisi penyimpanan tidak valid.',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{shelf_life_value: string, shelf_life_unit: string, storage_condition: string, notes: ?string}
     */
    public function values(array $data): array
    {
        $validated = Validator::make($data, $this->valueRules(), $this->valueMessages())->validate();

        return [
            'shelf_life_value' => (string) $validated['shelf_life_value'],
            'shelf_life_unit' => $validated['shelf_life_unit'],
            'storage_condition' => $validated['storage_condition'],
            'notes' => filled($validated['notes'] ?? null) ? trim($validated['notes']) : null,
        ];
    }

    /**
     * Identity is only ever company + Product Detail ID; code/name are display snapshots.
     *
     * @param  array<string, mixed>  $data
     * @return array{company_code: string, esb_product_detail_id: int, product_code: ?string, product_name: string}
     */
    public function identity(array $data): array
    {
        $validated = Validator::make($data, [
            'company_code' => ['sometimes', Rule::in([RndProductEsbShelfLife::DEFAULT_COMPANY_CODE])],
            'esb_product_detail_id' => ['required', 'integer', 'min:1'],
            'product_code' => ['nullable', 'string', 'max:255'],
            'product_name' => ['required', 'string', 'max:255'],
        ], [
            'esb_product_detail_id.*' => 'Identitas WIP belum lengkap (Product Detail ID tidak tersedia).',
            'company_code.in' => 'Company tidak didukung.',
        ])->validate();

        return [
            'company_code' => $validated['company_code'] ?? RndProductEsbShelfLife::DEFAULT_COMPANY_CODE,
            'esb_product_detail_id' => (int) $validated['esb_product_detail_id'],
            'product_code' => filled($validated['product_code'] ?? null) ? trim($validated['product_code']) : null,
            'product_name' => trim($validated['product_name']),
        ];
    }
}
