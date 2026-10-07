<?php

namespace App\Jobs\Rnd;

use App\Actions\Rnd\ShelfLife\SyncWipProductCatalogAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Refreshes the local "Barang WIP" product list behind the Shelf Life menu. The Action holds a
 * `Cache::lock`, so a second dispatch during a run exits immediately; `tries = 1` because the user
 * can simply press refresh again.
 */
class SyncWipProductCatalogJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public ?int $triggeredBy = null) {}

    public function handle(SyncWipProductCatalogAction $action): void
    {
        $action->execute($this->triggeredBy);
    }
}
