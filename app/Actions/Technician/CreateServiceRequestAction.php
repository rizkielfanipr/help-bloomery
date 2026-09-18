<?php

namespace App\Actions\Technician;

use App\Enums\ServiceRequestStatus;
use App\Models\Asset;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\Technician\AssetServiceRequestAssigner;
use Illuminate\Support\Facades\DB;

class CreateServiceRequestAction
{
    public function __construct(private readonly AssetServiceRequestAssigner $assigner) {}

    /** @param list<string> $attachmentPaths */
    public function execute(User $requester, string $notes, array $attachmentPaths, ?Asset $asset = null): ServiceRequest
    {
        if ($asset) {
            abort_unless($asset->is_active && $requester->canAccessBranch($asset->branch_id), 403);
        } else {
            abort_unless($requester->branch_id !== null, 403);
        }

        $request = DB::transaction(fn (): ServiceRequest => ServiceRequest::query()->create([
            'scheduled_by' => $requester->id,
            'branch_id' => $asset?->branch_id ?? $requester->branch_id,
            'technician_id' => null,
            'scheduled_date' => null,
            'asset_id' => $asset?->id,
            'source' => $asset ? 'asset_qr' : 'manual',
            'requestor_notes' => $notes,
            'attachments' => $attachmentPaths ?: null,
            'status' => ServiceRequestStatus::Submitted,
        ]));

        $this->assigner->assign($request->load(['asset', 'branch']));

        return $request->load('technician');
    }
}
