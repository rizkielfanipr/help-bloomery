<?php

namespace App\Services\Rnd\Material;

use App\Services\EsbMasterProductService;
use App\Services\EsbService;

/**
 * Reads one picked Master Product ESB detail again on the server, with the same active rules as the
 * Employee App and Back Office pickers. Browser rows are display data only; standalone sourcing and
 * Request Sourcing both trust this lookup instead.
 */
class ActiveEsbProductDetailResolver
{
    public function __construct(
        private readonly EsbService $esb,
        private readonly EsbMasterProductService $masterProducts,
    ) {}

    /**
     * The active detail, or null when it no longer exists, is inactive, or belongs to another code.
     * Connection and configuration failures are thrown so callers can tell them apart.
     *
     * @return array<string, mixed>|null
     */
    public function find(string $productCode, int $productDetailId): ?array
    {
        $details = $this->masterProducts->filterActiveProductDetails(
            $this->esb->getActiveProductDetailsByCodes([$productCode]),
        );
        $detail = $details[$productDetailId] ?? null;

        if (! is_array($detail) || (string) ($detail['productCode'] ?? '') !== $productCode || blank($detail['productName'] ?? null)) {
            return null;
        }

        return $detail;
    }
}
