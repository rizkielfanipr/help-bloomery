<?php

namespace App\Jobs;

use App\Enums\NotificationDeliveryStatus;
use App\Models\NotificationDelivery;
use App\Services\Telegram\TelegramClient;
use App\Services\Telegram\TelegramDeliveryException;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Sends one recorded notification delivery. It receives only the delivery ID, re-reads the row,
 * and never sends a delivery that is already sent or skipped. A Telegram failure is recorded on
 * the delivery and retried with backoff (released, not thrown), so it never changes the business
 * workflow status or surfaces in the user's request.
 */
class SendNotificationDeliveryJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 30;

    public int $uniqueFor = 3600;

    public function __construct(public readonly int $deliveryId)
    {
        if ($queue = config('services.telegram.queue')) {
            $this->onQueue($queue);
        }
    }

    public function uniqueId(): string
    {
        return 'notification-delivery:'.$this->deliveryId;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300, 900, 1800];
    }

    public function handle(TelegramClient $telegram): void
    {
        $delivery = DB::transaction(function (): ?NotificationDelivery {
            $delivery = NotificationDelivery::query()->lockForUpdate()->find($this->deliveryId);
            if (! $delivery || $delivery->status !== NotificationDeliveryStatus::Pending) {
                return null;
            }

            $delivery->forceFill(['attempts' => $delivery->attempts + 1, 'last_attempt_at' => now()])->save();

            return $delivery;
        });

        if (! $delivery) {
            return;
        }

        try {
            $telegram->send($delivery->destination_key, (string) $delivery->message);
        } catch (TelegramDeliveryException $exception) {
            $finalAttempt = $this->job === null || $this->job instanceof SyncJob || $this->attempts() >= $this->tries;
            $delivery->forceFill([
                'error_summary' => mb_substr($exception->getMessage(), 0, 500),
                'status' => $finalAttempt ? NotificationDeliveryStatus::Failed : NotificationDeliveryStatus::Pending,
            ])->save();

            if (! $finalAttempt) {
                $this->release($this->backoff()[min($this->attempts() - 1, count($this->backoff()) - 1)]);
            }

            return;
        }

        $delivery->forceFill(['status' => NotificationDeliveryStatus::Sent, 'sent_at' => now(), 'error_summary' => null])->save();
    }

    public function failed(?Throwable $exception): void
    {
        NotificationDelivery::query()->whereKey($this->deliveryId)
            ->where('status', NotificationDeliveryStatus::Pending->value)
            ->update(['status' => NotificationDeliveryStatus::Failed->value]);
    }
}
