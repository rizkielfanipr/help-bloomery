<?php

namespace App\Filament\Helpdesk\Resources\RndInternalMemos\Pages;

use App\Actions\Rnd\InternalMemo\CreateInternalMemoAction;
use App\Actions\Rnd\InternalMemo\DeleteInternalMemoAction;
use App\Filament\Helpdesk\Resources\RndInternalMemos\RndInternalMemoResource;
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

    public string $memoTitle = '';

    public string $periodMonth = '';

    public string $notes = '';

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
        $this->reset(['memoNumber', 'memoTitle', 'periodMonth', 'notes']);
        $this->createModalOpen = true;
    }

    public function closeCreateModal(): void
    {
        $this->resetValidation();
        $this->createModalOpen = false;
    }

    /**
     * docs/rnd-internal-memo-simplification-prd.md §7.1: the simplified form only collects Nama
     * Memo, Bulan Memo, Nomor Memo (optional), and Catatan. memo_date/recipient/sender/subject
     * stay NOT NULL at the database level (kept as-is per PRD §10.1 — no schema change for
     * columns not driving the simplified UI), so they get inert defaults here instead of being
     * collected from the user. memo_number likewise stays NOT NULL + unique, so a blank input
     * gets an auto-generated placeholder rather than requiring a migration to make it nullable.
     */
    public function createMemo(CreateInternalMemoAction $createMemo): void
    {
        abort_unless(RndInternalMemoResource::canCreate(), 403);

        $validated = $this->validate([
            'memoNumber' => ['nullable', 'string', 'max:255', 'unique:rnd_internal_memos,memo_number'],
            'memoTitle' => ['required', 'string', 'max:150'],
            'periodMonth' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $periodMonth = Carbon::parse($validated['periodMonth'])->startOfMonth()->toDateString();

        if (RndInternalMemo::query()->where('company_code', RndInternalMemo::COMPANY_CODE)->whereDate('period_month', $periodMonth)->where('revision', 1)->exists()) {
            throw ValidationException::withMessages([
                'periodMonth' => 'Memo untuk periode ini sudah ada.',
            ]);
        }

        $memo = $createMemo->execute([
            'memo_number' => filled($validated['memoNumber']) ? $validated['memoNumber'] : $this->generateMemoNumber(Carbon::parse($periodMonth)),
            'title' => $validated['memoTitle'],
            'period_month' => $periodMonth,
            'memo_date' => today()->toDateString(),
            'recipient' => '',
            'sender' => '',
            'subject' => $validated['memoTitle'],
            'notes' => $validated['notes'] ?? null,
        ], auth()->user());

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
