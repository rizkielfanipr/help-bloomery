<?php

namespace App\Exceptions\Rnd;

use RuntimeException;

/**
 * The BOM's `editedDate` changed in ESB after the form was loaded — the update is stopped
 * before any mutation attempt (docs/rnd-bom-adjustment-prd.md §14).
 */
class BomConflictException extends RuntimeException {}
