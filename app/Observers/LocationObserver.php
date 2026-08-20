<?php

namespace App\Observers;

use App\Models\Location;
use App\Services\QrCodeGenerator;
use Illuminate\Support\Facades\DB;

class LocationObserver
{
    public function __construct(protected QrCodeGenerator $qrCodeGenerator) {}

    public function saving(Location $location): void
    {
        $parent = $location->parent;

        $location->depth = $parent ? $parent->depth + 1 : 0;
        $location->code = $parent ? $parent->code.'-'.$location->segment : $location->segment;
    }

    public function saved(Location $location): void
    {
        // getOriginal() still reflects the pre-save value here: the "saved" event
        // fires before Eloquent syncs originals, and for a brand new record it's
        // simply null, which correctly counts as "changed" from nothing to a code.
        $oldCode = $location->getOriginal('code');

        if ($oldCode === $location->code) {
            return;
        }

        $location->qr_svg_path = $this->qrCodeGenerator->generate($location->code, "locations/{$location->branch_id}/{$location->id}.svg");
        $location->saveQuietly();

        if ($oldCode === null) {
            return;
        }

        $this->recomputeDescendants($location, $oldCode);
    }

    protected function recomputeDescendants(Location $location, string $oldCode): void
    {
        DB::transaction(function () use ($location, $oldCode): void {
            // Ordering by depth guarantees each ancestor is re-saved before its
            // children are processed, so re-reading $descendant->parent below
            // always sees the already-corrected parent code.
            Location::query()
                ->where('branch_id', $location->branch_id)
                ->where('code', 'like', $oldCode.'-%')
                ->orderBy('depth')
                ->get()
                ->each(function (Location $descendant): void {
                    $parent = $descendant->parent;
                    $newCode = $parent ? $parent->code.'-'.$descendant->segment : $descendant->segment;
                    $newDepth = $parent ? $parent->depth + 1 : 0;

                    if ($descendant->code !== $newCode || $descendant->depth !== $newDepth) {
                        $descendant->code = $newCode;
                        $descendant->depth = $newDepth;
                        $descendant->qr_svg_path = $this->qrCodeGenerator->generate($descendant->code, "locations/{$descendant->branch_id}/{$descendant->id}.svg");
                        $descendant->saveQuietly();
                    }
                });
        });
    }
}
