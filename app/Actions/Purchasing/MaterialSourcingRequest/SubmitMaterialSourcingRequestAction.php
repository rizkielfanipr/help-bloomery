<?php

namespace App\Actions\Purchasing\MaterialSourcingRequest;

use App\Enums\MaterialSourcingRequestStatus;
use App\Models\MaterialSourcingRequest;
use App\Models\User;
use App\Services\Purchasing\MaterialSourcingRequestAttachmentStore;
use App\Services\Purchasing\MaterialSourcingRequestNotifier;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Creates a Request Sourcing from the Employee App (PRD §10.1/§11). Requester, branch, and company
 * come from the server; the product is read again from ESB, which is never written to. A repeated
 * submit with the same submission key returns the request that already exists.
 */
class SubmitMaterialSourcingRequestAction
{
    public function __construct(
        private readonly ValidateMaterialSourcingRequestInput $input,
        private readonly MaterialSourcingRequestAttachmentStore $attachments,
        private readonly MaterialSourcingRequestNotifier $notifier,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function execute(User $actor, array $input): MaterialSourcingRequest
    {
        Gate::forUser($actor)->authorize('create', MaterialSourcingRequest::class);

        if (! $actor->branch_id) {
            throw ValidationException::withMessages(['branch' => 'Cabang Anda belum diatur. Hubungi admin sebelum mengirim request.']);
        }

        $submissionKey = (string) ($input['submissionKey'] ?? '');
        if (! Str::isUuid($submissionKey)) {
            throw ValidationException::withMessages(['submissionKey' => 'Form kedaluwarsa. Muat ulang halaman lalu kirim lagi.']);
        }

        if ($existing = $this->existing($actor, $submissionKey)) {
            return $existing;
        }

        ['data' => $data, 'product' => $product] = $this->input->execute($input);
        $actor->loadMissing('branch');

        $storedPaths = [];

        try {
            $request = DB::transaction(function () use ($actor, $submissionKey, $data, $product, &$storedPaths): MaterialSourcingRequest {
                $request = MaterialSourcingRequest::query()->create([
                    'submission_key' => $submissionKey,
                    'status' => MaterialSourcingRequestStatus::Submitted,
                    'company_code' => MaterialSourcingRequest::COMPANY_CODE,
                    'requester_id' => $actor->id,
                    'requester_name' => $actor->name,
                    'branch_id' => $actor->branch_id,
                    'branch_name' => $actor->branch?->name ?? 'Tanpa Cabang',
                    'submitted_at' => now(),
                    ...ValidateMaterialSourcingRequestInput::productSnapshot($product),
                    ...ValidateMaterialSourcingRequestInput::needColumns($data),
                ]);

                $stored = $this->attachments->store($request, array_values($data['attachments'] ?? []));
                $storedPaths = array_column($stored, 'path');

                $request->forceFill([
                    'request_number' => MaterialSourcingRequest::numberFor($request),
                    'attachments' => $stored ?: null,
                ])->save();

                $request->statusHistories()->create([
                    'from_status' => null,
                    'to_status' => MaterialSourcingRequestStatus::Submitted,
                    'actor_id' => $actor->id,
                    'metadata' => ['event' => 'submitted', 'attachments' => count($stored)],
                ]);

                return $request;
            });
        } catch (UniqueConstraintViolationException $exception) {
            $this->attachments->delete($storedPaths);

            return $this->existing($actor, $submissionKey) ?? throw $exception;
        } catch (Throwable $exception) {
            $this->attachments->delete($storedPaths);

            throw $exception;
        }

        $this->notifier->submitted($request);

        return $request;
    }

    private function existing(User $actor, string $submissionKey): ?MaterialSourcingRequest
    {
        $existing = MaterialSourcingRequest::query()->where('submission_key', $submissionKey)->first();
        if ($existing && $existing->requester_id !== $actor->id) {
            throw ValidationException::withMessages(['submissionKey' => 'Form kedaluwarsa. Muat ulang halaman lalu kirim lagi.']);
        }

        return $existing;
    }
}
