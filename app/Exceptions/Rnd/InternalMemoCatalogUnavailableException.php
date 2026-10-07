<?php

namespace App\Exceptions\Rnd;

use RuntimeException;

/**
 * The Memo Internal Master Menu catalog cannot be refreshed from ESB right now (no BLSS branch
 * returned, or none returned an active Menu). The message is user-safe: it never contains a token
 * or a credential. The last successful local snapshot stays in use.
 */
class InternalMemoCatalogUnavailableException extends RuntimeException {}
