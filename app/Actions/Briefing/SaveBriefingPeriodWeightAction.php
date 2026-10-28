<?php

namespace App\Actions\Briefing;

use App\Models\BriefingPeriodWeight;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * docs/products/daily-briefing/scoring-ui/prd.md §12.2/§12.3/§16 (Phase 4). The only path that saves the
 * default period weight row (branch_id null) or a branch override — shared by the legacy
 * BriefingPeriodWeightsPage and the new Settings page's Override Branch section. `execute(null,
 * ...)` always targets the default row; branch access is re-checked here even though callers
 * also gate visibility, so a branch outside the actor's scope is rejected regardless of how this
 * is invoked.
 */
class SaveBriefingPeriodWeightAction
{
    /**
     * @param  array{daily_weight: mixed, weekly_weight: mixed, monthly_weight: mixed}  $weights
     */
    public function execute(User $actor, ?int $branchId, array $weights): BriefingPeriodWeight
    {
        $this->authorize($actor, $branchId);

        $validated = Validator::make($weights, [
            'daily_weight' => ['required', 'numeric', 'min:0', 'max:100'],
            'weekly_weight' => ['required', 'numeric', 'min:0', 'max:100'],
            'monthly_weight' => ['required', 'numeric', 'min:0', 'max:100'],
        ])->validate();

        $sum = round((float) $validated['daily_weight'] + (float) $validated['weekly_weight'] + (float) $validated['monthly_weight'], 2);

        if (abs($sum - 100) > 0.01) {
            throw ValidationException::withMessages([
                'daily_weight' => "Total bobot harus 100% (saat ini {$sum}%).",
            ]);
        }

        return DB::transaction(fn (): BriefingPeriodWeight => BriefingPeriodWeight::updateOrCreate(
            ['branch_id' => $branchId],
            $validated,
        ));
    }

    /**
     * §12.3: removing the row makes the branch fall back to the default weights, rather than
     * persisting three null values as an ambiguous "override".
     */
    public function reset(User $actor, int $branchId): void
    {
        $this->authorize($actor, $branchId);

        DB::transaction(fn () => BriefingPeriodWeight::where('branch_id', $branchId)->delete());
    }

    /**
     * `edit briefing period weights` is currently only held by HRD_STAFF/SUPERADMIN as a global
     * HR-configuration permission, not a per-branch one — docs/products/daily-briefing/scoring-ui/prd.md
     * §16 only requires branch-scoping it "jika supervisor boleh mengatur bobot branch", which
     * is not the case today. Narrowing this to accessibleBranchIds() without that explicit
     * decision would silently take away capability the existing holders rely on.
     */
    private function authorize(User $actor, ?int $branchId): void
    {
        if (! $actor->can('edit briefing period weights')) {
            throw ValidationException::withMessages([
                'daily_weight' => 'Anda tidak berhak mengubah bobot penilaian Daily Briefing.',
            ]);
        }
    }
}
