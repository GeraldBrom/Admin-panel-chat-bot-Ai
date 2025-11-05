<?php

namespace App\Http\Controllers;

use App\Services\DialogService;
use App\Services\GreenApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

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

        // Игнорируем исходящие сообщения от бота (чтобы бот не разговаривал сам с собой)
        $typeWebhook = $payload['typeWebhook'] ?? null;
        if (in_array($typeWebhook, ['outgoingMessageStatus', 'outgoingAPIMessageReceived'], true)) {
            Log::debug('[GreenAPI Webhook] Пропускаем исходящее сообщение', [
                'typeWebhook' => $typeWebhook,
                'chatId' => $payload['chatId'] ?? $payload['senderData']['chatId'] ?? 'unknown',
            ]);
            
            return response()->json([
                'status' => 'ok',
                'processed' => 0,
                'message' => 'Outgoing message ignored',
                'received_at' => now()->toIso8601String(),
            ]);
        }

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
                    
                    // Защита от дублирования: проверяем messageId
                    $messageId = $normalized['meta']['messageId'] ?? null;
                    if ($messageId) {
                        $cacheKey = "processed_message_{$messageId}";
                        if (Cache::has($cacheKey)) {
                            Log::info('[GreenAPI Webhook] Сообщение уже обработано, пропускаем', [
                                'messageId' => $messageId,
                                'chatId' => $normalized['chatId'],
                            ]);
                            continue;
                        }
                        // Помечаем сообщение как обработанное на 1 час
                        Cache::put($cacheKey, true, 3600);
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
                    // Защита от дублирования: проверяем messageId
                    $messageId = $normalized['meta']['messageId'] ?? null;
                    if ($messageId) {
                        $cacheKey = "processed_message_{$messageId}";
                        if (Cache::has($cacheKey)) {
                            Log::info('[GreenAPI Webhook] Сообщение уже обработано, пропускаем', [
                                'messageId' => $messageId,
                                'chatId' => $normalized['chatId'],
                            ]);
                            return response()->json([
                                'status' => 'ok',
                                'processed' => 0,
                                'message' => 'Already processed',
                                'received_at' => now()->toIso8601String(),
                            ]);
                        }
                        // Помечаем сообщение как обработанное на 1 час
                        Cache::put($cacheKey, true, 3600);
                    }
                    
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

        // Дополнительная защита: игнорируем сообщения от самого бота
        // sender - это отправитель сообщения, wid - это ID инстанса бота
        $sender = $message['senderData']['sender'] ?? null;
        $botWid = $message['instanceData']['wid'] ?? null;
        
        if ($sender && $botWid && $sender === $botWid) {
            Log::debug('[GreenAPI Webhook] Пропускаем сообщение от самого бота', [
                'sender' => $sender,
                'botWid' => $botWid,
            ]);
            return null;
        }

        $chatId = $message['chatId']
            ?? ($message['senderData']['chatId'] ?? null);

        // Поддержка различных форматов текстовых сообщений от GreenAPI
        $messageText = null;
        
        // Формат 1: textMessage (простой текст)
        if (isset($message['textMessage'])) {
            $messageText = $message['textMessage'];
        }
        
        // Формат 2: messageData.textMessageData.textMessage
        elseif (isset($message['messageData']['textMessageData']['textMessage'])) {
            $messageText = $message['messageData']['textMessageData']['textMessage'];
        }
        
        // Формат 3: messageData.extendedTextMessageData.text (расширенные текстовые сообщения)
        elseif (isset($message['messageData']['extendedTextMessageData']['text'])) {
            $messageText = $message['messageData']['extendedTextMessageData']['text'];
        }

        if (!$chatId || !$messageText) {
            // Логируем нераспознанные форматы для отладки
            if (!$messageText && $chatId) {
                Log::warning('[GreenAPI Webhook] Не удалось извлечь текст сообщения', [
                    'chatId' => $chatId,
                    'typeMessage' => $message['messageData']['typeMessage'] ?? ($message['typeMessage'] ?? 'unknown'),
                    'available_paths' => [
                        'textMessage' => isset($message['textMessage']),
                        'messageData.textMessageData' => isset($message['messageData']['textMessageData']),
                        'messageData.extendedTextMessageData' => isset($message['messageData']['extendedTextMessageData']),
                    ],
                    'messageData_keys' => isset($message['messageData']) ? array_keys($message['messageData']) : [],
                ]);
            }
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


