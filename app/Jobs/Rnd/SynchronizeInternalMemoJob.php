<?php

namespace App\Jobs\Rnd;

use App\Actions\Rnd\InternalMemo\SynchronizeInternalMemoAction;
use App\Enums\RndInternalMemoStatus;
use App\Models\RndInternalMemo;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * docs/rnd-internal-memo-prd.md §17. Every BOM call inside the sync is a GET (read-only), so
 * retrying on a transient connection failure is safe; a data-shaped issue (WIP not found,
 * circular BOM) is not fixed by retrying and is instead reported as NeedsAttention.
 */
class SynchronizeInternalMemoJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 15;

    public function __construct(public int $memoId, public int $actorId) {}

    public function handle(SynchronizeInternalMemoAction $action): void
    {
        $memo = RndInternalMemo::query()->findOrFail($this->memoId);
        $actor = User::query()->findOrFail($this->actorId);

        $action->execute($memo, $actor);
    }

    public function failed(?Throwable $exception): void
    {
        RndInternalMemo::query()->whereKey($this->memoId)->update([
            'status' => RndInternalMemoStatus::NeedsAttention->value,
        ]);
    }
}
