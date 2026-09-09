<?php

namespace App\Http\Controllers\Helpdesk;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssetLabelController extends Controller
{
    public function single(Asset $asset): Response
    {
        $this->authorizeAsset($asset);

        return Pdf::loadView('exports.asset-label', ['labels' => [$this->labelData($asset)]])
            ->setPaper('a4', 'portrait')
            ->download("label-asset-{$asset->asset_number}.pdf");
    }

    public function bulk(Request $request): Response
    {
        abort_unless(auth()->user()?->can('print asset labels'), 403);
        $ids = collect($request->query('ids', []))->map(fn ($id): int => (int) $id)->filter()->values();
        abort_if($ids->isEmpty(), 422);

        $assets = Asset::query()->with('branch')->whereIn('id', $ids)->get();
        abort_unless($assets->count() === $ids->unique()->count(), 404);
        abort_unless($assets->every(fn (Asset $asset): bool => auth()->user()->canAccessBranch($asset->branch_id)), 403);

        return Pdf::loadView('exports.asset-label', ['labels' => $assets->map(fn (Asset $asset): array => $this->labelData($asset))->all()])
            ->setPaper('a4', 'portrait')
            ->download('label-assets.pdf');
    }

    public function qr(Asset $asset): StreamedResponse
    {
        $this->authorizeAsset($asset);
        abort_unless($asset->qr_svg_path && Storage::disk('b2')->exists($asset->qr_svg_path), 404);

        return Storage::disk('b2')->download($asset->qr_svg_path, "qr-{$asset->asset_number}.svg");
    }

    private function authorizeAsset(Asset $asset): void
    {
        abort_unless(auth()->user()?->can('print asset labels'), 403);
        abort_unless(auth()->user()->canAccessBranch($asset->branch_id), 403);
    }

    /** @return array{number: string, name: string, branch: string, qr: ?string} */
    private function labelData(Asset $asset): array
    {
        $asset->loadMissing('branch');

        return [
            'number' => $asset->asset_number,
            'name' => $asset->name,
            'branch' => $asset->branch->name,
            'qr' => $asset->qrDataUri(),
        ];
    }
}
