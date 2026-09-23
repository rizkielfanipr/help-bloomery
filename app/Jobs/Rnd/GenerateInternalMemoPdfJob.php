<?php

namespace App\Jobs\Rnd;

use App\Actions\Rnd\InternalMemo\GenerateInternalMemoPdfAction;
use App\Models\RndInternalMemo;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * docs/rnd-internal-memo-prd.md §7.6, §17. Rendering a PDF from already-persisted data and
 * writing it to disk is a pure, idempotent operation (a retry just produces another dated file,
 * never corrupts state), so it is safe to auto-retry on a transient failure.
 */
class GenerateInternalMemoPdfJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 15;

    public function __construct(public int $memoId, public int $actorId) {}

    public function handle(GenerateInternalMemoPdfAction $action): void
    {
        $memo = RndInternalMemo::query()->findOrFail($this->memoId);
        $actor = User::query()->findOrFail($this->actorId);

        $action->execute($memo, $actor);
    }
}
