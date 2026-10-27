<?php

namespace App\Services\Telegram;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Thin Telegram Bot API client (sendMessage only). It knows nothing about when a message should
 * be sent; callers decide. Errors are re-thrown without the bot token, which is part of the URL.
 */
class TelegramClient
{
    public function isConfigured(string $destinationKey): bool
    {
        return (bool) config('services.telegram.enabled')
            && filled(config('services.telegram.bot_token'))
            && filled(config("services.telegram.chats.{$destinationKey}"));
    }

    /**
     * @throws TelegramDeliveryException
     */
    public function send(string $destinationKey, string $text): void
    {
        $token = (string) config('services.telegram.bot_token');
        $chatId = (string) config("services.telegram.chats.{$destinationKey}");
        if (! $this->isConfigured($destinationKey)) {
            throw new TelegramDeliveryException("Telegram tujuan {$destinationKey} belum dikonfigurasi.");
        }

        $timeout = max(1, (int) config('services.telegram.timeout', 10));

        try {
            $response = Http::baseUrl(rtrim((string) config('services.telegram.base_url'), '/'))
                ->acceptJson()
                ->asJson()
                ->connectTimeout(min(5, $timeout))
                ->timeout($timeout)
                ->post("/bot{$token}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $text,
                    'disable_web_page_preview' => true,
                ]);
        } catch (Throwable $exception) {
            throw new TelegramDeliveryException($this->redact($exception->getMessage(), $token, $chatId), previous: null);
        }

        if (! $response->successful() || $response->json('ok') !== true) {
            $description = (string) ($response->json('description') ?? 'HTTP '.$response->status());

            throw new TelegramDeliveryException('Telegram menolak pesan: '.$this->redact($description, $token, $chatId));
        }
    }

    private function redact(string $message, string $token, string $chatId): string
    {
        $message = str_replace(array_filter([$token, $chatId]), '[REDACTED]', $message);

        return mb_strimwidth((string) preg_replace('#/bot[^/\s]+/#', '/bot[REDACTED]/', $message), 0, 450, '…');
    }
}
