<?php

namespace App\Filament\Helpdesk\Pages;

use App\Actions\Briefing\SaveBriefingBranchParticipationAction;
use App\Actions\Briefing\SaveBriefingGlobalSettingsAction;
use App\Actions\Briefing\SaveBriefingPeriodWeightAction;
use App\Models\Branch;
use App\Models\BriefingBranchSetting;
use App\Models\BriefingItem;
use App\Models\BriefingPeriodWeight;
use App\Models\BriefingSettings;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;

/**
 * docs/products/daily-briefing/scoring-ui/prd.md §14/Phase 6. Follows the Sales Report Settings visual
 * pattern (resources/views/filament/helpdesk/pages/sales-report-settings-page.blade.php): a
 * hand-built Blade form with `wire:model`, not Filament Schema components, so the layout matches
 * that reference exactly instead of Filament's default form rendering.
 */
class BriefingSettingsPage extends Page
{
    /**
     * This page now manages both the global settings and the default/override period weights in
     * one form (docs/products/daily-briefing/scoring-ui/prd.md §14), so it requires both permissions —
     * every role that holds one already holds the other (RolesAndPermissionsSeeder).
     */
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->can('edit briefing settings') && $user->can('edit briefing period weights');
    }

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calculator';

    protected static string|\UnitEnum|null $navigationGroup = 'Human Resources';

    protected static ?int $navigationSort = 8;

    protected static ?string $navigationLabel = 'Pengaturan Briefing';

    protected static ?string $title = 'Pengaturan Nilai Daily Briefing';

    protected string $view = 'filament.helpdesk.pages.briefing-settings-page';

    /** @var array<string, mixed> */
    public array $data = [];

    public ?int $selectedOverrideBranchId = null;

    public bool $useCustomWeight = false;

    /** @var array{branch_id: ?int, started_at: ?string} */
    public array $participationData = [];

    public function mount(): void
    {
        $settings = BriefingSettings::instance();
        $default = BriefingPeriodWeight::whereNull('branch_id')->first();

        $this->data = [
            'scoring_started_at' => $settings->scoring_started_at?->toDateString(),
            'passing_score' => (float) $settings->passing_score,
            'auto_reject_after_days' => $settings->auto_reject_after_days,
            'auto_reject_reason' => $settings->auto_reject_reason,
            'default_daily_weight' => (float) ($default?->daily_weight ?? 40),
            'default_weekly_weight' => (float) ($default?->weekly_weight ?? 30),
            'default_monthly_weight' => (float) ($default?->monthly_weight ?? 30),
            'override_daily_weight' => null,
            'override_weekly_weight' => null,
            'override_monthly_weight' => null,
        ];

        $this->participationData = [
            'branch_id' => null,
            'started_at' => today()->toDateString(),
        ];
    }

    /** @return Collection<int, Branch> */
    public function availableBranches(): Collection
    {
        return Branch::query()
            ->whereDoesntHave('briefingBranchSettings', fn ($query) => $query
                ->where('is_enabled', true)
                ->whereDate('started_at', '<=', today())
                ->where(fn ($query) => $query
                    ->whereNull('ended_at')
                    ->orWhereDate('ended_at', '>=', today())))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /** @return Collection<int, BriefingBranchSetting> */
    public function participationPeriods(): Collection
    {
        return BriefingBranchSetting::query()
            ->with('branch:id,name')
            ->latest('started_at')
            ->latest('id')
            ->get();
    }

    public function addParticipation(): void
    {
        abort_unless(static::canAccess(), 403);

        try {
            app(SaveBriefingBranchParticipationAction::class)->execute(
                auth()->user(),
                (int) ($this->participationData['branch_id'] ?? 0),
                $this->participationData['started_at'] ?: null,
            );
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                $this->addError("participationData.{$field}", $messages[0]);
            }

            return;
        }

        $this->resetValidation();
        $this->participationData = [
            'branch_id' => null,
            'started_at' => today()->toDateString(),
        ];

        Notification::make()->title('Branch peserta berhasil ditambahkan')->success()->send();
    }

    public function updateParticipationStart(int $settingId, string $startedAt): void
    {
        abort_unless(static::canAccess(), 403);

        try {
            app(SaveBriefingBranchParticipationAction::class)->updateStartDate(auth()->user(), $settingId, $startedAt);
        } catch (ValidationException $exception) {
            Notification::make()->title('Tanggal mulai tidak dapat diubah')->body(collect($exception->errors())->flatten()->first())->danger()->send();

            return;
        }

        Notification::make()->title('Tanggal mulai peserta diperbarui')->body('Nilai bulan yang sudah tersimpan tidak berubah sampai dihitung ulang.')->success()->send();
    }

    public function deactivateParticipation(int $settingId): void
    {
        abort_unless(static::canAccess(), 403);

        app(SaveBriefingBranchParticipationAction::class)->deactivate(auth()->user(), $settingId);

        Notification::make()->title('Partisipasi branch dinonaktifkan')->success()->send();
    }

    /**
     * §12.3/§14.4: branches that are ever participants (not necessarily active today), so a
     * paused branch's override can still be reviewed. The full list is small enough to load
     * eagerly; only the selected branch's weight row is loaded on demand.
     *
     * @return Collection<int, Branch>
     */
    public function participantBranches(): Collection
    {
        return Branch::query()
            ->whereHas('briefingBranchSettings')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function updatedSelectedOverrideBranchId(): void
    {
        $this->loadOverrideForBranch();
    }

    private function loadOverrideForBranch(): void
    {
        if (! $this->selectedOverrideBranchId) {
            $this->useCustomWeight = false;
            $this->data['override_daily_weight'] = null;
            $this->data['override_weekly_weight'] = null;
            $this->data['override_monthly_weight'] = null;

            return;
        }

        $override = BriefingPeriodWeight::where('branch_id', $this->selectedOverrideBranchId)->first();
        $this->useCustomWeight = $override !== null;
        $this->data['override_daily_weight'] = (float) ($override?->daily_weight ?? $this->data['default_daily_weight']);
        $this->data['override_weekly_weight'] = (float) ($override?->weekly_weight ?? $this->data['default_weekly_weight']);
        $this->data['override_monthly_weight'] = (float) ($override?->monthly_weight ?? $this->data['default_monthly_weight']);
    }

    public function defaultWeightTotal(): float
    {
        return round(
            (float) ($this->data['default_daily_weight'] ?? 0)
            + (float) ($this->data['default_weekly_weight'] ?? 0)
            + (float) ($this->data['default_monthly_weight'] ?? 0),
            2,
        );
    }

    public function overrideWeightTotal(): float
    {
        return round(
            (float) ($this->data['override_daily_weight'] ?? 0)
            + (float) ($this->data['override_weekly_weight'] ?? 0)
            + (float) ($this->data['override_monthly_weight'] ?? 0),
            2,
        );
    }

    /**
     * §14.5 preview: how many real submissions are currently eligible for the next auto-reject
     * SLA run. Mirrors BriefingAutoRejectCommand's own query so the number is never misleading.
     */
    #[Computed]
    public function eligibleAutoRejectCount(): int
    {
        $days = (int) ($this->data['auto_reject_after_days'] ?? BriefingSettings::instance()->auto_reject_after_days);

        return BriefingItem::query()
            ->whereIn('review_status', ['pending', 'supervisor_review'])
            ->where('is_completed', true)
            ->whereNotNull('completed_at')
            ->where('completed_at', '<=', now()->subDays($days))
            ->count();
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $actor = auth()->user();

        try {
            app(SaveBriefingGlobalSettingsAction::class)->execute($actor, [
                'scoring_started_at' => $this->data['scoring_started_at'] ?? null,
                'passing_score' => $this->data['passing_score'] ?? null,
                'auto_reject_after_days' => $this->data['auto_reject_after_days'] ?? null,
                'auto_reject_reason' => $this->data['auto_reject_reason'] ?? null,
            ]);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                $this->addError("data.{$field}", $messages[0]);
            }

            return;
        }

        try {
            app(SaveBriefingPeriodWeightAction::class)->execute($actor, null, [
                'daily_weight' => $this->data['default_daily_weight'] ?? null,
                'weekly_weight' => $this->data['default_weekly_weight'] ?? null,
                'monthly_weight' => $this->data['default_monthly_weight'] ?? null,
            ]);
        } catch (ValidationException $exception) {
            $this->addError('data.default_daily_weight', $exception->validator->errors()->first());

            return;
        }

        if ($this->selectedOverrideBranchId) {
            try {
                if ($this->useCustomWeight) {
                    app(SaveBriefingPeriodWeightAction::class)->execute($actor, $this->selectedOverrideBranchId, [
                        'daily_weight' => $this->data['override_daily_weight'] ?? null,
                        'weekly_weight' => $this->data['override_weekly_weight'] ?? null,
                        'monthly_weight' => $this->data['override_monthly_weight'] ?? null,
                    ]);
                } else {
                    app(SaveBriefingPeriodWeightAction::class)->reset($actor, $this->selectedOverrideBranchId);
                }
            } catch (ValidationException $exception) {
                $this->addError('data.override_daily_weight', $exception->validator->errors()->first());

                return;
            }
        }

        unset($this->eligibleAutoRejectCount);
        $this->loadOverrideForBranch();

        Notification::make()->title('Pengaturan Daily Briefing berhasil disimpan')->success()->send();
    }
}
