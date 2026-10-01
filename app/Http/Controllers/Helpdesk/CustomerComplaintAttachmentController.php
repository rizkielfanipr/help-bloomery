<?php

namespace App\Http\Controllers\Helpdesk;

use App\Http\Controllers\Controller;
use App\Models\CustomerComplaint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Serves private Customer Complaint attachments, mirroring
 * RndProjectTaskAttachmentController's authorization + strict path pattern
 * (docs/customer-complaints-prd.md §15, §20.3) rather than ERP Request's unauthenticated inline
 * URL. The path itself does not embed a complaint id (it is only `customer-complaints/{branchId}/
 * {filename}`), so the complaint is instead resolved by finding which record actually has this
 * exact path registered on it — a path that is not registered to any complaint 404s before
 * authorization even runs, which also defends against guessing another branch's file path.
 */
class CustomerComplaintAttachmentController extends Controller
{
    public function show(Request $request, string $path): Response
    {
        $complaint = CustomerComplaint::query()->whereJsonContains('attachment_paths', $path)->first();
        abort_unless($complaint, 404);
        abort_unless($request->user()?->can('view', $complaint), 403);
        abort_unless(Storage::disk('b2')->exists($path), 404);

        try {
            return Storage::disk('b2')->response($path, null, ['Cache-Control' => 'private, max-age=3600']);
        } catch (Throwable) {
            abort(404);
        }
    }
}
