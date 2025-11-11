<?php

namespace App\Services\Dialogs;

use App\Models\BotSession;
use App\Models\BotConfig;
use App\Models\Dialog;
use App\Models\Message;
use App\Services\OpenAIService;
use App\Services\Messaging\MessageFormatter;
use App\Services\Messaging\MessageSender;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class MessageBufferService
{
    private const MESSAGE_BUFFER_DELAY = 8;

    public function __construct(
        private OpenAIService $openAIService,
        private MessageFormatter $messageFormatter,
        private MessageSender $messageSender,
        private DialogSummaryService $dialogSummaryService,
    ) {}

    /**
     * Добавление сообщения в буфер и планирование обработки
     */
    public function bufferMessage(string $chatId, int $messageId): void
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
    public function processBufferedMessages(string $chatId): void
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
            
            // Добавляем контекст из metadata (цена, адрес и т.д.)
            $metadata = $session->metadata ?? [];
            if (!empty($metadata)) {
                $contextInfo = "\n\n=== КОНТЕКСТ ОБЪЕКТА ===\n";
                
                // Передаем уже извлеченное ИИ имя для использования в обращениях
                if (!empty($metadata['owner_name_clean'])) {
                    $contextInfo .= "Имя клиента: {$metadata['owner_name_clean']}\n";
                    $contextInfo .= "ВАЖНО: Используй это имя для обращения к клиенту (например: '{$metadata['owner_name_clean']}, ...').\n";
                } elseif (!empty($metadata['owner_name_raw'])) {
                    $contextInfo .= "Имя клиента в БД (сырое): \"{$metadata['owner_name_raw']}\"\n";
                    $contextInfo .= "ВАЖНО: Извлеки из этой строки чистое имя по правилам из промпта и используй его для обращения.\n";
                } else {
                    $contextInfo .= "Имя клиента: не указано, используй нейтральное обращение без имени\n";
                }
                
                if (!empty($metadata['address'])) {
                    $contextInfo .= "Адрес: {$metadata['address']}\n";
                }
                if (!empty($metadata['price'])) {
                    $contextInfo .= "Цена аренды: {$metadata['price']} руб/мес\n";
                }
                if (!empty($metadata['formatted_price'])) {
                    $contextInfo .= "Цена (форматированная): {$metadata['formatted_price']} руб/мес\n";
                }
                if (!empty($metadata['commission_client'])) {
                    $contextInfo .= "Комиссия клиента: {$metadata['commission_client']}\n";
                }
                $contextInfo .= "=== КОНЕЦ КОНТЕКСТА ===\n";
                
                $systemPrompt .= $contextInfo;
            }
            
            $maxTokens = $config?->max_tokens;
            $model = $config?->openai_model ?? 'gpt-4o';
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
                    null,  // temperature не используется
                    $maxTokens,
                    $vectorIds,
                    $model,
                    $serviceTier  // Responses API поддерживает service_tier
                );
            } else {
                $result = $this->openAIService->chat(
                    $systemPrompt,
                    $history,
                    null,  // temperature не используется
                    $maxTokens,
                    null,
                    null,
                    $model
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
                // Конвертируем Markdown в WhatsApp форматирование
                $whatsappReply = $this->messageFormatter->convertMarkdownToWhatsApp($assistantReply);
                
                // Send via provider
                $this->messageSender->sendWithDelay($chatId, $whatsappReply, 1200);

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
                    $this->dialogSummaryService->generate($dialog);
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
}

