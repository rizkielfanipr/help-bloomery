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
            return $sop->branches()->count();
        }

        return DB::transaction(function () use ($sop, $publisher): int {
            $branchIds = $sop->branches()->pluck('branches.id');
            $recipients = User::query()
                ->where('is_active', true)
                ->permission('access employee app store sop')
                ->get()
                ->filter(fn (User $user): bool => $branchIds->contains(fn ($branchId): bool => $user->canAccessBranch((int) $branchId)));

            $sop->update(['status' => 'published', 'published_by' => $publisher->id, 'published_at' => now()]);

            if ($recipients->isNotEmpty()) {
                $validityPeriod = $sop->expires_at
                    ? $sop->effective_date->format('d M Y').'–'.$sop->expires_at->format('d M Y')
                    : 'mulai '.$sop->effective_date->format('d M Y');

                Notification::make()
                    ->title('SOP Store baru: '.$sop->title)
                    ->body('Berlaku '.$validityPeriod.'. Silakan dibaca dan dikonfirmasi.')
                    ->info()
                    ->sendToDatabase($recipients);
            }

            return $branchIds->count();
        });
    }

    public function syncAssignmentsForUser(User $user): void
    {
        if (! $user->is_active || ! $user->can('access employee app store sop')) {
            return;
        }

        $branchIds = $user->accessibleBranchIds();
        if ($branchIds->isEmpty()) {
            return;
        }

        $sops = StoreSop::query()
            ->where('status', 'published')
            ->whereHas('branches', fn ($query) => $query->whereIn('branches.id', $branchIds))
            ->with(['branches' => fn ($query) => $query->whereIn('branches.id', $branchIds)])
            ->get();

        $sops->each(function (StoreSop $sop) use ($user): void {
            $sop->branches->each(function ($branch) use ($sop, $user): void {
                StoreSopAssignment::firstOrCreate(
                    ['store_sop_id' => $sop->id, 'branch_id' => $branch->id, 'user_id' => $user->id],
                    ['assigned_at' => $sop->published_at ?? now()],
                );
            });
        });
    }
}
