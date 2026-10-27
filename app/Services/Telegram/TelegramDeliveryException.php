<?php

namespace App\Services\Telegram;

use RuntimeException;

/**
 * Telegram send failure whose message never contains the bot token.
 */
class TelegramDeliveryException extends RuntimeException {}
