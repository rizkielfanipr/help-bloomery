<?php

namespace App\Filament\Casual\Pages;

use App\Enums\BriefingBundleStatus;
use App\Enums\BriefingPeriod;
use App\Enums\BriefingReviewStatus;
use App\Models\BriefingRecord;
use App\Services\BriefingBundleCompletenessService;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

class BriefingHistoryPage extends Page
{
    use WithPagination;

    protected static bool $shouldRegisterNavigation = false;

    protected static string $layout = 'filament.casual.layouts.bare';

    protected string $view = 'filament.casual.pages.briefing-history-page';

    #[Url]
    public string $period = 'all';

    public ?int $expandedRecordId = null;

    public function getTitle(): string|Htmlable
    {
        return 'Riwayat Briefing';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function setPeriod(string $period): void
    {
        $this->period = $period;
        $this->resetPage();
        $this->expandedRecordId = null;
    }

    public function toggleRecord(int $id): void
    {
        $this->expandedRecordId = $this->expandedRecordId === $id ? null : $id;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('access employee app briefing') ?? false;
    }

    /**
     * docs/daily-briefing-bundle-approval-prd.md §12: a participant sees every bundle of their own
     * branch — including ones a teammate started — and only their own legacy rows that have not
     * been grouped into bundles yet.
     */
    public function records(): LengthAwarePaginator
    {
        $user = auth()->user();

        $query = BriefingRecord::query()
            ->where(function (Builder $scope) use ($user): void {
                $scope->whereNull('status')->where('user_id', $user->id);

                if ($user->branch_id && $user->can('submit daily briefing')) {
                    $scope->orWhere(fn (Builder $bundles): Builder => $bundles->whereNotNull('status')->where('branch_id', $user->branch_id));
                }
            })
            ->with(['items', 'requirements'])
            ->orderBy('record_date', 'desc');

        if ($this->period !== 'all') {
            $query->where('period', $this->period);
        }

        return $query->paginate(15);
    }

    /**
     * Card summary: bundles use their own status and requirement snapshot; legacy rows keep the
     * old per-item roll-up until they are grouped.
     *
     * @return array{completed: int, total: int, label: string, class: string}
     */
    public function summary(BriefingRecord $record): array
    {
        if (! $record->isLegacyUngrouped()) {
            $completeness = app(BriefingBundleCompletenessService::class)->evaluate($record);

            return [
                'completed' => $completeness->completed,
                'total' => $completeness->required,
                'label' => $record->status->getLabel(),
                'class' => match ($record->status) {
                    BriefingBundleStatus::Approved => 'bg-green-100 text-green-700',
                    BriefingBundleStatus::Rejected => 'bg-red-100 text-red-700',
                    BriefingBundleStatus::SupervisorReview => 'bg-amber-100 text-amber-700',
                    BriefingBundleStatus::Reopened => 'bg-blue-100 text-blue-700',
                    BriefingBundleStatus::Draft => 'bg-gray-100 text-gray-600',
                },
            ];
        }

        $items = $record->items;

        [$label, $class] = match (true) {
            $items->contains(fn ($item): bool => $item->review_status === BriefingReviewStatus::Rejected) => ['Rejected', 'bg-red-100 text-red-700'],
            $items->isNotEmpty() && $items->every(fn ($item): bool => $item->review_status === BriefingReviewStatus::Approved) => ['All Approved', 'bg-green-100 text-green-700'],
            $items->contains(fn ($item): bool => (bool) $item->review_status?->isAwaitingSupervisorReview()) => ['Supervisor Review', 'bg-amber-100 text-amber-700'],
            $items->isNotEmpty() => ['Tersubmit', 'bg-blue-100 text-blue-700'],
            default => ['Kosong', 'bg-gray-100 text-gray-500'],
        };

        return [
            'completed' => $items->where('is_completed', true)->count(),
            'total' => $items->count(),
            'label' => $label,
            'class' => $class,
        ];
    }

    public function reviewBadgeClass(?BriefingReviewStatus $status): string
    {
        return match ($status) {
            BriefingReviewStatus::Approved => 'bg-green-100 text-green-700',
            BriefingReviewStatus::Rejected => 'bg-red-100 text-red-700',
            BriefingReviewStatus::LegacyPending, BriefingReviewStatus::SupervisorReview => 'bg-amber-100 text-amber-700',
            default => 'bg-blue-100 text-blue-700',
        };
    }

    public function reviewBadgeLabel(?BriefingReviewStatus $status): string
    {
        return $status?->getLabel() ?? 'Not Submitted';
    }

    public function periodBadgeClass(string $period): string
    {
        return match ($period) {
            BriefingPeriod::Daily->value => 'bg-green-100 text-green-700',
            BriefingPeriod::Weekly->value => 'bg-blue-100 text-blue-700',
            BriefingPeriod::Monthly->value => 'bg-amber-100 text-amber-700',
            default => 'bg-gray-100 text-gray-600',
        };
    }

    public function periodLabel(string $period): string
    {
        return BriefingPeriod::from($period)->getLabel();
    }
}
