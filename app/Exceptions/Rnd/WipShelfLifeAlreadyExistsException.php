<?php

namespace App\Exceptions\Rnd;

use App\Models\RndProductEsbShelfLife;
use RuntimeException;

/**
 * A WIP Shelf Life master already exists for this identity — creation never overwrites it
 * (docs/rnd-wip-shelf-life-prd.md §14.1, §25). Carries the existing record so the caller can
 * refresh and show its value instead.
 */
class WipShelfLifeAlreadyExistsException extends RuntimeException
{
    public function __construct(public readonly RndProductEsbShelfLife $existing)
    {
        parent::__construct('Shelf Life untuk WIP ini sudah tersedia. Data terbaru ditampilkan; perubahan dilakukan dari menu Shelf Life.');
    }
}
