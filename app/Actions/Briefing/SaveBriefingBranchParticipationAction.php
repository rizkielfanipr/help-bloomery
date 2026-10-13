<?php

namespace App\Actions\Briefing;

use App\Models\Branch;
use App\Models\BriefingBranchSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SaveBriefingBranchParticipationAction
{
    public function execute(User $actor, int $branchId): BriefingBranchSetting
    {
        $this->authorize($actor);

        $validated = Validator::make(['branch_id' => $branchId], [
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
        ])->validate();
        $startedAt = today()->toDateString();

        return DB::transaction(function () use ($actor, $startedAt, $validated): BriefingBranchSetting {
            Branch::query()->lockForUpdate()->findOrFail($validated['branch_id']);

            $overlapsExistingPeriod = BriefingBranchSetting::query()
                ->where('branch_id', $validated['branch_id'])
                ->where('is_enabled', true)
                ->whereDate('started_at', '<=', $startedAt)
                ->where(fn ($query) => $query
                    ->whereNull('ended_at')
                    ->orWhereDate('ended_at', '>=', $startedAt))
                ->exists();

            if ($overlapsExistingPeriod) {
                throw ValidationException::withMessages([
                    'branch_id' => 'Periode partisipasi branch bertabrakan dengan periode yang sudah ada.',
                ]);
            }

            return BriefingBranchSetting::create([
                'branch_id' => $validated['branch_id'],
                'is_enabled' => true,
                'started_at' => $startedAt,
                'ended_at' => null,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
        });
    }

    public function deactivate(User $actor, int $settingId): BriefingBranchSetting
    {
        $this->authorize($actor);

        return DB::transaction(function () use ($actor, $settingId): BriefingBranchSetting {
            $setting = BriefingBranchSetting::query()->lockForUpdate()->findOrFail($settingId);
            $today = today();

            if (! $setting->is_enabled || $setting->ended_at?->lt($today)) {
                return $setting;
            }

            if ($setting->started_at->lt($today)) {
                $setting->ended_at = $today->copy()->subDay();
            } else {
                $setting->is_enabled = false;
                $setting->ended_at = $today;
            }

            $setting->updated_by = $actor->id;
            $setting->save();

            return $setting;
        });
    }

    private function authorize(User $actor): void
    {
        if (! $actor->can('edit briefing settings')) {
            throw ValidationException::withMessages([
                'branch_id' => 'Anda tidak berhak mengubah peserta Daily Briefing.',
            ]);
        }
    }
}
