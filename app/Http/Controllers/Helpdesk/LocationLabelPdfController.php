<?php

namespace App\Http\Controllers\Helpdesk;

use App\Http\Controllers\Controller;
use App\Models\Location;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class LocationLabelPdfController extends Controller
{
    public function single(int $location): Response
    {
        $user = auth()->user();
        abort_unless($user?->can('view locations'), 403);

        $record = Location::query()->findOrFail($location);
        abort_unless($user->canAccessBranch($record->branch_id), 403);

        $pdf = Pdf::loadView('exports.location-label', [
            'labels' => [$this->toLabelData($record)],
        ])->setPaper('a4', 'portrait');

        return $pdf->download("label-lokasi-{$record->code}.pdf");
    }

    public function bulk(Request $request): Response
    {
        $user = auth()->user();
        abort_unless($user?->can('view locations'), 403);

        $ids = collect($request->query('ids', []))
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->values();

        abort_if($ids->isEmpty(), 422);

        $records = Location::query()->whereIn('id', $ids)->get();

        abort_unless(
            $records->every(fn (Location $location): bool => $user->canAccessBranch($location->branch_id)),
            403,
        );

        $pdf = Pdf::loadView('exports.location-label', [
            'labels' => $records->map(fn (Location $location) => $this->toLabelData($location))->all(),
        ])->setPaper('a4', 'portrait');

        return $pdf->download('label-lokasi.pdf');
    }

    /** @return array{code: string, name: string, type: string, qr: ?string} */
    private function toLabelData(Location $location): array
    {
        return [
            'code' => $location->code,
            'name' => $location->name,
            'type' => $location->type,
            'qr' => $this->qrDataUri($location),
        ];
    }

    private function qrDataUri(Location $location): ?string
    {
        if (! $location->qr_svg_path) {
            return null;
        }

        $contents = Storage::disk('b2')->get($location->qr_svg_path);

        if (! is_string($contents) || $contents === '') {
            return null;
        }

        return 'data:image/svg+xml;base64,'.base64_encode($contents);
    }
}
