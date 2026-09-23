<?php

namespace App\Actions\Rnd\InternalMemo;

use App\Enums\RndInternalMemoStatus;
use App\Models\RndInternalMemo;
use App\Models\User;
use RuntimeException;

/**
 * docs/rnd-internal-memo-prd.md §6. Toggles Finalized ↔ Archived; RndInternalMemoPolicy::archive
 * already restricts the caller to exactly those two starting statuses.
 */
class ArchiveInternalMemoAction
{
    public function execute(RndInternalMemo $memo, User $actor): RndInternalMemo
    {
        $memo->update(match ($memo->status) {
            RndInternalMemoStatus::Finalized => ['status' => RndInternalMemoStatus::Archived, 'archived_by' => $actor->id, 'archived_at' => now()],
            RndInternalMemoStatus::Archived => ['status' => RndInternalMemoStatus::Finalized, 'archived_by' => null, 'archived_at' => null],
            default => throw new RuntimeException('Memo hanya dapat diarsipkan dari status Finalized, atau dipulihkan dari status Archived.'),
        });

        return $memo->refresh();
    }
}
