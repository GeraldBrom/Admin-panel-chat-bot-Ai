<?php

namespace App\Services;

use App\Models\Dialog;
use App\Models\Message;
use App\Models\Fact;
use App\Models\BotSession;
use App\Models\BotConfig;
use Illuminate\Support\Facades\Log;

class DialogService
{
    // Simplified dialog states (no scripted scenarios)
    private const STATE_INITIAL = 'initial';
    private const STATE_ACTIVE = 'active';
    private const STATE_COMPLETED = 'completed';

    public function __construct(
        private OpenAIService $openAIService,
        private GreenApiService $greenApiService,
        private RemoteDatabaseService $remoteDbService
    ) {}

    /**
     * Initialize dialog with client
     */
    public function initializeDialog(string $chatId, int $objectId, ?int $botConfigId = null): void
    {
        Log::info("Initializing dialog for chatId: {$chatId}, objectId: {$objectId}, configId: {$botConfigId}");

        // Конфигурация: если явно не передана, используем последнюю для whatsapp
        $config = $botConfigId ? BotConfig::find($botConfigId) : null;
        if (!$config) {
            $config = BotConfig::forPlatform('whatsapp')->orderByDesc('id')->first();
            $botConfigId = $config?->id;
        }

        // Get or create bot session; если уже была сессия, принудительно переводим в running
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

        // Get or create dialog
        $dialog = Dialog::getOrCreate($chatId);

        // Get object data from remote database
        $objectData = $this->remoteDbService->getObjectData($objectId);

        if (!$objectData) {
            Log::error("Failed to get object data for objectId: {$objectId}");
            return;
        }

        // Owner name cleanup
        $rawName = $objectData['ownerInfo'][0]['value'] ?? 'Клиент';
        $cleanedName = $this->extractOwnerName($rawName);

        // Prepare variables for templates
        $vars = [
            'name' => $cleanedName,
            'owner_name' => $cleanedName,
            'owner_name_clean' => $cleanedName,
            'formattedAddDate' => $objectData['formattedAddDate'] ?? '',
            'objectCount' => $objectData['objectCount'] ?? '',
            'address' => $objectData['objectInfo'][0]['address'] ?? '',
        ];

        // No scripted scenario: mark as active and wait for user input
        $session->update([
            'dialog_state' => ['state' => self::STATE_ACTIVE],
        ]);

        $dialog->update([
            'current_state' => self::STATE_ACTIVE,
        ]);

        Log::info("Dialog initialized for chatId: {$chatId}");

        // Generate and send the first assistant message via single LLM call
        try {
            $config = $botConfigId ? BotConfig::find($botConfigId) : null;
            $systemPrompt = $config?->prompt ?? 'Ты - профессионал ИИ-ассистент компании Capital Mars. Отвечай кратко, по делу.';
            $temperature = $config?->temperature ? (float) $config->temperature : null;
            $maxTokens = $config?->max_tokens;

            // Provide context as first user turn to steer the model for initial outreach
            $kickoffInstruction = "{owner_name_clean}, добрый день!\n\nЯ — ИИ-ассистент Capital Mars. Мы уже {objectCount} сдавали вашу квартиру на {address}. Видим, что объявление снова актуально — верно? Если да, готовы подключиться к сдаче.";
            $history = [
                [ 'role' => 'user', 'content' => $this->renderTemplate($kickoffInstruction, $vars) ],
            ];

            $vectorIds = [];
            if ($config?->vector_store_id_main) { $vectorIds[] = $config->vector_store_id_main; }
            if ($config?->vector_store_id_objections) { $vectorIds[] = $config->vector_store_id_objections; }

            if (!empty($vectorIds)) {
                $result = $this->openAIService->chatWithRag($systemPrompt, $history, $temperature, $maxTokens, $vectorIds);
            } else {
                $result = $this->openAIService->chat($systemPrompt, $history, $temperature, $maxTokens);
            }

            $assistantReply = $result['content'] ?? '';
            $responseId = $result['response_id'] ?? null;
            $usage = $result['usage'] ?? ['prompt_tokens' => 0, 'completion_tokens' => 0];

            if ($assistantReply !== '') {
                Log::info('Initial assistant reply generated', [
                    'len' => mb_strlen($assistantReply),
                    'response_id' => $responseId,
                    'tokens' => $usage,
                ]);
                $this->sendMessageWithDelay($chatId, $assistantReply, 0);

                Message::create([
                    'dialog_id' => $dialog->dialog_id,
                    'role' => 'assistant',
                    'content' => $assistantReply,
                    'previous_response_id' => $responseId,
                    'tokens_in' => $usage['prompt_tokens'] ?? 0,
                    'tokens_out' => $usage['completion_tokens'] ?? 0,
                ]);
            } else {
                Log::warning('Initial assistant reply is empty, using fallback template');
                $fallback = $this->renderTemplate(
                    "{name}, добрый день! Мы ранее работали по вашей квартире на {address}. Подскажите, вы снова её сдаёте? {commission_text}",
                    [
                        'name' => $vars['name'] ?? 'Добрый день',
                        'address' => $vars['address'] ?? '',
                        'commission_text' => 'Наша комиссия — 50% по факту заселения (как при прошлом сотрудничестве).',
                    ]
                );
                $this->sendMessageWithDelay($chatId, $fallback, 0);
                Message::create([
                    'dialog_id' => $dialog->dialog_id,
                    'role' => 'assistant',
                    'content' => $fallback,
                    'previous_response_id' => null,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to send initial assistant message', [ 'error' => $e->getMessage() ]);
        }
    }

    /**
     * Process incoming message
     */
    public function processIncomingMessage(string $chatId, string $messageText, array $meta = []): void
    {
        try {
            Log::info("Processing incoming message from chatId: {$chatId}", [
                'message' => $messageText,
            ]);

            // Get session
            $session = BotSession::where('chat_id', $chatId)
                ->where('status', 'running')
                ->first();

            if (!$session) {
                Log::warning("No active session for chatId: {$chatId}");
                return;
            }

            // Get dialog
            $dialog = Dialog::getOrCreate($chatId);

            // Save user message
            Message::create([
                'dialog_id' => $dialog->dialog_id,
                'role' => 'user',
                'content' => $messageText,
                'meta' => $meta,
            ]);

            // Build history for single LLM call
            $config = $session->bot_config_id ? BotConfig::find($session->bot_config_id) : null;
            $systemPrompt = $config?->prompt ?? 'Ты - профессионал ИИ-ассистент компании Capital Mars. Отвечай кратко, по делу.';
            $temperature = $config?->temperature ? (float) $config->temperature : null;
            $maxTokens = $config?->max_tokens;

            $historyMessages = Message::where('dialog_id', $dialog->dialog_id)
                ->orderBy('created_at')
                ->get(['role', 'content']);

            $history = $historyMessages->map(function ($m) {
                return [
                    'role' => $m->role,
                    'content' => $m->content,
                ];
            })->values()->all();

            $vectorIds = [];
            if ($config?->vector_store_id_main) {
                $vectorIds[] = $config->vector_store_id_main;
            }
            if ($config?->vector_store_id_objections) {
                $vectorIds[] = $config->vector_store_id_objections;
            }

            // Prefer Responses API with RAG if vector stores configured
            $startTime = microtime(true);
            if (!empty($vectorIds)) {
                $result = $this->openAIService->chatWithRag(
                    $systemPrompt,
                    $history,
                    $temperature,
                    $maxTokens,
                    $vectorIds
                );
            } else {
                $result = $this->openAIService->chat(
                    $systemPrompt,
                    $history,
                    $temperature,
                    $maxTokens
                );
            }
            $elapsedTime = round((microtime(true) - $startTime) * 1000); // ms
            
            $assistantReply = $result['content'] ?? '';
            $responseId = $result['response_id'] ?? null;
            $usage = $result['usage'] ?? ['prompt_tokens' => 0, 'completion_tokens' => 0];

            Log::info("OpenAI API call completed", [
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

                Log::info("Message processed and sent to chatId: {$chatId}", [
                    'response_length' => mb_strlen($assistantReply),
                    'tokens' => $usage,
                ]);
            } else {
                Log::warning("Empty assistant reply for chatId: {$chatId}");
            }
        } catch (\Throwable $e) {
            Log::error("Failed to process incoming message for chatId: {$chatId}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'message' => $messageText,
            ]);
        }
    }

    /**
     * Handle initial question response
     */
    private function handleInitialQuestionResponse(string $chatId, string $messageText, BotSession $session, Dialog $dialog): void
    {
        $config = $session->bot_config_id ? BotConfig::find($session->bot_config_id) : null;
        $prompts = $this->getConfigPrompts($config);
        $temperature = $config?->temperature ? (float) $config->temperature : null;
        $maxTokens = $config?->max_tokens;
        $isPositive = $this->openAIService->analyzeResponse($messageText, $prompts['analyze_response'] ?? null, $temperature, $maxTokens);

        if ($isPositive === null) {
            Log::info("Neutral response on initial question, waiting...");
            return;
        }

        if ($isPositive) {
            $objectData = $this->remoteDbService->getObjectData($session->object_id);
            $messages = $this->getConfigMessages($config);
            $vars = [
                'price' => $objectData['formattedPrice'] ?? '0',
            ];
            $tpl = $messages['price_confirmation_positive'] ?? 'Хорошо, спасибо за доверие. Пару моментов для актуализации информации. Стоимость квартиры {price} руб (с коммуналкой, но счетчики отдельно), верно?';
            $this->sendMessageWithDelay($chatId, $this->renderTemplate($tpl, $vars), 2000);

            $session->update(['dialog_state' => ['state' => self::STATE_PRICE_CONFIRMATION]]);
            $dialog->update(['current_state' => self::STATE_PRICE_CONFIRMATION]);
        } else {
            $messages = $this->getConfigMessages($config);
            $tpl = $messages['final_negative'] ?? 'Я вас понял, извините за беспокойство.';
            $this->sendMessageWithDelay($chatId, $tpl);
            $this->completeDialog($chatId, $session, $dialog);
        }
    }

    /**
     * Handle price confirmation response
     */
    private function handlePriceConfirmationResponse(string $chatId, string $messageText, BotSession $session, Dialog $dialog): void
    {
        $config = $session->bot_config_id ? BotConfig::find($session->bot_config_id) : null;
        $prompts = $this->getConfigPrompts($config);
        $temperature = $config?->temperature ? (float) $config->temperature : null;
        $maxTokens = $config?->max_tokens;
        $isPositive = $this->openAIService->analyzeResponse($messageText, $prompts['analyze_response'] ?? null, $temperature, $maxTokens);

        if ($isPositive === null) {
            return;
        }

        if ($isPositive) {
            $objectData = $this->remoteDbService->getObjectData($session->object_id);
            $messages = $this->getConfigMessages($config);
            $vars = [
                'commission' => $objectData['objectInfo'][0]['commission_client'] ?? '0',
            ];
            $tpl = $messages['commission_info_positive'] ?? 'На всякий случай проговариваю, что наша комиссия по факту заселения жильцов оплачиваемая вами {commission}% (как и при прошлом сотрудничестве). Тогда мы запускаем в рекламу, как будут первые звонки сразу свяжемся с вами.';
            $this->sendMessageWithDelay($chatId, $this->renderTemplate($tpl, $vars), 2000);

            $session->update(['dialog_state' => ['state' => self::STATE_COMMISSION_INFO]]);
            $dialog->update(['current_state' => self::STATE_COMMISSION_INFO]);
        } else {
            $messages = $this->getConfigMessages($config);
            $tpl = $messages['price_confirmation_negative'] ?? 'Понял вас. Подскажите, пожалуйста, какая цена актуальна на данный момент?';
            $this->sendMessageWithDelay($chatId, $tpl);
            $session->update(['dialog_state' => ['state' => self::STATE_PRICE_UPDATE]]);
            $dialog->update(['current_state' => self::STATE_PRICE_UPDATE]);
        }
    }

    /**
     * Handle price update response
     */
    private function handlePriceUpdateResponse(string $chatId, string $messageText, BotSession $session, Dialog $dialog): void
    {
        if ($this->containsNegativeIntent($messageText)) {
            $config = $session->bot_config_id ? BotConfig::find($session->bot_config_id) : null;
            $messages = $this->getConfigMessages($config);
            $tpl = $messages['final_negative'] ?? 'Понял вас. Спасибо за ваше время, если что-то изменится — будем рады сотрудничеству.';
            $this->sendMessageWithDelay($chatId, $tpl);
            $this->completeDialog($chatId, $session, $dialog);
            return;
        }

        $price = $this->extractPriceFromText($messageText);
        
        if (!$price) {
            $config = $session->bot_config_id ? BotConfig::find($session->bot_config_id) : null;
            $messages = $this->getConfigMessages($config);
            $tpl = $messages['price_update_invalid'] ?? 'Понял вас. Подскажите, пожалуйста, актуальную цену числом (например, 95000 руб)?';
            $this->sendMessageWithDelay($chatId, $tpl);
            return;
        }

        $objectData = $this->remoteDbService->getObjectData($session->object_id);
        $commission = $objectData['objectInfo'][0]['commission_client'] ?? '0';
        $formattedPrice = number_format((int) $price, 0, ',', ',');

        $config = $session->bot_config_id ? BotConfig::find($session->bot_config_id) : null;
        $messages = $this->getConfigMessages($config);
        $tpl = $messages['price_update_success'] ?? 'Понял вас, цена {price} руб. На всякий случай проговариваю, что наша комиссия по факту заселения жильцов оплачиваемая вами {commission}% (как и при прошлом сотрудничестве). Тогда мы запускаем в рекламу, как будут первые звонки сразу свяжемся с вами.';
        $this->sendMessageWithDelay($chatId, $this->renderTemplate($tpl, [ 'price' => $formattedPrice, 'commission' => $commission ]), 2000);

        $session->update(['dialog_state' => ['state' => self::STATE_COMMISSION_INFO]]);
        $dialog->update(['current_state' => self::STATE_COMMISSION_INFO]);
    }

    /**
     * Handle commission info response
     */
    private function handleCommissionInfoResponse(string $chatId, string $messageText, BotSession $session, Dialog $dialog): void
    {
        $config = $session->bot_config_id ? BotConfig::find($session->bot_config_id) : null;
        $prompts = $this->getConfigPrompts($config);
        $temperature = $config?->temperature ? (float) $config->temperature : null;
        $maxTokens = $config?->max_tokens;
        $isPositive = $this->openAIService->analyzeResponse($messageText, $prompts['analyze_response'] ?? null, $temperature, $maxTokens);

        if ($isPositive === null) {
            return;
        }

        if ($isPositive) {
            $messages = $this->getConfigMessages($config);
            $tpl = $messages['final_success'] ?? 'Отлично! Благодарим за доверие. Мы свяжемся с вами, как только появятся первые заинтересованные клиенты. Хорошего дня!';
            $this->sendMessageWithDelay($chatId, $tpl, 2000);
        } else {
            $messages = $this->getConfigMessages($config);
            $tpl = $messages['final_negative'] ?? 'Понял вас. Спасибо за ваше время, если что-то изменится — будем рады сотрудничеству.';
            $this->sendMessageWithDelay($chatId, $tpl);
        }

        $this->completeDialog($chatId, $session, $dialog);
    }

    /**
     * Complete dialog
     */
    private function completeDialog(string $chatId, BotSession $session, Dialog $dialog): void
    {
        $session->update([
            'status' => 'completed',
            'stopped_at' => now(),
            'dialog_state' => ['state' => self::STATE_COMPLETED],
        ]);

        $dialog->update(['current_state' => self::STATE_COMPLETED]);

        Log::info("Dialog completed for chatId: {$chatId}");
    }

    /**
     * Check for negative intent
     */
    private function containsNegativeIntent(string $text): bool
    {
        $phrases = [
            'я против', 'не давал', 'не разреш', 'не надо', 'не хочу', 'стоп', 'нет', 'не соглас',
            'прекратите', 'остановите', 'не пишите', 'не беспокойте'
        ];

        $lowerText = mb_strtolower($text);
        foreach ($phrases as $phrase) {
            if (str_contains($lowerText, $phrase)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check for pause intent
     */
    private function containsPauseIntent(string $text): bool
    {
        $phrases = ['погодите', 'подождите', 'минутку', 'секунду', 'сейчас не'];
        $lowerText = mb_strtolower($text);

        foreach ($phrases as $phrase) {
            if (str_contains($lowerText, $phrase)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Handle negative intent
     */
    private function handleNegativeIntent(string $chatId, Dialog $dialog, BotSession $session): void
    {
        $config = $session->bot_config_id ? BotConfig::find($session->bot_config_id) : null;
        $messages = $this->getConfigMessages($config);
        $tpl = $messages['final_negative'] ?? 'Понял вас. Спасибо за ваше время, если что-то изменится — будем рады сотрудничеству.';
        $this->sendMessageWithDelay($chatId, $tpl);

        $this->completeDialog($chatId, $session, $dialog);
    }

    /**
     * Extract price from text
     */
    private function extractPriceFromText(string $text): ?string
    {
        $normalized = mb_strtolower($text);
        $normalized = preg_replace('/[\s\u00A0]/u', ' ', $normalized);
        $normalized = preg_replace('/руб\.?/u', '', $normalized);
        $normalized = trim($normalized);

        // Match "95k" or "95к"
        if (preg_match('/(\d+[\s.,]?\d*)\s*[kк]/u', $normalized, $matches)) {
            $num = preg_replace('/[^\d]/', '', $matches[1]);
            if (!empty($num)) {
                return (string) ($num * 1000);
            }
        }

        // Match numbers with thousands separators
        if (preg_match('/\d{1,3}([\s.,]?\d{3})+|\d+/u', $normalized, $matches)) {
            $digits = preg_replace('/[^\d]/', '', $matches[0]);
            if (!empty($digits)) {
                return $digits;
            }
        }

        return null;
    }

    /**
     * Send message with delay
     */
    private function sendMessageWithDelay(string $chatId, string $message, int $delayMs = 1500): void
    {
        if ($delayMs > 0) {
            usleep($delayMs * 1000);
        }

        try {
            $this->greenApiService->sendMessage($chatId, $message);
            
            // Persisting assistant messages is handled by caller in single-prompt flow

            Log::info("Message sent to chatId: {$chatId}", [
                'message' => substr($message, 0, 50) . '...',
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to send message to chatId: {$chatId}", [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get messages config as array
     */
    private function getConfigMessages(?BotConfig $config): array
    {
        if (!$config || !is_array($config->settings ?? null)) {
            return [];
        }
        $settings = $config->settings;
        return is_array($settings['messages'] ?? null) ? $settings['messages'] : [];
    }

    /**
     * Get prompts config as array
     */
    private function getConfigPrompts(?BotConfig $config): array
    {
        if (!$config || !is_array($config->settings ?? null)) {
            return [];
        }
        $settings = $config->settings;
        return is_array($settings['prompts'] ?? null) ? $settings['prompts'] : [];
    }

    /**
     * Render {placeholders} in template with provided vars
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
     * Extract clean owner name from raw string using heuristic rules
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
        // Fallback: title case first token if Cyrillic
        if (preg_match('/^([А-Яа-яЁё]+(?:-[А-Яа-яЁё]+)?)/u', $s, $m)) {
            $name = mb_strtolower($m[1]);
            $parts = explode('-', $name);
            $parts = array_map(fn($p) => mb_strtoupper(mb_substr($p,0,1)) . mb_substr($p,1), $parts);
            return implode('-', $parts);
        }
        return 'Добрый день';
    }
}

