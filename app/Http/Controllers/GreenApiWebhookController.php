<?php

namespace App\Http\Controllers;

use App\Services\DialogService;
use App\Services\GreenApiService;
use App\Jobs\ProcessGreenApiWebhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GreenApiWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();

        Log::info('[GreenAPI Webhook] Получен webhook', [
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'has_messages' => isset($payload['messages']),
            'has_message' => isset($payload['message']) || isset($payload['body']),
            'typeWebhook' => $payload['typeWebhook'] ?? null,
            'payload_keys' => array_keys($payload),
            'timestamp' => now()->toDateTimeString(),
        ]);

        // Детальное логирование для диагностики
        if (!empty($payload)) {
            Log::debug('[GreenAPI Webhook] Полный payload', [
                'payload' => $payload
            ]);
        } else {
            Log::warning('[GreenAPI Webhook] Получен пустой payload!');
        }

        // Асинхронная обработка после ответа (не блокируем 200 OK)
        ProcessGreenApiWebhook::dispatchAfterResponse($payload);

        return response()->json([
            'status' => 'ok',
            'queued' => true,
            'received_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Диагностический метод: получить последние входящие сообщения из GREEN-API
     */
    public function last(Request $request, GreenApiService $greenApiService): JsonResponse
    {
        $minutes = (int) ($request->query('minutes', 3));
        $minutes = $minutes > 0 ? $minutes : 1;
        $messages = $greenApiService->getLastIncomingMessages($minutes);

        return response()->json([
            'minutes' => $minutes,
            'count' => is_array($messages) ? count($messages) : 0,
            'sample' => array_slice($messages, 0, 3),
        ]);
    }

    /**
     * Тестовый endpoint для проверки работоспособности webhook
     */
    public function test(Request $request): JsonResponse
    {
        Log::info('[GreenAPI Webhook TEST] Получен тестовый запрос', [
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'headers' => $request->headers->all(),
            'payload' => $request->all(),
            'timestamp' => now()->toDateTimeString(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Webhook endpoint работает!',
            'received_at' => now()->toIso8601String(),
            'your_ip' => $request->ip(),
            'payload_received' => $request->all(),
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


