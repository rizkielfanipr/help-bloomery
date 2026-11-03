<?php

namespace App\Http\Controllers\Helpdesk;

use App\Actions\BrandMarketing\DesignMaster\PrepareDesignFileUrlAction;
use App\Http\Controllers\Controller;
use App\Models\MarketingDesignFile;
use App\Services\BrandMarketing\DesignMaster\MarketingDesignAccess;
use App\Services\BrandMarketing\DesignMaster\MarketingDesignFileStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Serves private design files after authorization (PRD §16). Only the record ID comes from the
 * browser; the disk and path always come from the record. Removed files resolve to 404.
 */
class MarketingDesignFileController extends Controller
{
    public function __construct(
        private readonly MarketingDesignAccess $access,
        private readonly PrepareDesignFileUrlAction $urls,
        private readonly MarketingDesignFileStore $store,
    ) {}

    public function preview(Request $request, MarketingDesignFile $file): RedirectResponse
    {
        abort_unless($request->user() && $this->access->allows($request->user(), 'preview', $file), 403);

        return $this->redirectTo($file, fn (): string => $this->urls->preview($request->user(), $file));
    }

    public function download(Request $request, MarketingDesignFile $file): RedirectResponse
    {
        abort_unless($request->user() && $this->access->allows($request->user(), 'download', $file), 403);

        return $this->redirectTo($file, fn (): string => $this->urls->download($request->user(), $file));
    }

    /** @param  callable(): string  $resolveUrl */
    private function redirectTo(MarketingDesignFile $file, callable $resolveUrl): RedirectResponse
    {
        try {
            abort_unless($this->store->exists($file), 404);

            return redirect()->away($resolveUrl())->withHeaders(['Cache-Control' => 'private, no-store']);
        } catch (Throwable $exception) {
            if ($exception instanceof HttpExceptionInterface) {
                throw $exception;
            }

            Log::warning('Master Design file URL failed', ['file_id' => $file->id, 'exception' => $exception::class]);
            abort(503, 'File sedang tidak dapat diakses.');
        }
    }
}
