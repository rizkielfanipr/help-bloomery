<?php

namespace App\Filament\Casual\Pages;

use App\Models\BriefingScore;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;

class BriefingScorePage extends Page
{
    /**
     * docs/products/daily-briefing/scoring-ui/prd.md §16 (Phase 11): the page and the PDF
     * (BriefingScorePdfController) must require the same permission — this page previously had
     * no canAccess() check at all.
     */
    public static function canAccess(): bool
    {
        return auth()->user()?->can('view briefing scores') ?? false;
    }

    protected static bool $shouldRegisterNavigation = false;

    protected static string $layout = 'filament.casual.layouts.bare';

    protected string $view = 'filament.casual.pages.briefing-score-page';

    public function getTitle(): string|Htmlable
    {
        return 'Nilai Briefing';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    #[Computed]
    public function scores(): Collection
    {
        $branchId = auth()->user()?->branch_id;

        if (! $branchId) {
            return collect();
        }

        return BriefingScore::where('branch_id', $branchId)
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->get();
    }

    #[Computed]
    public function latestScore(): ?BriefingScore
    {
        return $this->scores->first();
    }
}
