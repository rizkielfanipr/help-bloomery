<?php

namespace App\Http\Controllers;

use App\Enums\ServiceRequestStatus;
use App\Models\Asset;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\AssetServiceRequestAssigner;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AssetIssueReportController extends Controller
{
    public function __invoke(Request $request, string $token, AssetServiceRequestAssigner $assigner): RedirectResponse
    {
        $asset = Asset::query()->where('qr_token', $token)->firstOrFail();
        abort_unless($asset->is_active && $request->user()->canAccessBranch($asset->branch_id), 403);

        $validated = $request->validate([
            'issue' => ['required', 'string', 'max:2000'],
            'photos' => ['required', 'array', 'min:1', 'max:5'],
            'photos.*' => ['required', 'image', 'max:5120'],
        ]);

        $paths = collect($request->file('photos'))->map(
            fn ($photo): string => $photo->store('service-request-attachments', 'b2'),
        )->all();

        $serviceRequest = DB::transaction(function () use ($asset, $request, $validated, $paths): ServiceRequest {
            return ServiceRequest::query()->create([
                'asset_id' => $asset->id,
                'source' => 'asset_qr',
                'scheduled_by' => $request->user()->id,
                'branch_id' => $asset->branch_id,
                'scheduled_date' => today(),
                'requestor_notes' => $validated['issue'],
                'attachments' => $paths,
                'status' => ServiceRequestStatus::Submitted->value,
            ]);
        });

        $technician = $assigner->assign($serviceRequest->load(['asset', 'branch']));

        if (! $technician) {
            $backOfficeUsers = User::query()->where('is_active', true)->permission('edit service requests')->get();
            if ($backOfficeUsers->isNotEmpty()) {
                Notification::make()
                    ->title('Laporan asset menunggu teknisi')
                    ->body("{$serviceRequest->code} belum memiliki teknisi untuk branch {$asset->branch->name}.")
                    ->warning()
                    ->sendToDatabase($backOfficeUsers);
            }
        }

        Notification::make()
            ->title('Laporan asset berhasil dikirim')
            ->body($serviceRequest->code.($technician ? ' ditugaskan kepada '.$technician->name.'.' : ' sedang menunggu teknisi.'))
            ->success()
            ->sendToDatabase($request->user());

        return redirect()->route('assets.scan', $asset->qr_token)->with('success', "Laporan {$serviceRequest->code} berhasil dikirim.");
    }
}
