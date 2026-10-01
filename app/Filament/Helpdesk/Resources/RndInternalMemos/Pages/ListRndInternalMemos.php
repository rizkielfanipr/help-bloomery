<?php

namespace App\Filament\Helpdesk\Resources\RndInternalMemos\Pages;

use App\Actions\Rnd\InternalMemo\CreateInternalMemoAction;
use App\Actions\Rnd\InternalMemo\DeleteInternalMemoAction;
use App\Actions\Rnd\InternalMemo\MemoBranchMappingResolution;
use App\Actions\Rnd\InternalMemo\ResolveMemoBranchMappingsAction;
use App\Filament\Helpdesk\Resources\RndInternalMemos\RndInternalMemoResource;
use App\Models\Branch;
use App\Models\RndInternalMemo;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ListRndInternalMemos extends ListRecords
{
    protected static string $resource = RndInternalMemoResource::class;

    protected string $view = 'filament.helpdesk.rnd-internal-memos.index';

    public string $search = '';

    public string $periodFilter = '';

    public bool $createModalOpen = false;

    public string $memoNumber = '';

    public bool $memoNumberGenerated = false;

    public string $memoTitle = '';

    public string $periodMonth = '';

    public string $notes = '';

    /** @var list<int> */
    public array $branchIds = [];

    /**
     * docs/rnd-internal-memo-simplification-prd.md §12.1: the simplified index has no status
     * filter or workflow summary cards — every non-deleted Memo is listed, searched by name/
     * number, and filtered by period only.
     */
    public function memos(): Collection
    {
        return RndInternalMemo::query()
            ->when($this->search !== '', fn (Builder $query) => $query->where(function (Builder $query): void {
                $query->where('title', 'like', '%'.$this->search.'%')
                    ->orWhere('memo_number', 'like', '%'.$this->search.'%');
            }))
            ->when($this->periodFilter !== '', fn (Builder $query) => $query->whereDate('period_month', $this->periodFilter.'-01'))
            ->withCount('menus')
            ->latest('period_month')
            ->latest('revision')
            ->get();
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function openCreateModal(): void
    {
        abort_unless(RndInternalMemoResource::canCreate(), 403);
        $this->resetValidation();
        $this->reset(['memoNumber', 'memoNumberGenerated', 'memoTitle', 'periodMonth', 'notes', 'branchIds']);
        $this->createModalOpen = true;
    }

    /**
     * docs/rnd-internal-memo-multi-branch-prd.md §6, §7.2: options are limited to the user's
     * accessible branches (or every branch for access_all_branches), each annotated with its
     * mapping resolution so the Blade can disable unselectable options with a specific reason.
     *
     * @return array<int, array{branch: Branch, resolution: MemoBranchMappingResolution}>
     */
    public function branchOptions(?ResolveMemoBranchMappingsAction $resolveBranchMappings = null): array
    {
        $resolveBranchMappings ??= app(ResolveMemoBranchMappingsAction::class);
        $user = auth()->user();
        $branches = $user->canAccessAllBranches()
            ? Branch::query()->where('is_active', true)->orderBy('name')->get()
            : Branch::query()->where('is_active', true)->whereIn('id', $user->accessibleBranchIds())->orderBy('name')->get();

        $resolutions = $resolveBranchMappings->resolveMany($branches);

        return $branches->map(fn (Branch $branch): array => [
            'branch' => $branch,
            'resolution' => $resolutions[$branch->id],
        ])->all();
    }

    public function closeCreateModal(): void
    {
        $this->resetValidation();
        $this->createModalOpen = false;
    }

    /**
     * Fills Nomor Memo with the house numbering convention and locks the field read-only; the
     * user can still fill it in themselves instead by never pressing this button, or press
     * "Isi manual" afterwards to undo and type their own.
     */
    public function generateMemoNumberField(): void
    {
        $validated = $this->validate(['periodMonth' => ['required', 'date']]);

        $this->memoNumber = $this->generateMemoNumber(Carbon::parse($validated['periodMonth'])->startOfMonth());
        $this->memoNumberGenerated = true;
    }

    public function useManualMemoNumber(): void
    {
        $this->memoNumber = '';
        $this->memoNumberGenerated = false;
    }

    /**
     * docs/rnd-internal-memo-simplification-prd.md §7.1: the simplified form collects Nama Memo,
     * Bulan Memo, Nomor Memo, dan Catatan. Nomor Memo is required — either generated via
     * generateMemoNumberField() or typed in manually. memo_date/recipient/sender/subject stay
     * NOT NULL at the database level (kept as-is per PRD §10.1 — no schema change for columns not
     * driving the simplified UI), so they get inert defaults here instead of being collected from
     * the user.
     */
    public function createMemo(CreateInternalMemoAction $createMemo): void
    {
        abort_unless(RndInternalMemoResource::canCreate(), 403);

        $validated = $this->validate([
            'memoNumber' => ['required', 'string', 'max:255', 'unique:rnd_internal_memos,memo_number'],
            'memoTitle' => ['required', 'string', 'max:150'],
            'periodMonth' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'branchIds' => ['required', 'array', 'min:1'],
            'branchIds.*' => ['integer'],
        ]);

        $periodMonth = Carbon::parse($validated['periodMonth'])->startOfMonth()->toDateString();

        try {
            $memo = $createMemo->execute([
                'memo_number' => $validated['memoNumber'],
                'title' => $validated['memoTitle'],
                'period_month' => $periodMonth,
                'memo_date' => today()->toDateString(),
                'recipient' => '',
                'sender' => '',
                'subject' => $validated['memoTitle'],
                'notes' => $validated['notes'] ?? null,
                'branch_ids' => $validated['branchIds'],
            ], auth()->user());
        } catch (ValidationException $exception) {
            // The Action validates period_month (against the resolved branches' Company Code)
            // and branch_ids (access/mapping), neither of which this Page can check itself before
            // calling the Action — map both keys back to their form field names.
            $errors = $exception->errors();
            foreach (['period_month' => 'periodMonth', 'branch_ids' => 'branchIds'] as $actionKey => $formKey) {
                if (isset($errors[$actionKey])) {
                    $errors[$formKey] = $errors[$actionKey];
                    unset($errors[$actionKey]);
                }
            }

            throw ValidationException::withMessages($errors);
        }

        $this->createModalOpen = false;
        Notification::make()->title('Memo Internal berhasil dibuat')->success()->send();
        $this->redirect(RndInternalMemoResource::getUrl('view', ['record' => $memo]), navigate: true);
    }

    /** @var array<int, string> */
    private const ROMAN_MONTHS = [
        1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
        7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII',
    ];

    /**
     * Mirrors the house numbering convention shown in the form's own placeholder
     * ("001/RND/IX/2026"): a sequence that resets every year, the Bulan Memo's month as a roman
     * numeral, and its year — all based on the chosen period, not the creation date.
     */
    private function generateMemoNumber(Carbon $periodMonth): string
    {
        $year = $periodMonth->year;
        $sequence = RndInternalMemo::query()->whereYear('period_month', $year)->count() + 1;
        $roman = self::ROMAN_MONTHS[$periodMonth->month];

        do {
            $candidate = sprintf('%03d/RND/%s/%d', $sequence, $roman, $year);
            $sequence++;
        } while (RndInternalMemo::query()->where('memo_number', $candidate)->exists());

        return $candidate;
    }

    public function deleteMemo(int $memoId, DeleteInternalMemoAction $deleteMemo): void
    {
        $memo = RndInternalMemo::query()->findOrFail($memoId);
        abort_unless(RndInternalMemoResource::canDelete($memo), 403);

        try {
            $deleteMemo->execute($memo);
        } catch (RuntimeException $exception) {
            Notification::make()->title('Memo tidak dapat dihapus')->body($exception->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title('Memo Internal berhasil dihapus')->success()->send();
    }
}
