<?php

namespace App\Http\Controllers;

use App\Models\SalesReportCompliment;
use App\Models\SalesReportVoid;
use App\Services\SalesReport\SalesReportAccess;
use App\Services\SalesReport\SalesReportEvidenceStore;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Streams private Compliment and Void evidence after record authorization
 * (docs/sales-report-compliment-void-remediation-prd.md §16): no public or permanent URL is
 * ever handed out, and the path is never exposed to the browser.
 */
class SalesReportEvidenceController extends Controller
{
    public function __construct(private readonly SalesReportAccess $access, private readonly SalesReportEvidenceStore $evidence) {}

    public function compliment(Request $request, SalesReportCompliment $compliment, int $index): Response
    {
        abort_unless($compliment->salesReport && $this->access->canViewEvidence($request->user(), $compliment->salesReport), 403);

        return $this->stream(($compliment->attachment_paths ?? [])[$index] ?? null);
    }

    public function void(Request $request, SalesReportVoid $void, string $kind): Response
    {
        abort_unless($void->salesReport && $this->access->canViewEvidence($request->user(), $void->salesReport), 403);

        return $this->stream(match ($kind) {
            'original' => $void->original_receipt_path,
            'voided' => $void->voided_receipt_path,
            default => null,
        });
    }

    private function stream(?string $path): Response
    {
        abort_unless(filled($path), 404);
        $disk = $this->evidence->disk();
        abort_unless($disk->exists($path), 404);

        try {
            return $disk->response($path, basename($path), [
                'Cache-Control' => 'private, max-age=600',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        } catch (Throwable) {
            abort(404);
        }
    }
}
