<?php

namespace App\Jobs\Rnd;

use App\Actions\Rnd\Bom\SyncBomCatalogAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Refreshes the local BOM Assembly catalog from ESB (docs/rnd-bom-adjustment-prd.md §9). The
 * Action itself holds a `Cache::lock` so a second dispatch while one run is already in progress
 * exits immediately instead of running a duplicate pass.
 *
 * `tries = 1`: every ESB call inside the Action is a GET and every local write is an idempotent
 * upsert, so a queue-level retry would be safe — but a full multi-page catalog pass restarting
 * from page 1 after a late-page failure has limited value versus the user simply pressing
 * "Refresh BOM" again, and per-BOM failures already do not abort the rest of the run.
 */
class SyncBomCatalogJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public ?int $triggeredBy = null) {}

    public function handle(SyncBomCatalogAction $action): void
    {
        $action->execute($this->triggeredBy);
    }
}
