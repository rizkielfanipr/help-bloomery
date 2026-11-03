<?php

namespace App\Services\Purchasing\VendorCompliance;

use App\Filament\Helpdesk\Resources\VendorComplianceIncidents\VendorComplianceIncidentResource;
use App\Models\User;
use App\Models\VendorComplianceIncident;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Database notification for a new Late Delivery candidate (PRD §15.7). Sent after the Receiving
 * transaction commits, wording does not blame the vendor, and a failure never breaks the receipt.
 */
class VendorComplianceNotifier
{
    public function lateDeliveryCandidate(VendorComplianceIncident $incident): void
    {
        DB::afterCommit(function () use ($incident): void {
            try {
                $recipients = User::query()->where('is_active', true)->permission('edit vendor compliance incidents')->get();
                if ($recipients->isEmpty()) {
                    return;
                }

                Notification::make()->warning()
                    ->title('Keterlambatan delivery perlu diverifikasi')
                    ->body("PO {$incident->goodsReceipt?->reference_number} · {$incident->supplier_name} terdeteksi terlambat {$incident->delay_days} hari.")
                    ->actions([
                        Action::make('open')->label('Verifikasi')->url(VendorComplianceIncidentResource::getUrl('view', ['record' => $incident], panel: 'helpdesk')),
                    ])
                    ->sendToDatabase($recipients);
            } catch (Throwable $exception) {
                Log::warning('Late delivery notification failed.', ['incident_id' => $incident->id, 'exception' => $exception::class]);
            }
        });
    }
}
