<?php

namespace App\Http\Controllers\Helpdesk;

use App\Enums\RndWipShelfLifeStatus;
use App\Filament\Helpdesk\Pages\WipShelfLifePage;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Compatibility redirect for the retired `Master Shelf Life Menu` Resource URLs
 * (docs/rnd-wip-shelf-life-prd.md §20.3). Sends bookmarks to the WIP Shelf Life menu filtered on
 * missing Shelf Life; that page enforces its own authorization. No Menu ID is ever carried over as
 * a WIP identity.
 *
 * TEMPORARY: remove this controller and its routes once the `legacy shelf life route used` log
 * shows no traffic and a reference audit is clean.
 */
class LegacyShelfLifeRedirectController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        Log::info('legacy shelf life route used', [
            'path' => $request->path(),
            'user_id' => $request->user()?->id,
        ]);

        return redirect()->to(WipShelfLifePage::getUrl(['shelfLifeFilter' => RndWipShelfLifeStatus::Missing->value], panel: 'helpdesk'));
    }
}
