<?php

namespace App\Actions\Briefing;

use App\Models\BriefingSettings;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * docs/daily-briefing-scoring-ui-prd.md §12.1/§16 (Phase 4). The only path that saves the global
 * Daily Briefing scoring/auto-reject settings — shared by BriefingSettingsPage and anything else
 * that needs to change them, so authorization and validation never drift between callers.
 */
class SaveBriefingGlobalSettingsAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, array $data): BriefingSettings
    {
        if (! $actor->can('edit briefing settings')) {
            throw ValidationException::withMessages([
                'auto_reject_after_days' => 'Anda tidak berhak mengubah pengaturan Daily Briefing.',
            ]);
        }

        $validated = Validator::make($data, [
            'scoring_started_at' => ['required', 'date'],
            'passing_score' => ['required', 'numeric', 'min:0', 'max:100'],
            'auto_reject_after_days' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'auto_reject_reason' => ['required', 'string', 'max:255'],
        ])->validate();

        return DB::transaction(function () use ($validated): BriefingSettings {
            $settings = BriefingSettings::instance();
            $settings->update($validated);

            return $settings;
        });
    }
}
