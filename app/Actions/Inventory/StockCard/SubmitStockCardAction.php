<?php

namespace App\Actions\Inventory\StockCard;

use App\Enums\StockCardStatus;
use App\Models\Employee;
use App\Models\StockCard;
use App\Models\StockCardApproval;
use App\Models\StockCardEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SubmitStockCardAction
{
    /**
     * Validate what is stored rather than what the browser holds, then lock the staff count for
     * review. The row lock and status recheck let only one of two concurrent submits through.
     */
    public function execute(User $actor, string $reportDate): StockCard
    {
        return DB::transaction(function () use ($actor, $reportDate): StockCard {
            $card = StockCard::query()
                ->where('branch_id', $actor->branch_id)
                ->whereDate('report_date', $reportDate)
                ->lockForUpdate()
                ->first();

            if ($card === null) {
                throw ValidationException::withMessages(['status' => 'Draft Stock Card belum tersedia.']);
            }

            if (! $card->status->canBeEditedBySubmitter()) {
                throw ValidationException::withMessages(['status' => 'Stock Card sudah dikirim dan tidak dapat diubah.']);
            }

            Gate::forUser($actor)->authorize('submit', $card);

            $this->assertEntriesComplete($card);
            $this->assertEmployeesValid($card);

            // This snapshot is the baseline the Supervisor compares their own correction against.
            $card->entries()->update(['reported_qty' => DB::raw('actual_qty')]);

            $card->update([
                'submitted_by' => $actor->id,
                'submitted_at' => now(),
                'status' => StockCardStatus::PendingSupervisor->value,
            ]);

            StockCardApproval::create([
                'stock_card_id' => $card->id,
                'stage' => 'submitter',
                'action' => 'submitted',
                'actor_id' => $actor->id,
                'notes' => null,
                'revision_number' => $card->revision_number,
            ]);

            return $card;
        });
    }

    private function assertEntriesComplete(StockCard $card): void
    {
        $entries = $card->entries()->get(['id', 'actual_qty']);

        if ($entries->isEmpty()) {
            throw ValidationException::withMessages(['rows' => 'Tidak ada produk Stock Movement yang dapat dilaporkan.']);
        }

        if ($entries->contains(fn (StockCardEntry $entry): bool => $entry->actual_qty === null || (float) $entry->actual_qty < 0)) {
            throw ValidationException::withMessages(['rows' => 'Pastikan semua item sudah diisi qty aktualnya.']);
        }
    }

    private function assertEmployeesValid(StockCard $card): void
    {
        $employees = $card->employees()->get(['employee_id']);

        if ($employees->isEmpty()) {
            throw ValidationException::withMessages(['employeeIds' => 'Pilih minimal satu staff yang mengisi Stock Card.']);
        }

        $activeCount = Employee::query()
            ->where('branch_id', $card->branch_id)
            ->where('is_active', true)
            ->whereIn('id', $employees->pluck('employee_id')->filter())
            ->count();

        if ($activeCount !== $employees->count()) {
            throw ValidationException::withMessages(['employeeIds' => 'Staff tidak aktif atau tidak terdaftar pada branch ini.']);
        }
    }
}
