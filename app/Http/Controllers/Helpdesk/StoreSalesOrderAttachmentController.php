<?php

namespace App\Http\Controllers\Helpdesk;

use App\Http\Controllers\Controller;
use App\Models\StoreSalesOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Serves private Store Sales Order attachments, mirroring
 * CustomerComplaintAttachmentController's authorization + strict path pattern
 * (docs/store-sales-order-prd.md §10.4, §20.4) rather than ERP Request's unauthenticated inline
 * URL. The path itself does not embed an order id (it is only
 * `store-sales-orders/{branchId}/{filename}`), so the order is instead resolved by finding which
 * record actually has this exact path registered on it — a path that is not registered to any
 * order 404s before authorization even runs, which also defends against guessing another branch's
 * file path.
 */
class StoreSalesOrderAttachmentController extends Controller
{
    public function show(Request $request, string $path): Response
    {
        $order = StoreSalesOrder::query()->whereJsonContains('attachment_paths', $path)->first();
        abort_unless($order, 404);
        abort_unless($request->user()?->can('view', $order), 403);
        abort_unless(Storage::disk('b2')->exists($path), 404);

        try {
            return Storage::disk('b2')->response($path, null, ['Cache-Control' => 'private, max-age=3600']);
        } catch (Throwable) {
            abort(404);
        }
    }
}
