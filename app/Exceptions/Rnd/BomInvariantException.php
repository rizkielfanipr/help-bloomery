<?php

namespace App\Exceptions\Rnd;

use RuntimeException;

/**
 * A recipe invariant was violated (docs/rnd-bom-adjustment-prd.md §13) — the update is stopped
 * before any mutation attempt. `field` lets the caller attach the message to the right form
 * field (e.g. a Livewire `addError()` key).
 */
class BomInvariantException extends RuntimeException
{
    public function __construct(public readonly string $field, string $message)
    {
        parent::__construct($message);
    }
}
