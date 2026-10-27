<?php

namespace App\Http\Controllers\Helpdesk;

use App\Http\Controllers\Controller;
use App\Models\RndMaterialSampleAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Serves private sample photos after authorization (docs/material-sourcing-lifecycle-prd.md §23),
 * following the R&D Task attachment pattern instead of public URLs.
 */
class RndMaterialSampleAttachmentController extends Controller
{
    public function show(Request $request, RndMaterialSampleAttachment $attachment): Response
    {
        $material = $attachment->cycle?->material;
        abort_unless($material && $request->user()?->can('viewSampleEvidence', $material), 403);

        $disk = Storage::disk($attachment->disk);
        abort_unless($disk->exists($attachment->path), 404);

        try {
            return $disk->response($attachment->path, $attachment->original_name, [
                'Content-Type' => $attachment->mime_type,
                'Cache-Control' => 'private, max-age=600',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        } catch (Throwable) {
            abort(404);
        }
    }
}
