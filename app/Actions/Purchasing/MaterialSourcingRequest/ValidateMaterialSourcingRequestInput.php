<?php

namespace App\Actions\Purchasing\MaterialSourcingRequest;

use App\Models\MaterialSourcingRequest;
use App\Services\Rnd\Material\ActiveEsbProductDetailResolver;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Shared input rules of submit and resubmit (PRD §11). Keys match the Employee App form so errors
 * land next to their field. The product is read again from ESB; the browser only sends its code and
 * detail ID.
 */
class ValidateMaterialSourcingRequestInput
{
    public function __construct(private readonly ActiveEsbProductDetailResolver $products) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array{data: array<string, mixed>, product: array<string, mixed>}
     *
     * @throws ValidationException
     */
    public function execute(array $input, int $keptAttachments = 0): array
    {
        $maxNew = max(0, MaterialSourcingRequest::MAX_ATTACHMENTS - $keptAttachments);

        $data = Validator::make($input, [
            'productCode' => ['required', 'string', 'max:50'],
            'productDetailId' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
            'estimatedQuantity' => ['nullable', 'numeric', 'gt:0', 'max:99999999999'],
            'targetDate' => ['nullable', 'date', 'after_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'attachments' => ['array', 'max:'.$maxNew],
            'attachments.*' => ['file', 'mimes:'.implode(',', MaterialSourcingRequest::ATTACHMENT_EXTENSIONS), 'max:'.MaterialSourcingRequest::MAX_ATTACHMENT_KILOBYTES],
        ], [
            'productCode.required' => 'Pilih produk dari Master Product ESB.',
            'productDetailId.required' => 'Pilih produk dari Master Product ESB.',
            'reason.required' => 'Alasan atau tujuan request wajib diisi.',
            'reason.min' => 'Alasan atau tujuan request minimal 5 karakter.',
            'estimatedQuantity.gt' => 'Estimasi kebutuhan harus lebih dari 0.',
            'targetDate.after_or_equal' => 'Target tanggal tidak boleh sebelum hari ini.',
            'attachments.max' => 'Lampiran maksimal '.MaterialSourcingRequest::MAX_ATTACHMENTS.' file.',
            'attachments.*.mimes' => 'Lampiran harus berupa JPG, JPEG, PNG, WEBP, atau PDF.',
            'attachments.*.max' => 'Ukuran lampiran maksimal 10 MB per file.',
        ])->validate();

        try {
            $product = $this->products->find((string) $data['productCode'], (int) $data['productDetailId']);
        } catch (Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages(['productCode' => 'Master Product ESB belum dapat dihubungi. Data form tetap tersimpan; coba kirim lagi beberapa saat lagi.']);
        }

        if ($product === null) {
            throw ValidationException::withMessages(['productCode' => 'Produk tidak ditemukan atau sudah tidak aktif di Master Product ESB. Pilih ulang produk.']);
        }

        return ['data' => $data, 'product' => $product];
    }

    /**
     * Snapshot columns from the ESB product, never from the browser.
     *
     * @param  array<string, mixed>  $product
     * @return array<string, mixed>
     */
    public static function productSnapshot(array $product): array
    {
        return [
            'esb_product_id' => (int) ($product['productID'] ?? 0) ?: null,
            'esb_product_detail_id' => (int) $product['productDetailID'],
            'product_code' => (string) $product['productCode'],
            'product_name' => mb_substr((string) $product['productName'], 0, 255),
            'category_name' => filled($product['categoryName'] ?? null) ? (string) $product['categoryName'] : null,
            'sub_category_name' => filled($product['subCategoryName'] ?? null) ? (string) $product['subCategoryName'] : null,
            'uom_name' => mb_substr((string) (($product['unit'] ?? '') ?: ($product['baseUnit'] ?? '') ?: '-'), 0, 50),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function needColumns(array $data): array
    {
        return [
            'reason' => trim((string) $data['reason']),
            'estimated_quantity' => filled($data['estimatedQuantity'] ?? null) ? (float) $data['estimatedQuantity'] : null,
            'target_date' => filled($data['targetDate'] ?? null) ? $data['targetDate'] : null,
            'notes' => filled($data['notes'] ?? null) ? trim((string) $data['notes']) : null,
        ];
    }
}
