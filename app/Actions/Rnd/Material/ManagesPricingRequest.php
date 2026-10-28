<?php

namespace App\Actions\Rnd\Material;

use App\Enums\MaterialPricingStatus;
use App\Models\RndMaterialPricingRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

trait ManagesPricingRequest
{
    /**
     * @param  list<MaterialPricingStatus>  $allowed
     *
     * @throws AuthorizationException|ValidationException
     */
    protected function lockForFinance(RndMaterialPricingRequest $request, User $actor, array $allowed): RndMaterialPricingRequest
    {
        if (! Gate::forUser($actor)->allows('manage', $request)) {
            throw new AuthorizationException('Anda tidak berhak mengelola Product Pricelist.');
        }

        $request = RndMaterialPricingRequest::query()->lockForUpdate()->findOrFail($request->id);
        if (! in_array($request->status, $allowed, true)) {
            throw ValidationException::withMessages(['status' => 'Status Pricelist '.$request->status->getLabel().' tidak dapat diproses dengan aksi ini.']);
        }

        return $request;
    }

    protected function syncMaterialPricingStatus(RndMaterialPricingRequest $request): void
    {
        $request->material()->update(['pricing_status' => $request->status->value]);
    }
}
