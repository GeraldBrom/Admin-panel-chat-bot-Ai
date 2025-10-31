<?php

namespace App\Http\Controllers;

use App\Services\DialogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GreenApiWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();

        Log::info('[GreenAPI Webhook] Получен webhook', [
            'has_messages' => isset($payload['messages']),
            'has_message' => isset($payload['message']) || isset($payload['body']),
            'typeWebhook' => $payload['typeWebhook'] ?? null,
        ]);

        $processed = 0;

        // Вариант 1: массив сообщений
        if (isset($payload['messages']) && is_array($payload['messages'])) {
            foreach ($payload['messages'] as $message) {
                $normalized = $this->normalizeMessage($message);
                if (!$normalized) {
                    continue;
                }
                app(DialogService::class)->processIncomingMessage(
                    $normalized['chatId'],
                    $normalized['messageText'],
                    $normalized['meta']
                );
                $processed++;
            }
        } else {
            // Вариант 2: одиночное уведомление (ReceiveNotification)
            $message = $payload['message'] ?? $payload['body'] ?? $payload;
            $normalized = $this->normalizeMessage($message);
            if ($normalized) {
                app(DialogService::class)->processIncomingMessage(
                    $normalized['chatId'],
                    $normalized['messageText'],
                    $normalized['meta']
                );
                $processed++;
            }
        }

        return response()->json([
            'status' => 'ok',
            'processed' => $processed,
        ]);
    }

    /**
     * Приводит формат Green API к общему виду { chatId, messageText, meta }
     */
    private function normalizeMessage(array $message = null): ?array
    {
        if (!$message) {
            return null;
        }

        // Популярные поля из Green API (ReceiveNotification / messages)
        $chatId = $message['chatId']
            ?? ($message['senderData']['chatId'] ?? null);

        // textMessage может быть в разных уровнях
        $messageText = $message['textMessage']
            ?? ($message['messageData']['textMessageData']['textMessage'] ?? null);

        if (!$chatId || !$messageText) {
            return null;
        }

        $meta = [
            'messageId' => $message['idMessage'] ?? ($message['idMessage'] ?? null),
            'timestamp' => $message['timestamp'] ?? null,
            'typeMessage' => $message['typeMessage'] ?? ($message['messageData']['typeMessage'] ?? null),
            'raw' => $message,
        ];

        return [
            'chatId' => $chatId,
            'messageText' => $messageText,
            'meta' => $meta,
        ];
    }
}


