<?php

namespace App\Services;

use App\Models\StoreSop;
use App\Models\StoreSopAssignment;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class StoreSopPublisher
{
    public function publish(StoreSop $sop, User $publisher): int
    {
        if ($sop->status === 'published') {
            return $sop->assignments()->count();
        }

        return DB::transaction(function () use ($sop, $publisher): int {
            $branchIds = $sop->branches()->pluck('branches.id');
            $supervisors = User::query()
                ->where('is_active', true)
                ->role('SUPERVISOR_STORE')
                ->where(function ($query) use ($branchIds): void {
                    $query->whereIn('branch_id', $branchIds)
                        ->orWhereHas('accessibleBranches', fn ($branches) => $branches->whereIn('branches.id', $branchIds));
                })->get();

            $assignmentCount = 0;
            foreach ($branchIds as $branchId) {
                foreach ($supervisors->filter(fn (User $user): bool => $user->canAccessBranch((int) $branchId)) as $supervisor) {
                    StoreSopAssignment::firstOrCreate(
                        ['store_sop_id' => $sop->id, 'branch_id' => $branchId, 'user_id' => $supervisor->id],
                        ['assigned_at' => now()],
                    );
                    $assignmentCount++;
                }
            }

            $sop->update(['status' => 'published', 'published_by' => $publisher->id, 'published_at' => now()]);

            if ($supervisors->isNotEmpty()) {
                Notification::make()
                    ->title('SOP Store baru: '.$sop->title)
                    ->body('Versi '.$sop->version.' berlaku mulai '.$sop->effective_date->format('d M Y').'. Silakan dibaca dan dikonfirmasi.')
                    ->info()
                    ->sendToDatabase($supervisors);
            }

            return $assignmentCount;
        });
    }
}
