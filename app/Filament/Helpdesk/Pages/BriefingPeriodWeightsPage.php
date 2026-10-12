<?php

namespace App\Filament\Helpdesk\Pages;

use App\Actions\Briefing\SaveBriefingPeriodWeightAction;
use App\Models\Branch;
use App\Models\BriefingPeriodWeight;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Validation\ValidationException;

/**
 * docs/daily-briefing-scoring-ui-prd.md §15/Phase 7. Superseded by the Override Branch section
 * on BriefingSettingsPage, which saves through the same SaveBriefingPeriodWeightAction. The
 * route and class stay registered for compatibility — only the sidebar entry is hidden.
 */
class BriefingPeriodWeightsPage extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null
            && $user->can('edit briefing period weights');
    }

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-scale';

    protected static string|\UnitEnum|null $navigationGroup = 'Human Resources';

    protected static ?int $navigationSort = 9;

    protected static ?string $navigationLabel = 'Bobot Penilaian Briefing';

    protected static ?string $title = 'Bobot Penilaian Briefing';

    protected string $view = 'filament.helpdesk.pages.briefing-period-weights-page';

    /** @var array<int, array{branch_id: ?int, branch_label: string, daily_weight: ?float, weekly_weight: ?float, monthly_weight: ?float}> */
    public array $rows = [];

    public function mount(): void
    {
        $this->loadRows();
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $action = app(SaveBriefingPeriodWeightAction::class);

        foreach ($this->rows as $index => $row) {
            if (! $this->rowNeedsSaving($row)) {
                // Cabang tidak diisi sama sekali -> pakai bobot Default, hapus override lama kalau ada.
                if ($row['branch_id'] !== null) {
                    $action->reset(auth()->user(), $row['branch_id']);
                }

                continue;
            }

            try {
                $action->execute(auth()->user(), $row['branch_id'], [
                    'daily_weight' => $row['daily_weight'],
                    'weekly_weight' => $row['weekly_weight'],
                    'monthly_weight' => $row['monthly_weight'],
                ]);
            } catch (ValidationException $exception) {
                $this->addError("rows.{$index}.daily_weight", "{$row['branch_label']}: ".$exception->validator->errors()->first());

                return;
            }
        }

        $this->loadRows();

        Notification::make()->title('Bobot penilaian berhasil disimpan')->success()->send();
    }

    /** @param array{branch_id: ?int, branch_label: string, daily_weight: ?float, weekly_weight: ?float, monthly_weight: ?float} $row */
    private function rowNeedsSaving(array $row): bool
    {
        // The Default row always applies; a branch row only needs saving/validating
        // once the admin has actually started filling in an override for it.
        return $row['branch_id'] === null
            || $row['daily_weight'] !== null
            || $row['weekly_weight'] !== null
            || $row['monthly_weight'] !== null;
    }

    private function loadRows(): void
    {
        $default = BriefingPeriodWeight::whereNull('branch_id')->first();

        $rows = [[
            'branch_id' => null,
            'branch_label' => 'Default (Semua Cabang Lain)',
            'daily_weight' => (float) ($default?->daily_weight ?? 40),
            'weekly_weight' => (float) ($default?->weekly_weight ?? 30),
            'monthly_weight' => (float) ($default?->monthly_weight ?? 30),
        ]];

        $overrides = BriefingPeriodWeight::whereNotNull('branch_id')->get()->keyBy('branch_id');

        foreach (Branch::where('is_active', true)->orderBy('name')->get() as $branch) {
            $override = $overrides->get($branch->id);

            $rows[] = [
                'branch_id' => $branch->id,
                'branch_label' => $branch->name,
                'daily_weight' => $override?->daily_weight,
                'weekly_weight' => $override?->weekly_weight,
                'monthly_weight' => $override?->monthly_weight,
            ];
        }

        $this->rows = $rows;
    }
}
