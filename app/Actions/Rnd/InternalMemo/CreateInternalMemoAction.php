<?php

namespace App\Actions\Rnd\InternalMemo;

use App\Enums\RndInternalMemoStatus;
use App\Models\RndInternalMemo;
use App\Models\User;
use App\Services\Rnd\InternalMemo\InternalMemoBrandValidator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * docs/rnd-internal-memo-brand-prd.md §11.1, §15.1. The user picks exactly one Brand; Company Code
 * is always RndInternalMemo::COMPANY_CODE, set here and never read from input. The Brand is
 * metadata only: no Branch resolution, no Memo–Branch rows, and no catalog sync is triggered.
 */
class CreateInternalMemoAction
{
    public function __construct(private readonly InternalMemoBrandValidator $brands) {}

    /** @param array{memo_number: string, title: string, period_month: string, memo_date: string, recipient: string, sender: string, subject: string, notes: ?string, brand_id: int|string|null} $data */
    public function execute(array $data, User $actor): RndInternalMemo
    {
        if (! $actor->can('create', RndInternalMemo::class)) {
            throw new AuthorizationException('Anda tidak berhak membuat Memo Internal.');
        }

        $brand = $this->brands->brand($data['brand_id'] ?? null);
        $this->brands->ensureUniquePeriod($brand, $data['period_month'], 1);

        try {
            $memo = DB::transaction(fn (): RndInternalMemo => RndInternalMemo::query()->create([
                'company_code' => RndInternalMemo::COMPANY_CODE,
                'brand_id' => $brand->id,
                'brand_name_snapshot' => $brand->name,
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
            ]));
        } catch (UniqueConstraintViolationException) {
            Log::warning('rnd internal memo create hit a unique constraint', ['brand_id' => $brand->id, 'company_code' => RndInternalMemo::COMPANY_CODE]);

            throw RndInternalMemo::query()->where('memo_number', trim((string) $data['memo_number']))->exists()
                ? ValidationException::withMessages(['memo_number' => 'Nomor Memo sudah digunakan.'])
                : $this->brands->duplicatePeriod($brand);
        }

        Log::info('rnd internal memo created', ['memo_id' => $memo->id, 'brand_id' => $brand->id, 'company_code' => $memo->company_code]);

        return $memo;
    }
}
