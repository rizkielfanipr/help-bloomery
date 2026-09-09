<?php

namespace App\Filament\Casual\Pages;

use App\Models\Branch;
use App\Models\StoreSopAssignment;
use App\Services\StoreSopPublisher;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;

class StoreSopsPage extends Page
{
    protected string $view = 'filament.casual.pages.store-sops-page';

    protected static string $layout = 'filament.casual.layouts.bare';

    protected static bool $shouldRegisterNavigation = false;

    public string $filter = 'all';

    public string $search = '';

    public ?int $selectedBranchId = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('access employee app store sop') ?? false;
    }

    public function mount(StoreSopPublisher $publisher): void
    {
        $user = auth()->user();
        if ($user) {
            $publisher->syncAssignmentsForUser($user);
        }
    }

    public function getBranches(): Collection
    {
        $user = auth()->user();
        if (! $user) {
            return new Collection;
        }

        return Branch::query()
            ->whereIn('id', $user->accessibleBranchIds())
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function assignments(): Collection
    {
        $user = auth()->user();
        if (! $user) {
            return new Collection;
        }

        $query = StoreSopAssignment::query()
            ->with(['sop.category', 'branch'])
            ->where('user_id', $user->id)
            ->whereIn('branch_id', $user->accessibleBranchIds())
            ->whereHas('sop', fn ($q) => $q->where('status', 'published'));

        if ($this->selectedBranchId) {
            $query->where('branch_id', $this->selectedBranchId);
        }

        if ($this->filter === 'ongoing') {
            $query->whereHas('sop', fn ($q) => $q->whereNull('expires_at')->orWhereDate('expires_at', '>=', today()));
        } elseif ($this->filter === 'expired') {
            $query->whereHas('sop', fn ($q) => $q->whereDate('expires_at', '<', today()));
        }

        if (filled($this->search)) {
            $search = '%'.trim($this->search).'%';
            $query->whereHas('sop', function ($q) use ($search): void {
                $q->where('title', 'like', $search)
                    ->orWhere('code', 'like', $search)
                    ->orWhereHas('category', fn ($categoryQuery) => $categoryQuery->where('name', 'like', $search));
            });
        }

        return $query->latest('assigned_at')->get();
    }

    /**
     * @return array{all: int, ongoing: int, expired: int}
     */
    public function counts(): array
    {
        $user = auth()->user();
        if (! $user) {
            return ['all' => 0, 'ongoing' => 0, 'expired' => 0];
        }

        $base = StoreSopAssignment::query()
            ->where('user_id', $user->id)
            ->whereIn('branch_id', $user->accessibleBranchIds())
            ->whereHas('sop', fn ($q) => $q->where('status', 'published'));

        if ($this->selectedBranchId) {
            $base->where('branch_id', $this->selectedBranchId);
        }

        return [
            'all' => (clone $base)->count(),
            'ongoing' => (clone $base)->whereHas('sop', fn ($q) => $q->whereNull('expires_at')->orWhereDate('expires_at', '>=', today()))->count(),
            'expired' => (clone $base)->whereHas('sop', fn ($q) => $q->whereDate('expires_at', '<', today()))->count(),
        ];
    }

    public function setFilter(string $filter): void
    {
        $this->filter = $filter;
    }

    public function openSop(int $assignmentId): void
    {
        $assignment = StoreSopAssignment::where('user_id', auth()->id())->findOrFail($assignmentId);
        abort_unless(auth()->user()->canAccessBranch($assignment->branch_id), 403);
        $assignment->update(['opened_at' => $assignment->opened_at ?? now()]);
        $this->redirect($assignment->sop->downloadUrl(), navigate: false);
    }

    public function acknowledge(int $assignmentId): void
    {
        $assignment = StoreSopAssignment::where('user_id', auth()->id())->findOrFail($assignmentId);
        abort_unless(auth()->user()->canAccessBranch($assignment->branch_id), 403);
        $assignment->update(['opened_at' => $assignment->opened_at ?? now(), 'acknowledged_at' => now()]);
        Notification::make()->title('Penerimaan SOP sudah dikonfirmasi')->success()->send();
    }
}
