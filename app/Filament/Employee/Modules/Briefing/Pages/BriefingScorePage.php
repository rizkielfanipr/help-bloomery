<?php

namespace App\Filament\Employee\Modules\Briefing\Pages;

use App\Models\BriefingScore;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;

class BriefingScorePage extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static string $layout = 'filament.employee.layouts.bare';

    protected string $view = 'filament.employee.briefing.pages.briefing-score-page';

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
