<?php

namespace App\Filament\Helpdesk\Resources\RndInternalMemos\Pages;

use App\Actions\Rnd\InternalMemo\CreateInternalMemoAction;
use App\Enums\RndInternalMemoStatus;
use App\Filament\Helpdesk\Resources\RndInternalMemos\RndInternalMemoResource;
use App\Models\RndInternalMemo;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class ListRndInternalMemos extends ListRecords
{
    protected static string $resource = RndInternalMemoResource::class;

    protected string $view = 'filament.helpdesk.rnd-internal-memos.index';

    public string $search = '';

    public string $statusFilter = '';

    public string $periodFilter = '';

    public bool $createModalOpen = false;

    public string $memoNumber = '';

    public string $memoTitle = '';

    public string $periodMonth = '';

    public string $memoDate = '';

    public string $recipient = '';

    public string $sender = '';

    public string $subject = '';

    public string $notes = '';

    /**
     * Archived memos are hidden from the default "Semua Status" list (§6: "Dokumen disembunyikan
     * dari daftar aktif") and only appear once the user explicitly filters for `archived`.
     */
    public function memos(): Collection
    {
        return RndInternalMemo::query()
            ->when($this->search !== '', fn (Builder $query) => $query->where(function (Builder $query): void {
                $query->where('title', 'like', '%'.$this->search.'%')
                    ->orWhere('memo_number', 'like', '%'.$this->search.'%');
            }))
            ->when(
                $this->statusFilter !== '',
                fn (Builder $query) => $query->where('status', $this->statusFilter),
                fn (Builder $query) => $query->where('status', '!=', RndInternalMemoStatus::Archived->value),
            )
            ->when($this->periodFilter !== '', fn (Builder $query) => $query->whereDate('period_month', $this->periodFilter.'-01'))
            ->latest('period_month')
            ->latest('revision')
            ->get();
    }

    /** @return array<string, int> */
    public function summary(): array
    {
        $counts = RndInternalMemo::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return [
            'draft' => (int) ($counts[RndInternalMemoStatus::Draft->value] ?? 0),
            'needs_attention' => (int) ($counts[RndInternalMemoStatus::NeedsAttention->value] ?? 0),
            'ready' => (int) ($counts[RndInternalMemoStatus::Ready->value] ?? 0),
            'finalized' => (int) ($counts[RndInternalMemoStatus::Finalized->value] ?? 0),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function openCreateModal(): void
    {
        abort_unless(RndInternalMemoResource::canCreate(), 403);
        $this->resetValidation();
        $this->reset(['memoNumber', 'memoTitle', 'periodMonth', 'memoDate', 'recipient', 'sender', 'subject', 'notes']);
        $this->memoDate = today()->toDateString();
        $this->createModalOpen = true;
    }

    public function closeCreateModal(): void
    {
        $this->resetValidation();
        $this->createModalOpen = false;
    }

    public function createMemo(CreateInternalMemoAction $createMemo): void
    {
        abort_unless(RndInternalMemoResource::canCreate(), 403);

        $validated = $this->validate([
            'memoNumber' => ['required', 'string', 'max:255', 'unique:rnd_internal_memos,memo_number'],
            'memoTitle' => ['required', 'string', 'max:255'],
            'periodMonth' => ['required', 'date'],
            'memoDate' => ['required', 'date'],
            'recipient' => ['required', 'string', 'max:255'],
            'sender' => ['required', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $periodMonth = Carbon::parse($validated['periodMonth'])->startOfMonth()->toDateString();

        if (RndInternalMemo::query()->where('company_code', RndInternalMemo::COMPANY_CODE)->whereDate('period_month', $periodMonth)->where('revision', 1)->exists()) {
            throw ValidationException::withMessages([
                'periodMonth' => 'Memo untuk periode ini sudah ada.',
            ]);
        }

        $memo = $createMemo->execute([
            'memo_number' => $validated['memoNumber'],
            'title' => $validated['memoTitle'],
            'period_month' => $periodMonth,
            'memo_date' => $validated['memoDate'],
            'recipient' => $validated['recipient'],
            'sender' => $validated['sender'],
            'subject' => $validated['subject'],
            'notes' => $validated['notes'] ?? null,
        ], auth()->user());

        $this->createModalOpen = false;
        Notification::make()->title('Memo Internal berhasil dibuat')->success()->send();
        $this->redirect(RndInternalMemoResource::getUrl('view', ['record' => $memo]), navigate: true);
    }
}
