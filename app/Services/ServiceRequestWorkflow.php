<?php

namespace App\Services;

use App\Enums\ServiceRequestStatus;
use App\Models\ServiceRequest;
use App\Models\TechnicianSettings;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ServiceRequestWorkflow
{
    public function authorizeTechnician(ServiceRequest $request, User $actor): void
    {
        abort_unless($actor->can('edit service requests') && $request->branch_id !== null && $actor->canAccessBranch($request->branch_id)
            && ($request->technician_id === null || $request->technician_id === $actor->id || $actor->canAccessAllBranches()), 403);
    }

    /** @param array<string, mixed> $data */
    public function schedule(ServiceRequest $request, User $actor, array $data): void
    {
        $this->authorizeTechnician($request, $actor);
        $this->ensureStatus($request, [ServiceRequestStatus::Submitted, ServiceRequestStatus::Scheduled, ServiceRequestStatus::ReSubmitted]);
        Validator::make($data, ['scheduled_date' => ['required', 'date', 'after_or_equal:today'], 'priority' => ['required', 'in:normal,high,urgent']])->validate();
        if (ServiceRequest::query()->whereDate('scheduled_date', $data['scheduled_date'])->where('id', '!=', $request->id)->count() >= TechnicianSettings::instance()->max_jobs_per_day) {
            throw ValidationException::withMessages(['scheduled_date' => 'Kuota pekerjaan untuk tanggal ini sudah penuh.']);
        }
        $request->update(['scheduled_date' => $data['scheduled_date'], 'priority' => $data['priority'], 'technician_id' => $actor->id, 'status' => ServiceRequestStatus::Scheduled]);
    }

    /** @param array<string, mixed> $data */
    public function outsource(ServiceRequest $request, User $actor, array $data): void
    {
        $this->authorizeTechnician($request, $actor);
        $this->ensureStatus($request, [ServiceRequestStatus::Submitted, ServiceRequestStatus::Scheduled, ServiceRequestStatus::ReSubmitted, ServiceRequestStatus::InProgress, ServiceRequestStatus::AwaitingParts]);
        $request->update(['outsource_reason' => $data['outsource_reason'], 'technician_id' => $actor->id, 'status' => ServiceRequestStatus::Outsource]);
        Notification::make()->title('Report outsource diperlukan')->body($request->code.' · '.$data['outsource_reason'])->info()->sendToDatabase($request->scheduledBy);
    }

    /** @param array<string, mixed> $report */
    public function submitOutsourceReport(ServiceRequest $request, User $actor, array $report): void
    {
        abort_unless($request->scheduled_by === $actor->id, 403);
        $this->ensureStatus($request, [ServiceRequestStatus::Outsource]);
        $request->update(['outsource_report' => $report, 'status' => ServiceRequestStatus::AwaitingVerification]);
        if ($request->technician) {
            Notification::make()->title('Verifikasi report outsource')->body($request->code)->info()->sendToDatabase($request->technician);
        }
    }

    /** @param array<string, mixed> $data */
    public function verifyOutsource(ServiceRequest $request, User $actor, array $data): void
    {
        $this->authorizeTechnician($request, $actor);
        $this->ensureStatus($request, [ServiceRequestStatus::AwaitingVerification]);
        if ($data['result'] === 'revision') {
            $request->update(['verification_notes' => $data['notes'], 'status' => ServiceRequestStatus::Outsource]);
            Notification::make()->title('Report outsource perlu revisi')->body($request->code.' · '.$data['notes'])->warning()->sendToDatabase($request->scheduledBy);

            return;
        }
        DB::transaction(function () use ($request, $actor, $data): void {
            $request = ServiceRequest::query()->lockForUpdate()->findOrFail($request->id);
            $this->ensureStatus($request, [ServiceRequestStatus::AwaitingVerification]);
            $repair = $request->activeRepair;
            $repairData = ['after_notes' => $data['notes'], 'completed_at' => now(), 'outsource_report' => $request->outsource_report, 'warranty_expires_at' => now()->addDays(30)];
            if ($repair) {
                $repair->update($repairData);
            } else {
                $request->repairs()->create($repairData + ['technician_id' => $actor->id, 'cycle' => ($request->repairs()->max('cycle') ?? 0) + 1, 'before_notes' => $request->outsource_reason, 'started_at' => $request->updated_at]);
            }
            $request->update(['verification_notes' => $data['notes'], 'asset_condition' => $data['asset_condition'], 'status' => ServiceRequestStatus::Warranty, 'warranty_expires_at' => now()->addDays(30)]);
            $this->updateAssetAfterRepair($request);
        });
    }

    /** @param array<string, mixed> $data */
    public function start(ServiceRequest $request, User $actor, array $data): void
    {
        DB::transaction(function () use ($request, $actor, $data): void {
            $request = ServiceRequest::query()->lockForUpdate()->findOrFail($request->id);
            $this->authorizeTechnician($request, $actor);
            $this->ensureStatus($request, [ServiceRequestStatus::Submitted, ServiceRequestStatus::Scheduled, ServiceRequestStatus::ReSubmitted]);
            $request->repairs()->create([
                'technician_id' => $actor->id,
                'cycle' => ($request->repairs()->max('cycle') ?? 0) + 1,
                'before_photos' => array_values($data['photo']),
                'before_notes' => $data['notes'],
                'started_at' => now(),
                'warranty_claim_notes' => $request->warranty_claim_notes,
                'warranty_claim_attachments' => $request->warranty_claim_attachments,
            ]);
            $request->update(['status' => ServiceRequestStatus::InProgress, 'technician_id' => $actor->id, 'scheduled_date' => $request->scheduled_date ?? today(), 'diagnosis' => $data['notes'], 'asset_condition' => $request->asset_id ? $data['asset_condition'] : null]);
            if ($request->asset && $request->asset_condition === 'unsafe') {
                $request->asset->update(['is_active' => false]);
            }
        });
    }

    /** @param array<string, mixed> $data */
    public function complete(ServiceRequest $request, User $actor, array $data): void
    {
        DB::transaction(function () use ($request, $actor, $data): void {
            $request = ServiceRequest::query()->lockForUpdate()->findOrFail($request->id);
            $this->authorizeTechnician($request, $actor);
            $this->ensureStatus($request, [ServiceRequestStatus::InProgress]);
            if (! $request->activeRepair) {
                throw ValidationException::withMessages(['status' => 'Siklus perbaikan aktif belum tersedia.']);
            }
            $request->activeRepair->update(['after_photos' => array_values($data['photo']), 'after_notes' => $data['notes'], 'completed_at' => now(), 'warranty_expires_at' => now()->addDays(30)]);
            $request->update(['status' => ServiceRequestStatus::Warranty, 'asset_condition' => $data['asset_condition'], 'warranty_expires_at' => now()->addDays(30), 'warranty_claim_notes' => null, 'warranty_claim_attachments' => null]);
            $this->updateAssetAfterRepair($request);
        });
    }

    public function updateAssetAfterRepair(ServiceRequest $request): void
    {
        if (! $request->asset) {
            return;
        }
        if ($request->asset_condition === 'unsafe') {
            $request->asset->update(['is_active' => false]);
        } elseif ($request->asset_condition === 'safe' && ! $request->asset->serviceRequests()->where('id', '!=', $request->id)->where('asset_condition', 'unsafe')->exists()) {
            $request->asset->update(['is_active' => true]);
        }
    }

    /** @param array<int, ServiceRequestStatus> $statuses */
    public function ensureStatus(ServiceRequest $request, array $statuses): void
    {
        if (! in_array($request->fresh()->status, $statuses, true)) {
            throw ValidationException::withMessages(['status' => 'Status permintaan telah berubah. Muat ulang halaman sebelum melanjutkan.']);
        }
    }
}
