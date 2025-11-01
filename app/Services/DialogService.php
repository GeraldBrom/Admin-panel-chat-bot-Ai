<?php

namespace App\Services;

use App\Models\Dialog;
use App\Models\Message;
use App\Models\BotSession;
use App\Models\BotConfig;
use Illuminate\Support\Facades\Log;

class DialogService
{
    private const STATE_INITIAL = 'initial';
    private const STATE_ACTIVE = 'active';
    private const STATE_COMPLETED = 'completed';

    public function __construct(
        private OpenAIService $openAIService,
        private GreenApiService $greenApiService,
        private RemoteDatabaseService $remoteDbService
    ) {}

    /**
     * Инициализация диалога с клиентом
     */
    public function initializeDialog(string $chatId, int $objectId, ?int $botConfigId = null): void
    {
        Log::info("Инициализация диалога для chatId: {$chatId}, objectId: {$objectId}, configId: {$botConfigId}");

        // Конфигурация: если явно не передана, используем последнюю для whatsapp
        $config = $botConfigId ? BotConfig::find($botConfigId) : null;
        if (!$config) {
            $config = BotConfig::forPlatform('whatsapp')->orderByDesc('id')->first();
            $botConfigId = $config?->id;
        }

        // Получить или создать сессию бота; если уже была сессия, принудительно переводим в running
        $session = BotSession::firstOrCreate(
            [
                'chat_id' => $chatId,
                'platform' => 'whatsapp',
            ],
            [
                'object_id' => $objectId,
                'bot_config_id' => $botConfigId,
                'status' => 'running',
                'dialog_state' => ['state' => self::STATE_INITIAL],
                'started_at' => now(),
            ]
        );

        // Обновляем статус и основные поля при повторном запуске
        $session->update([
            'object_id' => $objectId,
            'bot_config_id' => $botConfigId,
            'status' => 'running',
            'dialog_state' => ['state' => self::STATE_INITIAL],
            'started_at' => $session->started_at ?: now(),
            'stopped_at' => null,
        ]);

        // Получить или создать диалог
        $dialog = Dialog::getOrCreate($chatId);

        // Получить данные объекта из удаленной базы данных
        $objectData = $this->remoteDbService->getObjectData($objectId);

        if (!$objectData) {
            Log::error("Ошибка при получении данных объекта для objectId: {$objectId}");
            return;
        }

        // Очистка имени владельца
        $rawName = $objectData['owner_name'] ?? 'Клиент';
        $cleanedName = $this->extractOwnerName($rawName);

        // Подготовка переменных для шаблонов
        $vars = [
            'name' => $cleanedName,
            'owner_name' => $cleanedName,
            'owner_name_clean' => $cleanedName,
            'formattedAddDate' => $objectData['formattedAddDate'] ?? '',
            'objectCount' => $objectData['objectCount'] ?? '',
            'address' => $objectData['address'] ?? '',
        ];

        // Без сценария: отметить как активный и ждать ввода пользователя
        $session->update([
            'dialog_state' => ['state' => self::STATE_ACTIVE],
        ]);

        $dialog->update([
            'current_state' => self::STATE_ACTIVE,
        ]);

        Log::info("Диалог инициализирован для chatId: {$chatId}");

        // Отправка стартового сообщения непосредственно клиенту (без GPT обработки)
        try {
            $config = $botConfigId ? BotConfig::find($botConfigId) : null;

            // Используем kickoff_message из конфигурации или дефолтное значение (если нет, используем дефолтное)
            $kickoffMessage = $config?->kickoff_message 
                ?? "{owner_name_clean}, добрый день!\n\nЯ — ИИ-ассистент Capital Mars. Мы уже {objectCount} сдавали вашу квартиру на {address}. Видим, что объявление снова актуально — верно? Если да, готовы подключиться к сдаче.";
            
            // Рендеринг шаблона с переменными
            $renderedMessage = $this->renderTemplate($kickoffMessage, $vars);

            // Отправка непосредственно клиенту БЕЗ GPT обработки
            if (!empty(trim($renderedMessage))) {
                Log::info('Отправка стартового сообщения непосредственно клиенту', [
                    'chatId' => $chatId,
                    'message_length' => mb_strlen($renderedMessage),
                ]);
                
                $this->sendMessageWithDelay($chatId, $renderedMessage, 0);

                // Сохранение как сообщение помощника (без использования GPT токенов)
                Message::create([
                    'dialog_id' => $dialog->dialog_id,
                    'role' => 'assistant',
                    'content' => $renderedMessage,
                    'previous_response_id' => null,
                    'tokens_in' => 0,
                    'tokens_out' => 0,
                ]);
            } else {
                Log::warning('Стартовое сообщение пустое после рендеринга, используем fallback');
                $fallback = $this->renderTemplate(
                    "{name}, добрый день! Мы ранее работали по вашей квартире на {address}. Подскажите, вы снова её сдаёте?",
                    [
                        'name' => $vars['name'] ?? 'Добрый день',
                        'address' => $vars['address'] ?? '',
                    ]
                );
                $this->sendMessageWithDelay($chatId, $fallback, 0);
                Message::create([
                    'dialog_id' => $dialog->dialog_id,
                    'role' => 'assistant',
                    'content' => $fallback,
                    'previous_response_id' => null,
                    'tokens_in' => 0,
                    'tokens_out' => 0,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Ошибка при отправке стартового сообщения', [ 'error' => $e->getMessage() ]);
        }
    }

    /**
     * Обработка входящего сообщения
     */
    public function processIncomingMessage(string $chatId, string $messageText, array $meta = []): void
    {
        try {
            Log::info("Обработка входящего сообщения от chatId: {$chatId}", [
                'message' => $messageText,
            ]);

            // Получить сессию
            $session = BotSession::where('chat_id', $chatId)
                ->where('status', 'running')
                ->first();

            if (!$session) {
                Log::warning("Не найдена активная сессия для chatId: {$chatId}");
                return;
            }

            // Получить диалог
            $dialog = Dialog::getOrCreate($chatId);

            // Сохранить сообщение пользователя
            Message::create([
                'dialog_id' => $dialog->dialog_id,
                'role' => 'user',
                'content' => $messageText,
                'meta' => $meta,
            ]);

            // Создать историю для одного вызова LLM
            $config = $session->bot_config_id ? BotConfig::find($session->bot_config_id) : null;
            $systemPrompt = $config?->prompt ?? 'Ты - профессионал ИИ-ассистент компании Capital Mars. Отвечай кратко, по делу.';
            $temperature = $config?->temperature ? (float) $config->temperature : null;
            $maxTokens = $config?->max_tokens;
            $model = $config?->openai_model ?? 'gpt-5-2025-08-07';
            $serviceTier = $config?->openai_service_tier ?? 'flex';

            $historyMessages = Message::where('dialog_id', $dialog->dialog_id)
                ->orderBy('created_at')
                ->get(['role', 'content']);

            $history = $historyMessages->map(function ($m) {
                return [
                    'role' => $m->role,
                    'content' => $m->content,
                ];
            })->values()->all();

            // Собираем все vector store IDs
            $vectorIds = [];
            
            // Сначала добавляем из нового формата vector_stores
            if ($config && is_array($config->vector_stores)) {
                foreach ($config->vector_stores as $store) {
                    if (isset($store['id']) && !empty($store['id'])) {
                        $vectorIds[] = $store['id'];
                    }
                }
            }
            
            // Для обратной совместимости: если нет новых, используем старые поля
            if (empty($vectorIds)) {
                if ($config?->vector_store_id_main) {
                    $vectorIds[] = $config->vector_store_id_main;
                }
                if ($config?->vector_store_id_objections) {
                    $vectorIds[] = $config->vector_store_id_objections;
                }
            }

            // Используем Responses API с RAG, если настроены vector stores
            $startTime = microtime(true);
            if (!empty($vectorIds)) {
                $result = $this->openAIService->chatWithRag(
                    $systemPrompt,
                    $history,
                    $temperature,
                    $maxTokens,
                    $vectorIds,
                    $model,
                    $serviceTier
                );
            } else {
                $result = $this->openAIService->chat(
                    $systemPrompt,
                    $history,
                    $temperature,
                    $maxTokens,
                    null,
                    null,
                    $model,
                    $serviceTier
                );
            }
            $elapsedTime = round((microtime(true) - $startTime) * 1000); // ms
            
            $assistantReply = $result['content'] ?? '';
            $responseId = $result['response_id'] ?? null;
            $usage = $result['usage'] ?? ['prompt_tokens' => 0, 'completion_tokens' => 0];

            Log::info("OpenAI API вызов завершен", [
                'chatId' => $chatId,
                'elapsed_ms' => $elapsedTime,
                'response_length' => mb_strlen($assistantReply),
                'tokens' => $usage,
            ]);

            if ($assistantReply !== '') {
                // Send via provider
                $this->sendMessageWithDelay($chatId, $assistantReply, 1200);

                // Save assistant message with previous_response_id
                Message::create([
                    'dialog_id' => $dialog->dialog_id,
                    'role' => 'assistant',
                    'content' => $assistantReply,
                    'previous_response_id' => $responseId,
                    'tokens_in' => $usage['prompt_tokens'] ?? 0,
                    'tokens_out' => $usage['completion_tokens'] ?? 0,
                ]);

                Log::info("Сообщение обработано и отправлено на chatId: {$chatId}", [
                    'response_length' => mb_strlen($assistantReply),
                    'tokens' => $usage,
                ]);
            } else {
                Log::warning("Пустой ответ помощника для chatId: {$chatId}");
            }
        } catch (\Throwable $e) {
            Log::error("Ошибка при обработке входящего сообщения для chatId: {$chatId}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'message' => $messageText,
            ]);
        }
    }

    /**
     * Завершение диалога
     */
    private function completeDialog(string $chatId, BotSession $session, Dialog $dialog): void
    {
        $session->update([
            'status' => 'completed',
            'stopped_at' => now(),
            'dialog_state' => ['state' => self::STATE_COMPLETED],
        ]);

        $dialog->update(['current_state' => self::STATE_COMPLETED]);

        Log::info("Диалог завершен для chatId: {$chatId}");
    }

    /**
     * Отправка сообщения с задержкой
     */
    private function sendMessageWithDelay(string $chatId, string $message, int $delayMs = 1500): void
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

    /**
     * Рендеринг {placeholders} в шаблоне с предоставленными переменными
     */
    private function renderTemplate(string $template, array $vars): string
    {
        $result = $template;
        foreach ($vars as $key => $value) {
            $result = str_replace('{' . $key . '}', (string) $value, $result);
        }
        return $result;
    }

    /**
     * Извлечение чистого имени владельца из raw строки с использованием эвристических правил
     */
    private function extractOwnerName(string $raw): string
    {
        $s = $raw;
        $s = preg_replace('/[\p{So}\p{Sk}]/u', '', $s) ?? $s; // emojis/symbols
        $s = preg_replace('/["\'\(\)\[\]<>]/u', ' ', $s) ?? $s;
        $s = preg_replace('/\b(собственник|собст\.?|соб\.?|владелец|агент|ооо|ип)\b/iu', ' ', $s) ?? $s;
        $s = preg_replace('/[+]?\d[\d\s\-()]{6,}/u', ' ', $s) ?? $s; // phones
        $s = preg_replace('/[\w.+-]+@\w+\.[\w.]+/u', ' ', $s) ?? $s; // emails
        $s = preg_replace('/\/.*/u', ' ', $s) ?? $s; // cut after /
        $s = preg_replace('/[,—-].*/u', ' ', $s) ?? $s; // cut after comma/dash
        $s = preg_replace('/\s+/u', ' ', trim((string)$s)) ?? trim((string)$s);

        if (preg_match('/\b[А-ЯЁ][а-яё]+(?:-[А-ЯЁ][а-яё]+)?\b/u', $s, $m)) {
            return $m[0];
        }
        // Fallback: title case first token if Cyrillic (fallback: заглавные буквы первого токена, если кириллический)
        if (preg_match('/^([А-Яа-яЁё]+(?:-[А-Яа-яЁё]+)?)/u', $s, $m)) {
            $name = mb_strtolower($m[1]);
            $parts = explode('-', $name);
            $parts = array_map(fn($p) => mb_strtoupper(mb_substr($p,0,1)) . mb_substr($p,1), $parts);
            return implode('-', $parts);
        }
        return 'Добрый день';
    }
}

