<?php

namespace App\Exceptions\Briefing;

use RuntimeException;

/**
 * More than one row claims the same bundle business key, so picking one would be a guess
 * (docs/daily-briefing-bundle-approval-prd.md §15.6). Resolved by reconciliation, never at runtime.
 */
class BriefingBundleConflictException extends RuntimeException
{
    /**
     * @param  list<int>  $recordIds
     */
    public function __construct(public readonly array $recordIds)
    {
        parent::__construct('Terdapat lebih dari satu data briefing untuk periode ini. Hubungi HR untuk rekonsiliasi.');
    }
}
