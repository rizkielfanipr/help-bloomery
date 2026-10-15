<?php

namespace App\Actions\Rnd;

use App\Enums\MaterialSourcingStatus;
use App\Models\RndProductEsbMaterial;
use App\Models\User;
use App\Services\EsbMasterProductService;
use App\Services\EsbService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Records a material and its supplier/sample offers directly in Sourcing Bahan, without a Project
 * R&D product (e.g. a supplier sends a sample on their own). The material is chosen in the same
 * Master Product ESB picker as the BOM "Tambah Komponen" modal; only its product code and detail ID
 * come from the browser, and the product is read again from ESB here, so name, category and unit
 * are never taken from the client.
 *
 * The row is a regular sourcing record with `sourcing_origin = standalone`, so it goes through the
 * same R&D → Finance review as project sourcing instead of a second workflow. It is never pushed to
 * ESB (local `draft` status) and its code/SKU is a unique `SRC-` reference, so the same ESB product
 * can be sourced more than once; the ESB identity is kept in `esb_product_*`.
 */
class CreateStandaloneMaterialSourcingAction
{
    public function __construct(
        private readonly EsbService $esb,
        private readonly EsbMasterProductService $masterProducts,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function execute(array $data, User $actor): RndProductEsbMaterial
    {
        if (! $actor->can('submit material sourcing')) {
            throw new AuthorizationException('Anda tidak berhak membuat sourcing bahan.');
        }

        $validated = Validator::make($data, [
            'product_code' => ['required', 'string', 'max:50'],
            'product_detail_id' => ['required', 'integer', 'min:1'],
            'material_notes' => ['nullable', 'string', 'max:100'],
            'sourcings' => ['required', 'array', 'min:1'],
            'sourcings.*.supplier_name' => ['required', 'string', 'max:255'],
            'sourcings.*.brand' => ['nullable', 'string', 'max:255'],
            'sourcings.*.price' => ['required', 'numeric', 'min:0'],
            'sourcings.*.moq' => ['nullable', 'string', 'max:255'],
            'sourcings.*.lead_time_days' => ['nullable', 'integer', 'min:0'],
            'sourcings.*.contact_name' => ['nullable', 'string', 'max:255'],
            'sourcings.*.contact_phone' => ['nullable', 'string', 'max:255'],
            'sourcings.*.notes' => ['nullable', 'string'],
            'sourcings.*.attachment_path' => ['nullable', 'string', 'max:2048'],
        ], [
            'product_code.required' => 'Pilih bahan dari Master Product ESB.',
            'product_detail_id.required' => 'Pilih bahan dari Master Product ESB.',
            'sourcings.required' => 'Tambahkan minimal satu supplier.',
            'sourcings.min' => 'Tambahkan minimal satu supplier.',
            'sourcings.*.supplier_name.required' => 'Nama supplier wajib diisi.',
            'sourcings.*.price.required' => 'Harga wajib diisi.',
        ])->validate();

        $product = $this->activeProduct($validated['product_code'], (int) $validated['product_detail_id']);

        return DB::transaction(function () use ($validated, $actor, $product): RndProductEsbMaterial {
            $reference = 'SRC-'.Str::upper((string) Str::ulid());

            $material = RndProductEsbMaterial::query()->create([
                'rnd_project_product_id' => null,
                'category_id' => null,
                'category_name' => filled($product['categoryName'] ?? null) ? $product['categoryName'] : null,
                'sub_category_id' => null,
                'sub_category_name' => filled($product['subCategoryName'] ?? null) ? $product['subCategoryName'] : null,
                'uom_id' => null,
                'uom_name' => Str::upper(($product['baseUnit'] ?? '') ?: ($product['unit'] ?? '') ?: '-'),
                'product_code' => $reference,
                'product_name' => Str::limit((string) $product['productName'], 100, ''),
                'sku' => $reference,
                'notes' => filled($validated['material_notes'] ?? null) ? trim($validated['material_notes']) : null,
                'status' => 'draft',
                'esb_product_id' => (int) ($product['productID'] ?? 0) ?: null,
                'esb_product_detail_id' => (int) $product['productDetailID'],
                'esb_product_code' => (string) $product['productCode'],
                'sourcing_status' => MaterialSourcingStatus::PendingRndReview,
                'sourcing_origin' => RndProductEsbMaterial::SOURCING_ORIGIN_STANDALONE,
                'created_by' => $actor->id,
            ]);

            foreach (array_values($validated['sourcings']) as $supplier) {
                $material->sourcings()->create([...$supplier, 'submitted_by' => $actor->id]);
            }

            return $material->load('sourcings');
        });
    }

    /**
     * The picked product detail as Master Product ESB returns it now, with the same active rules as
     * the picker; a detail that no longer exists, is inactive, or belongs to another code is refused.
     *
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function activeProduct(string $productCode, int $productDetailId): array
    {
        try {
            $details = $this->masterProducts->filterActiveProductDetails(
                $this->esb->getActiveProductDetailsByCodes([$productCode]),
            );
        } catch (Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages(['product_code' => 'Master Product ESB belum dapat dihubungi. Coba lagi beberapa saat lagi.']);
        }

        $detail = $details[$productDetailId] ?? null;

        if (! is_array($detail) || (string) ($detail['productCode'] ?? '') !== $productCode || blank($detail['productName'] ?? null)) {
            throw ValidationException::withMessages(['product_code' => 'Bahan tidak ditemukan atau sudah tidak aktif di Master Product ESB. Pilih ulang bahan.']);
        }

        return $detail;
    }
}
