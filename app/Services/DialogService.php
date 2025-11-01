<?php

namespace App\Services;

use App\Models\Dialog;
use App\Models\Message;
use App\Models\BotSession;
use App\Models\BotConfig;
use App\Models\Fact;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class DialogService
{
    private const STATE_INITIAL = 'initial';
    private const STATE_ACTIVE = 'active';
    private const STATE_COMPLETED = 'completed';
    
    // Задержка перед обработкой сообщений (в секундах)
    // Если за это время приходят новые сообщения, они накапливаются
    private const MESSAGE_BUFFER_DELAY = 8;

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

        // Получить диалог
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

        // Подготовка метаданных для сессии и диалога
        $metadata = [
            'object_id' => $objectId,
            'owner_name' => $cleanedName,
            'address' => $objectData['address'] ?? '',
            'object_count' => $objectData['objectCount'] ?? '',
            'add_date' => $objectData['formattedAddDate'] ?? '',
            'phone' => $objectData['phone'] ?? '',
            'email' => $objectData['email'] ?? '',
            'initialized_at' => now()->toIso8601String(),
            'bot_config_id' => $botConfigId,
            'platform' => 'whatsapp',
        ];

        // Обновляем статус и основные поля при повторном запуске (включая metadata)
        $session->update([
            'object_id' => $objectId,
            'bot_config_id' => $botConfigId,
            'status' => 'running',
            'dialog_state' => ['state' => self::STATE_ACTIVE],
            'metadata' => $metadata,
            'started_at' => $session->started_at ?: now(),
            'stopped_at' => null,
        ]);

        // Обновляем диалог с теми же метаданными
        $dialog->update([
            'current_state' => self::STATE_ACTIVE,
            'metadata' => $metadata,
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
     * Обработка входящего сообщения с буферизацией
     */
    public function processIncomingMessage(string $chatId, string $messageText, array $meta = []): void
    {
        try {
            Log::info("📨 Получено сообщение от chatId: {$chatId}", [
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
            $userMessage = Message::create([
                'dialog_id' => $dialog->dialog_id,
                'role' => 'user',
                'content' => $messageText,
                'meta' => $meta,
            ]);

            // Извлекаем факты из сообщения пользователя
            $this->extractFactsFromMessage($dialog, $userMessage);
            
            // Добавляем сообщение в буфер
            $this->bufferMessage($chatId, $userMessage->id);
            
            Log::info("✅ Сообщение добавлено в буфер для chatId: {$chatId}");
        } catch (\Throwable $e) {
            Log::error("Ошибка при буферизации сообщения для chatId: {$chatId}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'message' => $messageText,
            ]);
        }
    }
    
    /**
     * Добавление сообщения в буфер и планирование обработки
     */
    private function bufferMessage(string $chatId, int $messageId): void
    {
        $bufferKey = "message_buffer_{$chatId}";
        $processingKey = "processing_scheduled_{$chatId}";
        
        // Получаем текущий буфер сообщений
        $buffer = Cache::get($bufferKey, []);
        $buffer[] = $messageId;
        
        // Сохраняем буфер на 60 секунд
        Cache::put($bufferKey, $buffer, 60);
        
        Log::info("📦 Буфер обновлен", [
            'chatId' => $chatId,
            'buffer_size' => count($buffer),
            'message_ids' => $buffer,
        ]);
        
        // Проверяем, запланирована ли уже обработка
        if (!Cache::has($processingKey)) {
            // Планируем обработку через MESSAGE_BUFFER_DELAY секунд
            Cache::put($processingKey, true, self::MESSAGE_BUFFER_DELAY);
            
            Log::info("⏱️ Запланирована обработка буфера через " . self::MESSAGE_BUFFER_DELAY . " секунд", [
                'chatId' => $chatId,
            ]);
            
            // Запускаем отложенную обработку
            dispatch(function () use ($chatId) {
                sleep(self::MESSAGE_BUFFER_DELAY);
                $this->processBufferedMessages($chatId);
            })->afterResponse();
        } else {
            Log::info("⏳ Обработка уже запланирована, сообщение добавлено в буфер", [
                'chatId' => $chatId,
            ]);
        }
    }
    
    /**
     * Обработка всех накопленных сообщений из буфера
     */
    private function processBufferedMessages(string $chatId): void
    {
        try {
            $bufferKey = "message_buffer_{$chatId}";
            $processingKey = "processing_scheduled_{$chatId}";
            
            // Получаем буфер и очищаем его
            $messageIds = Cache::pull($bufferKey, []);
            Cache::forget($processingKey);
            
            if (empty($messageIds)) {
                Log::info("🔍 Буфер пуст для chatId: {$chatId}");
                return;
            }
            
            Log::info("🚀 Начинаем обработку буфера", [
                'chatId' => $chatId,
                'messages_count' => count($messageIds),
                'message_ids' => $messageIds,
            ]);
            
            // Получаем сессию и диалог
            $session = BotSession::where('chat_id', $chatId)
                ->where('status', 'running')
                ->first();

            if (!$session) {
                Log::warning("Сессия не найдена или остановлена для chatId: {$chatId}");
                return;
            }

            $dialog = Dialog::getOrCreate($chatId);

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

            // Собираем все vector store IDs из конфигурации для RAG (Retrieval-Augmented Generation)
            // OpenAI File Search будет автоматически искать релевантные документы в этих базах знаний
            $vectorIds = [];
            
            if ($config && is_array($config->vector_stores)) {
                foreach ($config->vector_stores as $store) {
                    if (isset($store['id']) && !empty($store['id'])) {
                        $vectorIds[] = $store['id'];
                    }
                }
            }

            Log::info("🗂️ Подготовка к вызову OpenAI", [
                'chatId' => $chatId,
                'model' => $model,
                'temperature' => $temperature,
                'max_tokens' => $maxTokens,
                'service_tier' => $serviceTier,
                'vector_stores_count' => count($vectorIds),
                'vector_store_ids' => $vectorIds,
                'using_rag' => !empty($vectorIds),
            ]);

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
                    $serviceTier  // Responses API поддерживает service_tier
                );
            } else {
                $result = $this->openAIService->chat(
                    $systemPrompt,
                    $history,
                    $temperature,
                    $maxTokens,
                    null,
                    null,
                    $model
                    // chat/completions не поддерживает service_tier, поэтому не передаем
                );
            }
            $elapsedTime = round((microtime(true) - $startTime) * 1000); // ms
            
            $assistantReply = $result['content'] ?? '';
            $responseId = $result['response_id'] ?? null;
            $usage = $result['usage'] ?? ['prompt_tokens' => 0, 'completion_tokens' => 0];

            Log::info("🤖 OpenAI API вызов завершен", [
                'chatId' => $chatId,
                'elapsed_ms' => $elapsedTime,
                'response_length' => mb_strlen($assistantReply),
                'tokens' => $usage,
                'buffered_messages' => count($messageIds),
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

                // Обновляем provider_conversation_id с последним response_id
                if ($responseId) {
                    $dialog->update([
                        'provider_conversation_id' => $responseId,
                    ]);
                }

                // Автоматическое обновление summary после каждых 5 сообщений
                $messageCount = Message::where('dialog_id', $dialog->dialog_id)->count();
                if ($messageCount > 0 && $messageCount % 5 === 0) {
                    Log::info("📝 Генерация резюме диалога для chatId: {$chatId} (сообщений: {$messageCount})");
                    $this->generateDialogSummary($dialog);
                }

                Log::info("✅ Буфер обработан, ответ отправлен на chatId: {$chatId}", [
                    'response_length' => mb_strlen($assistantReply),
                    'tokens' => $usage,
                    'buffered_messages' => count($messageIds),
                ]);
            } else {
                Log::warning("⚠️ Пустой ответ помощника для chatId: {$chatId}");
            }
        } catch (\Throwable $e) {
            Log::error("❌ Ошибка при обработке буфера для chatId: {$chatId}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Финализация диалога при остановке бота
     * Вызывается когда бот останавливается - извлекает все факты и генерирует резюме
     */
    public function finalizeDialog(string $chatId): void
    {
        Log::info("🏁 Начинаем финализацию диалога для chatId: {$chatId}");

        try {
            // Получаем диалог
            $dialog = Dialog::where('client_id', $chatId)->orWhere('dialog_id', 'like', "%{$chatId}")->first();
            
            if (!$dialog) {
                Log::warning("Диалог не найден для chatId: {$chatId}");
                return;
            }

            // Получаем все сообщения пользователя для извлечения фактов
            $userMessages = Message::where('dialog_id', $dialog->dialog_id)
                ->where('role', 'user')
                ->get();

            Log::info("📨 Найдено сообщений пользователя для анализа", [
                'dialog_id' => $dialog->dialog_id,
                'messages_count' => $userMessages->count(),
            ]);

            // Извлекаем факты из каждого сообщения пользователя (если еще не извлечены)
            $factsExtracted = 0;
            foreach ($userMessages as $message) {
                // Проверяем, извлекались ли уже факты из этого сообщения
                $existingFacts = Fact::where('source_message_id', $message->id)->count();
                
                if ($existingFacts === 0) {
                    Log::info("🔍 Извлекаем факты из сообщения #{$message->id}");
                    $this->extractFactsFromMessage($dialog, $message);
                    $factsExtracted++;
                }
            }

            Log::info("✅ Факты извлечены из сообщений", [
                'dialog_id' => $dialog->dialog_id,
                'processed_messages' => $factsExtracted,
            ]);

            // Генерируем финальное резюме диалога (независимо от количества сообщений)
            if ($userMessages->count() > 0) {
                Log::info("📝 Генерируем финальное резюме диалога");
                $this->generateDialogSummary($dialog, true); // true = принудительная генерация
            }

            // Собираем статистику для metadata
            $totalFacts = Fact::where('dialog_id', $dialog->dialog_id)->count();
            $totalMessages = Message::where('dialog_id', $dialog->dialog_id)->count();
            $userMessagesCount = Message::where('dialog_id', $dialog->dialog_id)
                ->where('role', 'user')
                ->count();

            // Обновляем статус диалога и добавляем статистику в metadata
            $currentMetadata = $dialog->metadata ?? [];
            $dialog->update([
                'current_state' => self::STATE_COMPLETED,
                'metadata' => array_merge($currentMetadata, [
                    'finalized_at' => now()->toIso8601String(),
                    'total_messages' => $totalMessages,
                    'user_messages' => $userMessagesCount,
                    'total_facts' => $totalFacts,
                    'has_summary' => !empty($dialog->summary),
                ]),
            ]);

            // Обновляем metadata в сессии бота
            $session = BotSession::where('chat_id', $chatId)->first();
            if ($session) {
                $sessionMetadata = $session->metadata ?? [];
                $session->update([
                    'metadata' => array_merge($sessionMetadata, [
                        'finalized_at' => now()->toIso8601String(),
                        'total_messages' => $totalMessages,
                        'user_messages' => $userMessagesCount,
                        'total_facts' => $totalFacts,
                        'has_summary' => !empty($dialog->summary),
                    ]),
                ]);
            }

            Log::info("🎉 Диалог успешно финализирован", [
                'dialog_id' => $dialog->dialog_id,
                'total_facts' => $totalFacts,
                'total_messages' => $totalMessages,
                'has_summary' => !empty($dialog->summary),
            ]);

        } catch (\Exception $e) {
            Log::error("❌ Ошибка при финализации диалога", [
                'chatId' => $chatId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Завершение диалога (приватный метод для внутреннего использования)
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
     * Очистка активной сессии чата без удаления самой сессии
     * Удаляет все сообщения, чтобы начать новый диалог без контекста
     */
    public function clearSession(string $chatId): void
    {
        Log::info("🧹 Начинаем очистку сессии для chatId: {$chatId}");

        try {
            // Получаем диалог
            $dialog = Dialog::where('client_id', $chatId)
                ->orWhere('dialog_id', 'like', "%{$chatId}")
                ->first();
            
            if (!$dialog) {
                Log::warning("⚠️ Диалог не найден для chatId: {$chatId}");
                
                // Пытаемся найти все возможные диалоги для отладки
                $allDialogs = Dialog::where('client_id', 'like', "%{$chatId}%")
                    ->orWhere('dialog_id', 'like', "%{$chatId}%")
                    ->get(['dialog_id', 'client_id']);
                
                Log::info("📋 Найденные диалоги для отладки:", [
                    'search_chat_id' => $chatId,
                    'found_dialogs' => $allDialogs->toArray(),
                ]);
                
                return;
            }

            Log::info("📍 Диалог найден", [
                'dialog_id' => $dialog->dialog_id,
                'client_id' => $dialog->client_id,
            ]);

            // Подсчитываем количество сообщений ДО удаления
            $messagesCountBefore = Message::where('dialog_id', $dialog->dialog_id)->count();
            $factsCountBefore = Fact::where('dialog_id', $dialog->dialog_id)->count();
            
            Log::info("📊 Перед удалением", [
                'messages' => $messagesCountBefore,
                'facts' => $factsCountBefore,
            ]);

            // Удаляем все сообщения диалога
            $deletedMessagesCount = Message::where('dialog_id', $dialog->dialog_id)->delete();
            
            // Удаляем все факты диалога
            $deletedFactsCount = Fact::where('dialog_id', $dialog->dialog_id)->delete();
            
            // Проверяем количество ПОСЛЕ удаления
            $messagesCountAfter = Message::where('dialog_id', $dialog->dialog_id)->count();
            $factsCountAfter = Fact::where('dialog_id', $dialog->dialog_id)->count();

            Log::info("📊 После удаления", [
                'messages' => $messagesCountAfter,
                'facts' => $factsCountAfter,
            ]);

            // Очищаем summary и provider_conversation_id
            $dialog->update([
                'summary' => null,
                'provider_conversation_id' => null,
                'current_state' => self::STATE_INITIAL,
            ]);

            // Получаем сессию и обнуляем dialog_state
            $session = BotSession::where('chat_id', $chatId)->first();
            if ($session) {
                $session->update([
                    'dialog_state' => ['state' => self::STATE_INITIAL],
                ]);
            }

            // Очищаем кеш буфера сообщений
            $bufferKey = "message_buffer_{$chatId}";
            $processingKey = "processing_scheduled_{$chatId}";
            Cache::forget($bufferKey);
            Cache::forget($processingKey);

            Log::info("✅ Сессия успешно очищена", [
                'chatId' => $chatId,
                'dialog_id' => $dialog->dialog_id,
                'deleted_messages' => $deletedMessagesCount,
                'deleted_facts' => $deletedFactsCount,
                'messages_remaining' => $messagesCountAfter,
                'facts_remaining' => $factsCountAfter,
            ]);

        } catch (\Exception $e) {
            Log::error("❌ Ошибка при очистке сессии", [
                'chatId' => $chatId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
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
     * Извлечение ключевых фактов из сообщения пользователя
     */
    private function extractFactsFromMessage(Dialog $dialog, Message $message): void
    {
        try {
            // Извлекаем факты только из сообщений пользователя
            if ($message->role !== 'user') {
                return;
            }

            $messageText = $message->content;
            
            // Промпт для извлечения фактов
            $extractionPrompt = "Проанализируй следующее сообщение клиента и извлеки ключевые факты в формате JSON.\n\n"
                . "Извлекай только ЯВНО указанные факты о:\n"
                . "- Цене недвижимости (ключ: \"price\")\n"
                . "- Количестве комнат (ключ: \"rooms\")\n"
                . "- Площади (ключ: \"area\")\n"
                . "- Этаже (ключ: \"floor\")\n"
                . "- Адресе/районе (ключ: \"location\")\n"
                . "- Дате доступности (ключ: \"available_from\")\n"
                . "- Предпочтениях по арендаторам (ключ: \"tenant_preferences\")\n"
                . "- Контактных данных (ключ: \"contact_info\")\n"
                . "- Особых условиях (ключ: \"special_conditions\")\n\n"
                . "Верни ТОЛЬКО JSON массив объектов формата: [{\"key\": \"название_ключа\", \"value\": \"значение\", \"confidence\": число_от_0_до_1}]\n"
                . "Если фактов нет, верни пустой массив [].\n\n"
                . "Сообщение клиента: \"{$messageText}\"";

            // Используем OpenAI для извлечения фактов
            $result = $this->openAIService->chat(
                'Ты - помощник для извлечения структурированных фактов из текста. Отвечай ТОЛЬКО валидным JSON массивом.',
                [['role' => 'user', 'content' => $extractionPrompt]],
                0.1, // Очень низкая температура для точности
                300,
                null,
                null,
                'gpt-4o-mini'
            );

            $responseContent = trim($result['content'] ?? '');
            
            if (empty($responseContent)) {
                return;
            }

            // Очищаем ответ от markdown если есть
            $responseContent = preg_replace('/^```json\s*|\s*```$/s', '', $responseContent);
            $responseContent = trim($responseContent);

            // Парсим JSON
            $extractedFacts = json_decode($responseContent, true);

            if (!is_array($extractedFacts) || empty($extractedFacts)) {
                Log::info("Факты не найдены в сообщении", [
                    'dialog_id' => $dialog->dialog_id,
                    'message_id' => $message->id,
                ]);
                return;
            }

            // Сохраняем каждый факт
            $savedCount = 0;
            foreach ($extractedFacts as $fact) {
                if (!isset($fact['key'], $fact['value'])) {
                    continue;
                }

                // Проверяем, нет ли уже такого факта в диалоге
                $existingFact = Fact::where('dialog_id', $dialog->dialog_id)
                    ->where('key', $fact['key'])
                    ->first();

                $confidence = isset($fact['confidence']) ? (float) $fact['confidence'] : 1.00;
                $confidence = max(0.0, min(1.0, $confidence)); // Ограничиваем 0-1

                if ($existingFact) {
                    // Обновляем факт, если новая уверенность выше
                    if ($confidence >= $existingFact->confidence) {
                        $existingFact->update([
                            'value' => $fact['value'],
                            'source_message_id' => $message->id,
                            'confidence' => $confidence,
                            'discovered_at' => now(),
                        ]);
                        $savedCount++;
                    }
                } else {
                    // Создаем новый факт
                    Fact::create([
                        'dialog_id' => $dialog->dialog_id,
                        'key' => $fact['key'],
                        'value' => $fact['value'],
                        'source_message_id' => $message->id,
                        'confidence' => $confidence,
                        'discovered_at' => now(),
                    ]);
                    $savedCount++;
                }
            }

            if ($savedCount > 0) {
                Log::info("Извлечено и сохранено фактов", [
                    'dialog_id' => $dialog->dialog_id,
                    'message_id' => $message->id,
                    'facts_count' => $savedCount,
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Ошибка при извлечении фактов из сообщения", [
                'dialog_id' => $dialog->dialog_id,
                'message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Генерация краткого резюме диалога на основе истории сообщений
     * 
     * @param Dialog $dialog Диалог для которого генерируется резюме
     * @param bool $forceGenerate Принудительная генерация даже с малым количеством сообщений
     */
    private function generateDialogSummary(Dialog $dialog, bool $forceGenerate = false): void
    {
        try {
            // Получаем последние сообщения диалога
            $messages = Message::where('dialog_id', $dialog->dialog_id)
                ->orderBy('created_at', 'asc')
                ->get(['role', 'content']);

            // Если сообщений меньше 3 и не принудительная генерация, не генерируем summary
            if (!$forceGenerate && $messages->count() < 3) {
                return;
            }
            
            // При малом количестве сообщений проверяем минимум
            if ($messages->count() === 0) {
                Log::warning("Нет сообщений для генерации резюме", ['dialog_id' => $dialog->dialog_id]);
                return;
            }

            // Формируем контекст для summary
            $conversationText = $messages->map(function ($msg) {
                $roleLabel = $msg->role === 'user' ? 'Клиент' : 'Ассистент';
                return "{$roleLabel}: {$msg->content}";
            })->implode("\n");

            // Создаем промпт для генерации резюме
            $summaryPrompt = "Создай краткое резюме (2-3 предложения) следующего диалога между ассистентом Capital Mars и клиентом. Укажи основные темы, вопросы клиента и текущий статус обсуждения:\n\n{$conversationText}";

            // Используем OpenAI для генерации summary
            $result = $this->openAIService->chat(
                'Ты - помощник, который создает краткие резюме диалогов. Отвечай только кратким резюме.',
                [['role' => 'user', 'content' => $summaryPrompt]],
                0.3, // Низкая температура для более предсказуемых результатов
                200, // Максимум 200 токенов для summary
                null,
                null,
                'gpt-4o-mini'
                // chat/completions не поддерживает service_tier
            );

            $summary = trim($result['content'] ?? '');

            if ($summary !== '') {
                $dialog->update(['summary' => $summary]);
                Log::info("Резюме диалога обновлено для dialog_id: {$dialog->dialog_id}", [
                    'summary_length' => mb_strlen($summary),
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Ошибка при генерации резюме диалога для dialog_id: {$dialog->dialog_id}", [
                'error' => $e->getMessage(),
            ]);
        }
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

