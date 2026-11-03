<?php

namespace App\Http\Controllers;

use App\Models\MaterialSourcingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Streams a private Request Sourcing attachment to its requester or a Purchasing viewer (PRD §11.22).
 * Only the request ID and list index come from the browser; disk and path come from the record, and
 * no signed URL is created or logged.
 */
class MaterialSourcingRequestAttachmentController extends Controller
{
    public function show(Request $request, MaterialSourcingRequest $sourcingRequest, int $index): Response
    {
        abort_unless($request->user()?->can('downloadAttachment', $sourcingRequest), 403);

        $attachment = $sourcingRequest->attachmentList()[$index] ?? null;
        abort_unless(is_array($attachment) && str_starts_with($attachment['path'], 'purchasing/material-sourcing-requests/'.$sourcingRequest->id.'/'), 404);

        $disk = Storage::disk(MaterialSourcingRequest::ATTACHMENT_DISK);
        abort_unless($disk->exists($attachment['path']), 404);

        try {
            return $disk->response($attachment['path'], $attachment['name'], [
                'Content-Type' => $attachment['mime'] ?: 'application/octet-stream',
                'Cache-Control' => 'private, max-age=600',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        } catch (Throwable) {
            abort(404);
        }
    }
}
