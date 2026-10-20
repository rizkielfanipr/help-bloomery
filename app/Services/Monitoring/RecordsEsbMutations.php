<?php

namespace App\Services\Monitoring;

use Closure;

/**
 * Lets an ESB domain service wrap each mutation request with the transaction recorder without
 * changing its constructor signature.
 */
trait RecordsEsbMutations
{
    /**
     * @template TResult
     *
     * @param  Closure(EsbAttempt): TResult  $send
     * @return TResult
     */
    protected function recordEsbMutation(EsbMutation $mutation, Closure $send): mixed
    {
        return app(EsbTransactionRecorder::class)->attempt($mutation, $send);
    }
}
