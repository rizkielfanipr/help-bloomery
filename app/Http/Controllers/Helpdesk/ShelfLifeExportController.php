<?php

namespace App\Http\Controllers\Helpdesk;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Retired Shelf Life Product/Menu export (docs/rnd-wip-shelf-life-prd.md §20.4). Shelf Life is now
 * kept per WIP and no WIP export format has been defined, so the old URL answers 410 Gone instead
 * of redirecting to a page with a different meaning.
 *
 * TEMPORARY: remove this controller and its route once the `legacy shelf life export used` log
 * shows no traffic.
 */
class ShelfLifeExportController extends Controller
{
    public function __invoke(Request $request): Response
    {
        Log::info('legacy shelf life export used', ['user_id' => $request->user()?->id]);

        abort(Response::HTTP_GONE, 'Export Shelf Life Menu sudah dipensiunkan. Shelf Life kini dikelola per WIP di menu Shelf Life.');
    }
}
