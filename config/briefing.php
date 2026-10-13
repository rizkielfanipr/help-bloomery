<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Active Daily Briefing scoring method
    |--------------------------------------------------------------------------
    |
    | 2 = per-item occurrences (legacy). 3 = approved bundles over due obligations
    | (docs/daily-briefing-bundle-approval-prd.md §11). Stays on 2 until the score
    | compare is reviewed and the cutover is approved; switching back is a rollback.
    |
    */

    'scoring_method_version' => (int) env('BRIEFING_SCORING_METHOD_VERSION', 2),

];
