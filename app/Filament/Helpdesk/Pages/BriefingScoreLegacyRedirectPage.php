<?php

namespace App\Filament\Helpdesk\Pages;

use Filament\Pages\Page;

/**
 * docs/products/daily-briefing/scoring-ui/prd.md §15/Phase 7. BriefingScoreResource (the old `/briefing-
 * scores` table) has been deleted — its table had no columns configured and was fully superseded
 * by BriefingScoreWorkspacePage. This keeps the URL alive as a redirect only, for anyone with the
 * old link bookmarked.
 */
class BriefingScoreLegacyRedirectPage extends Page
{
    protected static ?string $slug = 'briefing-scores';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.helpdesk.pages.briefing-score-legacy-redirect';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view briefing scores') ?? false;
    }

    public function mount(): void
    {
        $this->redirect(BriefingScoreWorkspacePage::getUrl());
    }
}
