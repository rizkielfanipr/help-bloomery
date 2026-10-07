<?php

namespace App\Filament\Helpdesk\Resources\RndInternalMemos\Pages;

use App\Actions\Rnd\InternalMemo\CreateInternalMemoAction;
use App\Actions\Rnd\InternalMemo\DeleteInternalMemoAction;
use App\Filament\Helpdesk\Resources\RndInternalMemos\RndInternalMemoResource;
use App\Models\Brand;
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

    /** Brand ID, `unresolved` for legacy Memos without a Brand, or empty for every Brand. */
    public string $brandFilter = '';

    public bool $createModalOpen = false;

    public string $memoNumber = '';

    public bool $memoNumberGenerated = false;

    public string $memoTitle = '';

    public string $periodMonth = '';

    public string $notes = '';

    public ?int $brandId = null;

    /**
     * docs/rnd-internal-memo-simplification-prd.md §12.1, docs/rnd-internal-memo-brand-prd.md
     * §12.3: no status filter or workflow cards; search covers name, number, and Brand snapshot,
     * filtered by period and Brand. There is no company or Branch filter.
     */
    public function memos(): Collection
    {
        return RndInternalMemo::query()
            ->with('brand:id,name')
            ->when($this->search !== '', fn (Builder $query) => $query->where(function (Builder $query): void {
                $query->where('title', 'like', '%'.$this->search.'%')
                    ->orWhere('memo_number', 'like', '%'.$this->search.'%')
                    ->orWhere('brand_name_snapshot', 'like', '%'.$this->search.'%');
            }))
            ->when($this->periodFilter !== '', fn (Builder $query) => $query->whereDate('period_month', $this->periodFilter.'-01'))
            ->when($this->brandFilter === 'unresolved', fn (Builder $query) => $query->whereNull('brand_id'))
            ->when(ctype_digit($this->brandFilter), fn (Builder $query) => $query->where('brand_id', (int) $this->brandFilter))
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
        $this->reset(['memoNumber', 'memoNumberGenerated', 'memoTitle', 'periodMonth', 'notes', 'brandId']);
        $this->createModalOpen = true;
    }

    /**
     * Master Brand options, alphabetical (docs/rnd-internal-memo-brand-prd.md §12.1). Every Brand
     * is selectable: Brand is metadata and grants no access.
     *
     * @return Collection<int, Brand>
     */
    public function brandOptions(): Collection
    {
        return Brand::query()->orderBy('name')->get(['id', 'name']);
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
     * Bulan Memo, Nomor Memo, Brand (docs/rnd-internal-memo-brand-prd.md §11.1), dan Catatan. Nomor Memo is required — either generated via
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
            'brandId' => ['required', 'integer', 'exists:brands,id'],
        ], [
            'brandId.required' => 'Pilih Brand Memo.',
            'brandId.exists' => 'Brand yang dipilih tidak ditemukan.',
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
                'brand_id' => $validated['brandId'],
            ], auth()->user());
        } catch (ValidationException $exception) {
            // The Action re-validates the Brand and the Brand–period uniqueness; map its keys back
            // to the form field names so errors stay next to their fields.
            $errors = $exception->errors();
            foreach (['period_month' => 'periodMonth', 'brand_id' => 'brandId', 'memo_number' => 'memoNumber'] as $actionKey => $formKey) {
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
