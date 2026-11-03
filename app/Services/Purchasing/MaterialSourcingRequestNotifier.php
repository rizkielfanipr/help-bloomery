<?php

namespace App\Services\Purchasing;

use App\Filament\Casual\Pages\MaterialSourcingRequestHistoryPage;
use App\Filament\Helpdesk\Resources\MaterialSourcingRequests\MaterialSourcingRequestResource;
use App\Models\MaterialSourcingRequest;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Database notifications for Request Sourcing (PRD §15.8/§29). They are sent after the surrounding
 * transaction commits, never carry supplier or price data, and a failure never breaks the step.
 */
class MaterialSourcingRequestNotifier
{
    public function submitted(MaterialSourcingRequest $request, bool $resubmitted = false): void
    {
        $this->afterCommit($request, 'submitted', function () use ($request, $resubmitted): void {
            $recipients = User::query()->where('is_active', true)->permission('process material sourcing requests')->get();
            if ($recipients->isEmpty()) {
                return;
            }

            Notification::make()->warning()
                ->title($resubmitted ? 'Request Sourcing diperbaiki' : 'Request Sourcing baru')
                ->body("{$request->request_number} · {$request->product_name} dari {$request->branch_name}.")
                ->actions([
                    Action::make('open')->label('Buka')->url(MaterialSourcingRequestResource::getUrl('view', ['record' => $request], panel: 'helpdesk')),
                ])
                ->sendToDatabase($recipients);
        });
    }

    public function revisionRequested(MaterialSourcingRequest $request): void
    {
        $this->notifyRequester($request, 'revision_requested', fn (): Notification => Notification::make()->warning()
            ->title('Request Sourcing perlu diperbaiki')
            ->body("{$request->request_number}: {$request->last_decision_reason}"));
    }

    public function rejected(MaterialSourcingRequest $request): void
    {
        $this->notifyRequester($request, 'rejected', fn (): Notification => Notification::make()->danger()
            ->title('Request Sourcing ditolak')
            ->body("{$request->request_number}: {$request->last_decision_reason}"));
    }

    public function converted(MaterialSourcingRequest $request): void
    {
        $this->notifyRequester($request, 'converted', fn (): Notification => Notification::make()->success()
            ->title('Request Sourcing diproses menjadi sourcing')
            ->body("{$request->request_number} · {$request->product_name} sedang dicarikan supplier."));
    }

    /** @param \Closure(): Notification $notification */
    private function notifyRequester(MaterialSourcingRequest $request, string $event, \Closure $notification): void
    {
        $this->afterCommit($request, $event, function () use ($request, $notification): void {
            $requester = User::query()->whereKey($request->requester_id)->where('is_active', true)->get();
            if ($requester->isEmpty()) {
                return;
            }

            $notification()
                ->actions([Action::make('open')->label('Lihat Riwayat')->url(MaterialSourcingRequestHistoryPage::getUrl(panel: 'casual'))])
                ->sendToDatabase($requester);
        });
    }

    private function afterCommit(MaterialSourcingRequest $request, string $event, \Closure $send): void
    {
        DB::afterCommit(function () use ($request, $event, $send): void {
            try {
                $send();
            } catch (Throwable $exception) {
                Log::warning('Material sourcing request notification failed.', ['request' => $request->id, 'event' => $event, 'exception' => $exception::class]);
            }
        });
    }
}
