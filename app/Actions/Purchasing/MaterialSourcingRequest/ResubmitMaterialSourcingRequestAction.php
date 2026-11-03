<?php

namespace App\Actions\Purchasing\MaterialSourcingRequest;

use App\Enums\MaterialSourcingRequestStatus;
use App\Models\MaterialSourcingRequest;
use App\Models\User;
use App\Services\Purchasing\MaterialSourcingRequestAttachmentStore;
use App\Services\Purchasing\MaterialSourcingRequestNotifier;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * The requester corrects a request Purchasing returned (`revision_requested`) and sends it back to
 * the queue as `submitted` (PRD §11.12). The product is verified again; removed attachments are
 * deleted from storage only after the change commits.
 */
class ResubmitMaterialSourcingRequestAction
{
    public function __construct(
        private readonly ValidateMaterialSourcingRequestInput $input,
        private readonly TransitionMaterialSourcingRequestAction $transition,
        private readonly MaterialSourcingRequestAttachmentStore $attachments,
        private readonly MaterialSourcingRequestNotifier $notifier,
    ) {}

    /**
     * @param  array<string, mixed>  $input  form fields plus `removedAttachmentPaths`
     *
     * @throws AuthorizationException|ValidationException
     */
    public function execute(User $actor, MaterialSourcingRequest $request, array $input): MaterialSourcingRequest
    {
        if ($request->requester_id !== $actor->id) {
            throw new AuthorizationException('Request ini bukan milik Anda.');
        }
        if ($request->status === MaterialSourcingRequestStatus::Submitted) {
            return $request;
        }
        Gate::forUser($actor)->authorize('resubmit', $request);

        $existingPaths = array_column($request->attachmentList(), 'path');
        $removed = array_values(array_intersect($existingPaths, array_map('strval', (array) ($input['removedAttachmentPaths'] ?? []))));
        ['data' => $data, 'product' => $product] = $this->input->execute($input, count($existingPaths) - count($removed));

        $storedPaths = [];
        $transitioned = false;

        try {
            $updated = DB::transaction(function () use ($actor, $request, $data, $product, $removed, &$storedPaths, &$transitioned): MaterialSourcingRequest {
                $locked = MaterialSourcingRequest::query()->whereKey($request->getKey())->lockForUpdate()->firstOrFail();
                if ($locked->status === MaterialSourcingRequestStatus::Submitted) {
                    return $locked;
                }

                $kept = array_values(array_filter($locked->attachmentList(), fn (array $attachment): bool => ! in_array($attachment['path'], $removed, true)));
                $stored = $this->attachments->store($locked, array_values($data['attachments'] ?? []));
                $storedPaths = array_column($stored, 'path');

                $transitioned = true;

                return $this->transition->execute($locked, MaterialSourcingRequestStatus::Submitted, $actor, null, [
                    ...ValidateMaterialSourcingRequestInput::productSnapshot($product),
                    ...ValidateMaterialSourcingRequestInput::needColumns($data),
                    'attachments' => array_merge($kept, $stored) ?: null,
                    'assigned_to' => null,
                    'claimed_at' => null,
                    'submitted_at' => now(),
                ], ['event' => 'resubmitted', 'attachments_added' => count($stored), 'attachments_removed' => count($removed)]);
            });
        } catch (Throwable $exception) {
            $this->attachments->delete($storedPaths);

            throw $exception;
        }

        if ($transitioned) {
            $this->attachments->delete($removed);
            $this->notifier->submitted($updated, resubmitted: true);
        }

        return $updated;
    }
}
