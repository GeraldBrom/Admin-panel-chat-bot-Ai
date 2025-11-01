<?php

namespace App\Http\Controllers;

use App\Services\DialogService;
use App\Services\GreenApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GreenApiWebhookController extends Controller
{
    public function __construct(
        private DialogService $dialogService
    ) {}

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

        // Синхронная обработка сообщений
        try {
            $processed = 0;

            if (isset($payload['messages']) && is_array($payload['messages'])) {
                foreach ($payload['messages'] as $message) {
                    $normalized = $this->normalizeMessage(is_array($message) ? $message : []);
                    if (!$normalized) {
                        continue;
                    }
                    $this->dialogService->processIncomingMessage(
                        $normalized['chatId'],
                        $normalized['messageText'],
                        $normalized['meta']
                    );
                    $processed++;
                }
            } else {
                $message = $payload['message'] ?? $payload['body'] ?? $payload;
                $normalized = $this->normalizeMessage(is_array($message) ? $message : []);
                if ($normalized) {
                    $this->dialogService->processIncomingMessage(
                        $normalized['chatId'],
                        $normalized['messageText'],
                        $normalized['meta']
                    );
                    $processed++;
                }
            }

            Log::info('[GreenAPI Webhook] Processed messages', [
                'processed' => $processed,
            ]);

            return response()->json([
                'status' => 'ok',
                'processed' => $processed,
                'received_at' => now()->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            Log::error('[GreenAPI Webhook] Failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $payload,
            ]);

            return response()->json([
                'status' => 'error',
                'error' => $e->getMessage(),
            ], 500);
        }
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
     *
     * @param array<mixed> $message
     * @return array{chatId:string,messageText:string,meta:array<mixed>}|null
     */
    private function normalizeMessage(array $message = null): ?array
    {
        if (!$message) {
            return null;
        }

        $chatId = $message['chatId']
            ?? ($message['senderData']['chatId'] ?? null);

        $messageText = $message['textMessage']
            ?? ($message['messageData']['textMessageData']['textMessage'] ?? null);

        if (!$chatId || !$messageText) {
            return null;
        }

        $meta = [
            'messageId' => $message['idMessage'] ?? null,
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


