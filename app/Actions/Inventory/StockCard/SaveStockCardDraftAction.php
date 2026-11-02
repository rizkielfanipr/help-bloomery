<?php

namespace App\Actions\Inventory\StockCard;

use App\Models\StockCard;
use App\Models\StockCardEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SaveStockCardDraftAction
{
    /**
     * Write only what changed: one autosave touches the given entries, and employees only when a
     * list is passed. Entries are never created or removed here, so a row dropped from the catalog
     * survives and the browser cannot add products.
     *
     * @param  array<int|string, array{product_code: string, actual_qty: mixed, notes: mixed}>  $entryChanges
     * @param  list<int|string>|null  $employeeIds  null leaves the stored employees untouched
     */
    public function execute(User $actor, string $reportDate, array $entryChanges = [], ?array $employeeIds = null): StockCard
    {
        $this->validate($actor, $entryChanges, $employeeIds);

        return DB::transaction(function () use ($actor, $reportDate, $entryChanges, $employeeIds): StockCard {
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

            Gate::forUser($actor)->authorize('editDraft', $card);

            $hasChanges = $this->saveEntries($card, $entryChanges);

            if ($employeeIds !== null) {
                $hasChanges = $this->syncEmployees($card, $actor, $employeeIds) || $hasChanges;
            }

            if ($hasChanges) {
                $card->touch();
            }

            return $card;
        });
    }

    /**
     * @param  array<int|string, array{product_code: string, actual_qty: mixed, notes: mixed}>  $entryChanges
     * @param  list<int|string>|null  $employeeIds
     */
    private function validate(User $actor, array $entryChanges, ?array $employeeIds): void
    {
        validator(
            ['rows' => $entryChanges, 'employeeIds' => $employeeIds ?? []],
            [
                'rows.*.product_code' => ['required', 'string'],
                'rows.*.actual_qty' => [function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! StockCardEntry::isValidQuantity($value)) {
                        $fail('Qty harus berupa angka minimal 0 dengan maksimal 4 angka desimal.');
                    }
                }],
                'rows.*.notes' => ['nullable', 'string', 'max:2000'],
                'employeeIds' => ['array'],
                'employeeIds.*' => [
                    'integer',
                    Rule::exists('employees', 'id')->where(fn ($query) => $query
                        ->where('branch_id', $actor->branch_id)
                        ->where('is_active', true)),
                ],
            ],
            ['employeeIds.*.exists' => 'Staff tidak aktif atau tidak terdaftar pada branch ini.'],
        )->validate();
    }

    /** @param array<int|string, array{product_code: string, actual_qty: mixed, notes: mixed}> $entryChanges */
    private function saveEntries(StockCard $card, array $entryChanges): bool
    {
        if ($entryChanges === []) {
            return false;
        }

        $entries = $card->entries()
            ->whereIn('product_code', array_column($entryChanges, 'product_code'))
            ->get()
            ->keyBy('product_code');
        $hasChanges = false;

        foreach ($entryChanges as $key => $change) {
            $entry = $entries->get($change['product_code']);

            if ($entry === null) {
                throw ValidationException::withMessages(["rows.{$key}.actual_qty" => 'Produk tidak terdaftar pada Stock Card ini.']);
            }

            $notes = trim((string) ($change['notes'] ?? ''));
            $entry->fill([
                'actual_qty' => StockCardEntry::normalizeQuantity($change['actual_qty'] ?? null),
                'notes' => $notes !== '' ? $change['notes'] : null,
            ]);

            if ($entry->isDirty()) {
                $entry->save();
                $hasChanges = true;
            }
        }

        return $hasChanges;
    }

    /** @param list<int|string> $employeeIds */
    private function syncEmployees(StockCard $card, User $actor, array $employeeIds): bool
    {
        $employees = $actor->branch->employees()->whereIn('id', $employeeIds)->get();
        $storedIds = $card->employees()->pluck('employee_id')->filter()->sort()->values()->all();

        if ($storedIds === $employees->pluck('id')->sort()->values()->all()) {
            return false;
        }

        $card->employees()->delete();
        $card->employees()->createMany($employees->map(fn ($employee): array => [
            'employee_id' => $employee->id,
            'employee_code' => $employee->employee_code,
            'employee_name' => $employee->name,
            'employee_position' => $employee->position,
        ])->all());

        return true;
    }
}
