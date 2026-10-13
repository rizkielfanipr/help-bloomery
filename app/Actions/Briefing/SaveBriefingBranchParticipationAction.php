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
    public function execute(User $actor, int $branchId, ?string $startedAt = null): BriefingBranchSetting
    {
        $this->authorize($actor);

        $validated = Validator::make(
            ['branch_id' => $branchId, 'started_at' => $startedAt ?? today()->toDateString()],
            [
                'branch_id' => ['required', 'integer', 'exists:branches,id'],
                'started_at' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            ],
            ['started_at.before_or_equal' => 'Tanggal mulai tidak boleh melewati hari ini.'],
        )->validate();

        return DB::transaction(function () use ($actor, $validated): BriefingBranchSetting {
            Branch::query()->lockForUpdate()->findOrFail($validated['branch_id']);

            if ($this->overlapsAnotherPeriod((int) $validated['branch_id'], $validated['started_at'], null)) {
                throw ValidationException::withMessages([
                    'branch_id' => 'Periode partisipasi branch bertabrakan dengan periode yang sudah ada.',
                ]);
            }

            return BriefingBranchSetting::create([
                'branch_id' => $validated['branch_id'],
                'is_enabled' => true,
                'started_at' => $validated['started_at'],
                'ended_at' => null,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
        });
    }

    public function updateStartDate(User $actor, int $settingId, string $startedAt): BriefingBranchSetting
    {
        $this->authorize($actor);

        $validated = Validator::make(
            ['started_at' => $startedAt],
            ['started_at' => ['required', 'date_format:Y-m-d', 'before_or_equal:today']],
            ['started_at.before_or_equal' => 'Tanggal mulai tidak boleh melewati hari ini.'],
        )->validate();

        return DB::transaction(function () use ($actor, $settingId, $validated): BriefingBranchSetting {
            $setting = BriefingBranchSetting::query()->lockForUpdate()->findOrFail($settingId);

            if (! $setting->is_enabled) {
                throw ValidationException::withMessages(['started_at' => 'Periode partisipasi ini sudah tidak aktif.']);
            }

            if ($setting->ended_at !== null && $setting->ended_at->lt($validated['started_at'])) {
                throw ValidationException::withMessages(['started_at' => 'Tanggal mulai tidak boleh melewati tanggal berakhir periode.']);
            }

            if ($this->overlapsAnotherPeriod($setting->branch_id, $validated['started_at'], $setting->ended_at?->toDateString(), $setting->id)) {
                throw ValidationException::withMessages(['started_at' => 'Periode partisipasi branch bertabrakan dengan periode yang sudah ada.']);
            }

            $setting->started_at = $validated['started_at'];
            $setting->updated_by = $actor->id;
            $setting->save();

            return $setting;
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

    private function overlapsAnotherPeriod(int $branchId, string $startedAt, ?string $endedAt, ?int $ignoreId = null): bool
    {
        return BriefingBranchSetting::query()
            ->where('branch_id', $branchId)
            ->where('is_enabled', true)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->when($endedAt, fn ($query) => $query->whereDate('started_at', '<=', $endedAt))
            ->where(fn ($query) => $query
                ->whereNull('ended_at')
                ->orWhereDate('ended_at', '>=', $startedAt))
            ->exists();
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
