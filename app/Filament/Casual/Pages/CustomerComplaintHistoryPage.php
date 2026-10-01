<?php

namespace App\Filament\Casual\Pages;

use App\Models\CustomerComplaint;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;

/**
 * docs/customer-complaints-prd.md §9, reached from the Form Komplain bottom nav (user request:
 * a dedicated Riwayat tab rather than the "last 5 on the same page" list the PRD originally
 * described — the user's explicit instruction here supersedes that line of the PRD). Mirrors
 * ErpRequestHistoryPage's shape: full history of the signed-in user's own complaints, newest
 * first, with an expand/collapse accordion instead of a modal.
 */
class CustomerComplaintHistoryPage extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static string $layout = 'filament.casual.layouts.bare';

    protected string $view = 'filament.casual.pages.customer-complaint-history-page';

    public ?int $expandedId = null;

    public function getTitle(): string|Htmlable
    {
        return 'Riwayat Komplain';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function toggleItem(int $id): void
    {
        $this->expandedId = $this->expandedId === $id ? null : $id;
    }

    /**
     * The submitter can always see their own complaint regardless of branch access
     * (CustomerComplaintPolicy::view), so no branch filter is applied here either.
     *
     * @return Collection<int, CustomerComplaint>
     */
    public function complaints(): Collection
    {
        return CustomerComplaint::query()
            ->where('submitted_by', auth()->id())
            ->with('branch:id,name')
            ->orderBy('occurred_at', 'desc')
            ->get();
    }
}
