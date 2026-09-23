<?php

namespace App\Actions\Rnd\InternalMemo;

use App\Enums\RndInternalMemoStatus;
use App\Models\RndInternalMemo;
use RuntimeException;

class DeleteInternalMemoAction
{
    /** @var list<RndInternalMemoStatus> */
    private const DELETABLE_STATUSES = [
        RndInternalMemoStatus::Draft,
        RndInternalMemoStatus::NeedsAttention,
        RndInternalMemoStatus::Ready,
    ];

    public function execute(RndInternalMemo $memo): void
    {
        if (! in_array($memo->status, self::DELETABLE_STATUSES, true)) {
            throw new RuntimeException('Memo hanya dapat dihapus sebelum proses finalisasi.');
        }

        $memo->delete();
    }
}
