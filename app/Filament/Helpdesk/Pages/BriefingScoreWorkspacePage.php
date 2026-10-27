<?php

namespace App\Filament\Helpdesk\Pages;

use App\Actions\Briefing\RecomputeBriefingScoresAction;
use App\Models\Branch;
use App\Models\BriefingScore;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * docs/products/daily-briefing/scoring-ui/prd.md §13/Phase 5/8. Follows the Basket Size workspace pattern
 * (resources/views/filament/helpdesk/pages/basket-size-page.blade.php): header, filter, summary,
 * ranking, detail modal, and a batched "Hitung Ulang" action — all reading the same
 * BriefingScoreCalculator snapshot through RecomputeBriefingScoresAction. Replaces
 * BriefingScoreResource as the primary Nilai Briefing experience; the resource/route stays
 * registered for compatibility (docs/products/daily-briefing/remediation/prd.md §15) until its navigation
 * is hidden in a later step.
 */
class BriefingScoreWorkspacePage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-star';

    protected static string|UnitEnum|null $navigationGroup = 'Human Resources';

    protected static ?int $navigationSort = 7;

    protected static ?string $navigationLabel = 'Nilai Briefing';

    protected static ?string $title = 'Nilai Daily Briefing';

    protected static bool $shouldRegisterNavigation = true;

    protected string $view = 'filament.helpdesk.pages.briefing-score-workspace-page';

    #[Url]
    public ?int $year = null;

    #[Url]
    public ?int $month = null;

    #[Url]
    public ?int $branchId = null;

    #[Url]
    public string $search = '';

    #[Url]
    public ?int $detailBranchId = null;

    /** @var array<int, int> */
    public array $recalculationQueue = [];

    public int $recalculationTotal = 0;

    /** @var array{computed: int, skipped: int, failed: int} */
    public array $recalculationResult = ['computed' => 0, 'skipped' => 0, 'failed' => 0];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view briefing scores') ?? false;
    }

    public function mount(): void
    {
        $this->year = $this->year ?: now()->year;
        $this->month = $this->month ?: now()->month;
    }

    public function canRecompute(): bool
    {
        return auth()->user()?->can('edit briefing scores') ?? false;
    }

    public function resetFilters(): void
    {
        $this->branchId = null;
        $this->search = '';
    }

    /**
     * @param  Builder<*>  $query
     * @return Builder<*>
     */
    private function limitToAccessibleBranches(Builder $query, string $column = 'id'): Builder
    {
        $user = auth()->user();

        if ($user === null || $user->canAccessAllBranches()) {
            return $query;
        }

        return $query->whereIn($column, $user->accessibleBranchIds());
    }

    /**
     * §16: participant branches within the viewer's own branch scope, optionally filtered by the
     * branch picker or the name search.
     *
     * @return Collection<int, Branch>
     */
    public function participantBranches(): Collection
    {
        return $this->limitToAccessibleBranches(Branch::query()->whereHas('briefingBranchSettings'), 'id')
            ->when($this->branchId, fn ($q) => $q->where('id', $this->branchId))
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, Branch>
     */
    public function branchOptions(): Collection
    {
        return $this->limitToAccessibleBranches(Branch::query()->whereHas('briefingBranchSettings'), 'id')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * §13.4: branches with a score sorted by final score desc / name asc, and branches without
     * one kept separate — never shown as a zero and never folded into the ranked list.
     *
     * @return array{scored: Collection<int, object>, unscored: Collection<int, object>}
     */
    public function ranking(): array
    {
        $branches = $this->participantBranches();
        $scores = BriefingScore::query()
            ->whereIn('branch_id', $branches->pluck('id'))
            ->where('year', $this->year)
            ->where('month', $this->month)
            ->get()
            ->keyBy('branch_id');

        $rows = $branches->map(fn (Branch $branch): object => (object) [
            'branch' => $branch,
            'score' => $scores->get($branch->id),
        ]);

        $scored = $rows->filter(fn ($row): bool => $row->score !== null)
            ->sort(fn ($a, $b): int => [-$a->score->score, $a->branch->name] <=> [-$b->score->score, $b->branch->name])
            ->values();

        $unscored = $rows->filter(fn ($row): bool => $row->score === null)
            ->sortBy(fn ($row) => $row->branch->name)
            ->values();

        return ['scored' => $scored, 'unscored' => $unscored];
    }

    /**
     * @return array{count: int, average: float, achieve: int, notAchieve: int, unscored: int}
     */
    public function summary(): array
    {
        ['scored' => $scored, 'unscored' => $unscored] = $this->ranking();

        $count = $scored->count();
        $achieve = $scored->filter(fn ($row) => $row->score->isPassing())->count();

        return [
            'count' => $count,
            'average' => $count > 0 ? round($scored->avg(fn ($row) => $row->score->score), 2) : 0.0,
            'achieve' => $achieve,
            'notAchieve' => $count - $achieve,
            'unscored' => $unscored->count(),
        ];
    }

    public function viewDetail(int $branchId): void
    {
        $this->detailBranchId = $branchId;
    }

    public function closeDetail(): void
    {
        $this->detailBranchId = null;
    }

    public function detailScore(): ?BriefingScore
    {
        if (! $this->detailBranchId) {
            return null;
        }

        $user = auth()->user();
        if ($user !== null && ! $user->canAccessAllBranches() && ! $user->accessibleBranchIds()->contains($this->detailBranchId)) {
            return null;
        }

        return BriefingScore::where('branch_id', $this->detailBranchId)
            ->where('year', $this->year)
            ->where('month', $this->month)
            ->with('branch')
            ->first();
    }

    public function recalculableCount(): int
    {
        return $this->participantBranches()->count();
    }

    public function startRecalculation(): void
    {
        abort_unless($this->canRecompute(), 403);

        $ids = $this->participantBranches()->pluck('id')->map(fn ($id): int => (int) $id)->all();

        if ($ids === []) {
            Notification::make()->title('Tidak ada branch peserta pada filter ini')->warning()->send();

            return;
        }

        if (count($ids) > RecomputeBriefingScoresAction::MAX_SCOPE) {
            Notification::make()
                ->title('Terlalu banyak branch untuk dihitung ulang sekaligus')
                ->body('Filter ini mencakup '.count($ids).' branch, maksimal '.RecomputeBriefingScoresAction::MAX_SCOPE.' per proses. Persempit filter branch, atau jalankan command briefing:compute-scores.')
                ->warning()
                ->send();

            return;
        }

        $this->recalculationQueue = $ids;
        $this->recalculationTotal = count($ids);
        $this->recalculationResult = ['computed' => 0, 'skipped' => 0, 'failed' => 0];
    }

    public function processRecalculationBatch(RecomputeBriefingScoresAction $action): void
    {
        abort_unless($this->canRecompute(), 403);

        if ($this->recalculationQueue === []) {
            return;
        }

        $batchIds = array_map('intval', array_splice($this->recalculationQueue, 0, RecomputeBriefingScoresAction::BATCH_SIZE));
        $branches = Branch::whereIn('id', $batchIds)->get();

        $result = $action->process($branches, (int) $this->year, (int) $this->month);

        foreach (['computed', 'skipped', 'failed'] as $key) {
            $this->recalculationResult[$key] += $result[$key];
        }

        if ($this->recalculationQueue === []) {
            $this->finishRecalculation();
        }
    }

    private function finishRecalculation(): void
    {
        $result = $this->recalculationResult;

        $summary = collect([
            "{$result['computed']} branch dihitung",
            $result['skipped'] ? "{$result['skipped']} dilewati (not applicable)" : null,
            $result['failed'] ? "{$result['failed']} gagal" : null,
        ])->filter()->join(', ');

        Notification::make()
            ->title('Hitung ulang Nilai Briefing selesai')
            ->body($summary.'.')
            ->color($result['failed'] > 0 ? 'warning' : 'success')
            ->icon($result['failed'] > 0 ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-check-circle')
            ->send();

        $this->recalculationQueue = [];
        $this->recalculationTotal = 0;
        $this->recalculationResult = ['computed' => 0, 'skipped' => 0, 'failed' => 0];
    }
}
