<?php

namespace App\Actions\Purchasing\MaterialSourcingRequest;

use App\Actions\Rnd\CreateStandaloneMaterialSourcingAction;
use App\Enums\MaterialSourcingRequestStatus;
use App\Models\MaterialSourcingRequest;
use App\Models\RndProductEsbMaterial;
use App\Models\User;
use App\Services\Purchasing\MaterialSourcingRequestNotifier;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Turns one request into one standalone sourcing (PRD §10.2/§15.6-7). The request row stays locked
 * while CreateStandaloneMaterialSourcingAction verifies the product again and creates the sourcing,
 * so a double or concurrent click cannot create a second one; a request that is already converted
 * returns its existing sourcing. The unique `rnd_product_esb_material_id` is the database guard.
 */
class ConvertMaterialSourcingRequestAction
{
    public function __construct(
        private readonly CreateStandaloneMaterialSourcingAction $createSourcing,
        private readonly TransitionMaterialSourcingRequestAction $transition,
        private readonly MaterialSourcingRequestNotifier $notifier,
    ) {}

    /**
     * @param  array<string, mixed>  $data  `material_notes` and `sourcings`, as in Buat Sourcing
     *
     * @throws AuthorizationException|ValidationException
     */
    public function execute(User $actor, MaterialSourcingRequest $request, array $data): RndProductEsbMaterial
    {
        $converted = false;

        try {
            $material = DB::transaction(function () use ($actor, $request, $data, &$converted): RndProductEsbMaterial {
                $locked = MaterialSourcingRequest::query()->whereKey($request->getKey())->lockForUpdate()->firstOrFail();
                if ($locked->rnd_product_esb_material_id) {
                    return RndProductEsbMaterial::query()->findOrFail($locked->rnd_product_esb_material_id);
                }
                if ($locked->status !== MaterialSourcingRequestStatus::InReview) {
                    throw ValidationException::withMessages(['status' => 'Request tidak sedang diproses Purchasing.']);
                }
                Gate::forUser($actor)->authorize('convert', $locked);

                $material = $this->createSourcing->execute([
                    'product_code' => $locked->product_code,
                    'product_detail_id' => $locked->esb_product_detail_id,
                    'material_notes' => $data['material_notes'] ?? null,
                    'sourcings' => array_values((array) ($data['sourcings'] ?? [])),
                ], $actor);

                $this->transition->execute($locked, MaterialSourcingRequestStatus::Converted, $actor, null, [
                    'rnd_product_esb_material_id' => $material->id,
                    'converted_at' => now(),
                ], ['event' => 'converted', 'rnd_product_esb_material_id' => $material->id]);
                $converted = true;

                return $material;
            });
        } catch (Throwable $exception) {
            Log::warning('Material sourcing request conversion failed.', ['request_id' => $request->id, 'actor_id' => $actor->id, 'exception' => $exception::class]);

            throw $exception;
        }

        if ($converted) {
            Log::info('Material sourcing request converted.', ['request_id' => $request->id, 'actor_id' => $actor->id, 'rnd_product_esb_material_id' => $material->id]);
            $this->notifier->converted($request->refresh());
        }

        return $material;
    }
}
