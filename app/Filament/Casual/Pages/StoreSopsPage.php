<?php

namespace App\Filament\Casual\Pages;

use App\Models\StoreSopAssignment;
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

    public function getBranches(): Collection
    {
        $user = auth()->user();
        if (! $user) {
            return new Collection;
        }

        return $user->accessibleBranches()->where('is_active', true)->orderBy('name')->get();
    }

    public function assignments(): Collection
    {
        $query = StoreSopAssignment::query()
            ->with(['sop', 'branch'])
            ->where('user_id', auth()->id())
            ->whereHas('sop', fn ($q) => $q->where('status', 'published'));

        if ($this->selectedBranchId) {
            $query->where('branch_id', $this->selectedBranchId);
        }

        if ($this->filter === 'unread') {
            $query->whereNull('opened_at');
        } elseif ($this->filter === 'unacknowledged') {
            $query->whereNull('acknowledged_at');
        } elseif ($this->filter === 'acknowledged') {
            $query->whereNotNull('acknowledged_at');
        }

        if (filled($this->search)) {
            $search = '%'.trim($this->search).'%';
            $query->whereHas('sop', function ($q) use ($search): void {
                $q->where('title', 'like', $search)
                    ->orWhere('code', 'like', $search)
                    ->orWhere('category', 'like', $search);
            });
        }

        return $query->latest('assigned_at')->get();
    }

    /**
     * @return array{all: int, unread: int, unacknowledged: int, acknowledged: int}
     */
    public function counts(): array
    {
        $base = StoreSopAssignment::query()
            ->where('user_id', auth()->id())
            ->whereHas('sop', fn ($q) => $q->where('status', 'published'));

        if ($this->selectedBranchId) {
            $base->where('branch_id', $this->selectedBranchId);
        }

        return [
            'all' => (clone $base)->count(),
            'unread' => (clone $base)->whereNull('opened_at')->count(),
            'unacknowledged' => (clone $base)->whereNull('acknowledged_at')->count(),
            'acknowledged' => (clone $base)->whereNotNull('acknowledged_at')->count(),
        ];
    }

    public function setFilter(string $filter): void
    {
        $this->filter = $filter;
    }

    public function openSop(int $assignmentId): void
    {
        $assignment = StoreSopAssignment::where('user_id', auth()->id())->findOrFail($assignmentId);
        $assignment->update(['opened_at' => $assignment->opened_at ?? now()]);
        $this->redirect($assignment->sop->downloadUrl(), navigate: false);
    }

    public function acknowledge(int $assignmentId): void
    {
        $assignment = StoreSopAssignment::where('user_id', auth()->id())->findOrFail($assignmentId);
        $assignment->update(['opened_at' => $assignment->opened_at ?? now(), 'acknowledged_at' => now()]);
        Notification::make()->title('SOP sudah dikonfirmasi')->success()->send();
    }
}
