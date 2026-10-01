<?php

namespace App\Actions\CustomerComplaint;

use App\Enums\CustomerComplaintStatus;
use App\Models\CustomerComplaint;
use App\Models\CustomerComplaintActivity;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * docs/customer-complaints-prd.md §10.3, §11, §14.2. Handles the back office Follow-up section in
 * one call: Status, Person in Charge, Internal Notes, and Resolution. Each field that actually
 * changed is recorded as its own Activity row, all inside the same transaction as the complaint
 * update, so the timeline shows exactly what changed, by whom, and when.
 *
 * @param  array{status: string, assigned_to: ?int, internal_notes: ?string, resolution: ?string}  $data
 */
class UpdateCustomerComplaintAction
{
    public function execute(CustomerComplaint $complaint, array $data, User $actor): CustomerComplaint
    {
        // Filament's Select resolves its options' enum class eagerly, so $data['status'] may
        // already be a CustomerComplaintStatus instance (not only a raw string) depending on the
        // caller.
        $newStatus = $data['status'] instanceof CustomerComplaintStatus
            ? $data['status']
            : CustomerComplaintStatus::from($data['status']);

        if (! $complaint->status->canTransitionTo($newStatus)) {
            throw ValidationException::withMessages([
                'status' => "Transisi status dari {$complaint->status->getLabel()} ke {$newStatus->getLabel()} tidak diperbolehkan.",
            ]);
        }

        $resolution = filled($data['resolution'] ?? null) ? trim((string) $data['resolution']) : null;

        if ($newStatus->requiresResolution() && blank($resolution)) {
            throw ValidationException::withMessages([
                'resolution' => 'Resolution wajib diisi untuk status Resolved atau Closed.',
            ]);
        }

        if (filled($data['assigned_to'] ?? null)) {
            $pic = User::query()->find($data['assigned_to']);

            if (! $pic || ! $pic->is_active || ! $pic->can('update customer complaints')) {
                throw ValidationException::withMessages([
                    'assigned_to' => 'Person in Charge harus pengguna aktif yang berwenang menangani komplain.',
                ]);
            }
        }

        return DB::transaction(function () use ($complaint, $data, $newStatus, $resolution, $actor): CustomerComplaint {
            $previousStatus = $complaint->status;
            $previousAssignedTo = $complaint->assigned_to;
            $previousNotes = $complaint->internal_notes;
            $newAssignedTo = $data['assigned_to'] ?? null;
            $newNotes = filled($data['internal_notes'] ?? null) ? trim((string) $data['internal_notes']) : null;
            $now = now();

            $complaint->fill([
                'status' => $newStatus,
                'assigned_to' => $newAssignedTo,
                'internal_notes' => $newNotes,
                'resolution' => $resolution,
            ]);

            if ($newStatus === CustomerComplaintStatus::Resolved && $previousStatus !== CustomerComplaintStatus::Resolved) {
                $complaint->resolved_at = $now;
                $complaint->resolved_by = $actor->id;
            }

            if ($newStatus === CustomerComplaintStatus::Closed && $previousStatus !== CustomerComplaintStatus::Closed) {
                $complaint->closed_at = $now;
                $complaint->closed_by = $actor->id;
            }

            $complaint->save();

            if ($previousStatus !== $newStatus) {
                $complaint->activities()->create([
                    'activity_type' => CustomerComplaintActivity::TYPE_STATUS_CHANGED,
                    'previous_status' => $previousStatus->value,
                    'new_status' => $newStatus->value,
                    'created_by' => $actor->id,
                ]);
            }

            if ($previousAssignedTo !== $newAssignedTo) {
                $complaint->activities()->create([
                    'activity_type' => CustomerComplaintActivity::TYPE_PIC_CHANGED,
                    'metadata' => ['previous_assigned_to' => $previousAssignedTo, 'new_assigned_to' => $newAssignedTo],
                    'created_by' => $actor->id,
                ]);
            }

            if ($previousNotes !== $newNotes && filled($newNotes)) {
                $complaint->activities()->create([
                    'activity_type' => CustomerComplaintActivity::TYPE_NOTES_UPDATED,
                    'notes' => $newNotes,
                    'created_by' => $actor->id,
                ]);
            }

            if ($complaint->wasChanged('resolution') && filled($resolution)) {
                $complaint->activities()->create([
                    'activity_type' => CustomerComplaintActivity::TYPE_RESOLUTION_UPDATED,
                    'notes' => $resolution,
                    'created_by' => $actor->id,
                ]);
            }

            return $complaint->fresh(['activities', 'assignee']);
        });
    }
}
