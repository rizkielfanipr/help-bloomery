<?php

namespace App\Services\Notifications;

use App\Enums\NotificationDeliveryStatus;
use App\Jobs\SendNotificationDeliveryJob;
use App\Models\NotificationDelivery;
use App\Services\Telegram\TelegramClient;
use Illuminate\Database\Eloquent\Model;

/**
 * Records one external notification per idempotency key and queues its delivery after the
 * surrounding database transaction commits (§14.3). Calling it twice with the same key returns
 * the existing delivery and dispatches nothing; a rolled-back transaction leaves no row and no job.
 */
class NotificationDeliveryRecorder
{
    public function __construct(private readonly TelegramClient $telegram) {}

    public function telegram(string $eventType, Model $aggregate, string $destinationKey, string $idempotencyKey, string $message): NotificationDelivery
    {
        $configured = $this->telegram->isConfigured($destinationKey);

        $delivery = NotificationDelivery::query()->firstOrCreate(
            ['idempotency_key' => mb_substr($idempotencyKey, 0, 191)],
            [
                'channel' => NotificationDelivery::CHANNEL_TELEGRAM,
                'event_type' => $eventType,
                'aggregate_type' => $aggregate->getMorphClass(),
                'aggregate_id' => $aggregate->getKey(),
                'destination_key' => $destinationKey,
                'status' => $configured ? NotificationDeliveryStatus::Pending : NotificationDeliveryStatus::Skipped,
                'message' => $message,
                'error_summary' => $configured ? null : 'Telegram dinonaktifkan atau tujuan belum dikonfigurasi; tidak ada request yang dikirim.',
            ],
        );

        if ($delivery->wasRecentlyCreated && $configured) {
            SendNotificationDeliveryJob::dispatch($delivery->id)->afterCommit();
        }

        return $delivery;
    }

    /**
     * Controlled manual retry of a failed delivery; the same row (and idempotency key) is reused.
     */
    public function retry(NotificationDelivery $delivery): bool
    {
        if ($delivery->status !== NotificationDeliveryStatus::Failed || ! $this->telegram->isConfigured($delivery->destination_key)) {
            return false;
        }

        $delivery->forceFill(['status' => NotificationDeliveryStatus::Pending])->save();
        SendNotificationDeliveryJob::dispatch($delivery->id)->afterCommit();

        return true;
    }
}
