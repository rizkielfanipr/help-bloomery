<?php

namespace App\Services;

use App\Enums\ServiceRequestStatus;
use App\Models\ServiceRequest;
use App\Models\User;
use Filament\Notifications\Notification;

class AssetServiceRequestAssigner
{
    public function assign(ServiceRequest $request): ?User
    {
        $activeStatuses = [
            ServiceRequestStatus::Submitted->value,
            ServiceRequestStatus::InProgress->value,
            ServiceRequestStatus::ReSubmitted->value,
        ];

        $technician = User::query()
            ->where('is_active', true)
            ->role('TECHNICIAN')
            ->get()
            ->filter(fn (User $user): bool => $user->canAccessBranch($request->branch_id))
            ->map(function (User $user) use ($activeStatuses): array {
                return [
                    'user' => $user,
                    'active_jobs' => ServiceRequest::query()->where('technician_id', $user->id)->whereIn('status', $activeStatuses)->count(),
                    'last_assignment' => ServiceRequest::query()->where('technician_id', $user->id)->max('assigned_at'),
                ];
            })
            ->sortBy(fn (array $candidate): string => sprintf('%08d-%s', $candidate['active_jobs'], $candidate['last_assignment'] ?? '0000-00-00 00:00:00'))
            ->first()['user'] ?? null;

        if (! $technician) {
            return null;
        }

        $request->update(['technician_id' => $technician->id, 'assigned_at' => now()]);

        Notification::make()
            ->title('Pekerjaan asset baru: '.$request->asset?->name)
            ->body($request->code.' · '.$request->branch?->name)
            ->info()
            ->sendToDatabase($technician);

        return $technician;
    }
}
