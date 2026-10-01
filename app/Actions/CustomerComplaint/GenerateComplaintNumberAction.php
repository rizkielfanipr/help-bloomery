<?php

namespace App\Actions\CustomerComplaint;

use App\Models\CustomerComplaint;

/**
 * docs/customer-complaints-prd.md §13: `CMP-YYYYMMDD-NNNN`, atomic and safe under concurrent
 * submits. Must be called from inside an existing `DB::transaction()` (see
 * CreateCustomerComplaintAction) so the row lock below is held until the caller's INSERT
 * completes — a bare `count + 1` without a lock would let two concurrent requests compute the
 * same number. The unique index on `complaint_number` is a second, independent safety net for the
 * very first number of a day, when no row yet exists to lock.
 */
class GenerateComplaintNumberAction
{
    public function execute(): string
    {
        $prefix = 'CMP-'.now()->format('Ymd').'-';

        $maxSequence = CustomerComplaint::withTrashed()
            ->where('complaint_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->get(['complaint_number'])
            ->map(fn (CustomerComplaint $complaint): int => (int) substr($complaint->complaint_number, -4))
            ->max() ?? 0;

        return $prefix.str_pad((string) ($maxSequence + 1), 4, '0', STR_PAD_LEFT);
    }
}
