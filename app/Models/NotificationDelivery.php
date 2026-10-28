<?php

namespace App\Models;

use App\Enums\NotificationDeliveryStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * Delivery log of one external notification (§19.5). Stores a destination key, never a token or
 * chat ID.
 */
class NotificationDelivery extends Model
{
    public const CHANNEL_TELEGRAM = 'telegram';

    protected $fillable = [
        'channel',
        'event_type',
        'aggregate_type',
        'aggregate_id',
        'destination_key',
        'idempotency_key',
        'status',
        'attempts',
        'message',
        'last_attempt_at',
        'sent_at',
        'error_summary',
    ];

    protected function casts(): array
    {
        return [
            'status' => NotificationDeliveryStatus::class,
            'aggregate_id' => 'integer',
            'attempts' => 'integer',
            'last_attempt_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }
}
