<?php

namespace App\Services\Messaging;

use App\Services\GreenApiService;
use Illuminate\Support\Facades\Log;

class MessageSender
{
    public function __construct(
        private GreenApiService $greenApiService
    ) {}

    /**
     * Отправка сообщения с задержкой
     */
    public function sendWithDelay(string $chatId, string $message, int $delayMs = 1500): void
    {
        if ($delayMs > 0) {
            usleep($delayMs * 1000);
        }

        try {
            $this->greenApiService->sendMessage($chatId, $message);

            Log::info("Сообщение отправлено на chatId: {$chatId}", [
                'message' => substr($message, 0, 50) . '...',
            ]);
        } catch (\Exception $e) {
            Log::error("Ошибка при отправке сообщения на chatId: {$chatId}", [
                'error' => $e->getMessage(),
            ]);
        }
    }
}

