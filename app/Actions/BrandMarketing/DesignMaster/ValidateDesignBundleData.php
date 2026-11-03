<?php

namespace App\Actions\BrandMarketing\DesignMaster;

use App\Enums\MarketingDesignBundleCategory;
use App\Models\MarketingDesignBundle;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Shared server-side rules of bundle metadata: one existing Brand and a normalized code that is
 * unique within that Brand (PRD §11.1, §11.2).
 */
class ValidateDesignBundleData
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{brand_id: int, code: string, normalized_code: string, name: string, category: string, description: ?string, valid_from: ?string, valid_until: ?string}
     *
     * @throws ValidationException
     */
    public function validate(array $data, ?MarketingDesignBundle $ignore = null): array
    {
        $code = trim((string) ($data['code'] ?? ''));
        $normalizedCode = MarketingDesignBundle::normalizeCode($code);

        $validated = Validator::make(['normalized_code' => $normalizedCode] + $data, [
            'brand_id' => ['required', 'integer', Rule::exists('brands', 'id')],
            'code' => ['required', 'string', 'max:100'],
            'normalized_code' => [
                'required',
                Rule::unique('marketing_design_bundles', 'normalized_code')
                    ->where('brand_id', $data['brand_id'] ?? null)
                    ->ignore($ignore),
            ],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::enum(MarketingDesignBundleCategory::class)],
            'description' => ['nullable', 'string', 'max:5000'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
        ], [
            'brand_id.required' => 'Brand wajib dipilih.',
            'brand_id.exists' => 'Brand tidak ditemukan.',
            'code.required' => 'Kode bundle wajib diisi.',
            'normalized_code.unique' => 'Kode bundle sudah dipakai pada Brand ini.',
            'name.required' => 'Nama bundle wajib diisi.',
            'category.required' => 'Kategori wajib dipilih.',
            'valid_until.after_or_equal' => 'Tanggal akhir tidak boleh sebelum tanggal mulai.',
        ])->validate();

        return [
            'brand_id' => (int) $validated['brand_id'],
            'code' => $code,
            'normalized_code' => $normalizedCode,
            'name' => trim((string) $validated['name']),
            'category' => $validated['category'] instanceof MarketingDesignBundleCategory ? $validated['category']->value : (string) $validated['category'],
            'description' => filled($validated['description'] ?? null) ? trim((string) $validated['description']) : null,
            'valid_from' => $validated['valid_from'] ?? null,
            'valid_until' => $validated['valid_until'] ?? null,
        ];
    }

    public function duplicateCode(): ValidationException
    {
        return ValidationException::withMessages(['code' => 'Kode bundle sudah dipakai pada Brand ini.']);
    }
}
