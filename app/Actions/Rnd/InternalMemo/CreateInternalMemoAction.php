<?php

namespace App\Actions\Rnd\InternalMemo;

use App\Enums\RndInternalMemoStatus;
use App\Models\RndInternalMemo;
use App\Models\User;

/**
 * docs/rnd-internal-memo-prd.md §7.1. Company Code is fixed to BLSS server-side; it is never
 * accepted from the request.
 */
class CreateInternalMemoAction
{
    /** @param array<string, mixed> $data */
    public function execute(array $data, User $actor): RndInternalMemo
    {
        return RndInternalMemo::query()->create([
            'company_code' => RndInternalMemo::COMPANY_CODE,
            'memo_number' => trim((string) $data['memo_number']),
            'title' => trim((string) $data['title']),
            'period_month' => $data['period_month'],
            'memo_date' => $data['memo_date'],
            'recipient' => trim((string) $data['recipient']),
            'sender' => trim((string) $data['sender']),
            'subject' => trim((string) $data['subject']),
            'notes' => filled($data['notes'] ?? null) ? trim((string) $data['notes']) : null,
            'status' => RndInternalMemoStatus::Draft,
            'revision' => 1,
            'created_by' => $actor->id,
        ]);
    }
}
