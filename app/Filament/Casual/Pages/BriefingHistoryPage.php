<?php

namespace App\Filament\Casual\Pages;

use App\Enums\BriefingPeriod;
use App\Enums\BriefingReviewStatus;
use App\Models\BriefingRecord;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
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

    public function records(): LengthAwarePaginator
    {
        $query = BriefingRecord::where('user_id', auth()->id())
            ->with(['items'])
            ->orderBy('record_date', 'desc');

        if ($this->period !== 'all') {
            $query->where('period', $this->period);
        }

        return $query->paginate(15);
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
