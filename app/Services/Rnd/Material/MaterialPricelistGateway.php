<?php

namespace App\Services\Rnd\Material;

use RuntimeException;

/**
 * Integration boundary for the ESB Master Pricelist (§18). The official create/read endpoints
 * are not proven yet, so the API mode is reported as unavailable and nothing ever pretends that a
 * price was sent to ESB. Implement `submit()`/`readBack()` here once the contract is verified,
 * behind `esb.pricelist.mode = api`.
 */
class MaterialPricelistGateway
{
    public function mode(): string
    {
        return 'manual';
    }

    public function supportsApi(): bool
    {
        return false;
    }

    public function configuredMode(): string
    {
        return (string) config('esb.pricelist.mode', 'manual');
    }

    /**
     * @throws RuntimeException
     */
    public function submit(): never
    {
        throw new RuntimeException('API Pricelist ESB belum tersedia; gunakan mode manual terkontrol.');
    }
}
