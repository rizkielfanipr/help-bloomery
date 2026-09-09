<?php

namespace App\Http\Controllers;

use App\Enums\ServiceRequestStatus;
use App\Models\Asset;
use Illuminate\Contracts\View\View;

class AssetScanController extends Controller
{
    public function __invoke(string $token): View
    {
        $asset = Asset::query()->with('branch')->where('qr_token', $token)->firstOrFail();
        $activeRequest = $asset->serviceRequests()
            ->whereNotIn('status', [ServiceRequestStatus::Completed->value, ServiceRequestStatus::Warranty->value])
            ->latest()
            ->first();

        return view('assets.scan', compact('asset', 'activeRequest'));
    }
}
