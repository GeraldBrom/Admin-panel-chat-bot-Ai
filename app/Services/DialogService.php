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
    // Dialog states (FSM)
    private const STATE_INITIAL = 'initial';
    private const STATE_INITIAL_QUESTION = 'initial_question';
    private const STATE_PRICE_CONFIRMATION = 'price_confirmation';
    private const STATE_PRICE_UPDATE = 'price_update';
    private const STATE_COMMISSION_INFO = 'commission_info';
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

        // Clean owner name
        $rawName = $objectData['ownerInfo'][0]['value'] ?? 'Клиент';
        $cleanedName = $this->openAIService->cleanOwnerName($rawName);

        // Prepare variables for templates
        $vars = [
            'name' => $cleanedName,
            'owner_name' => $cleanedName,
            'formattedAddDate' => $objectData['formattedAddDate'] ?? '',
            'objectCount' => $objectData['objectCount'] ?? '',
            'address' => $objectData['objectInfo'][0]['address'] ?? '',
        ];

        // Messages from config
        $messages = $this->getConfigMessages($config);

        // Send greeting
        $greetingTpl = $messages['greeting'] ?? '{name}, добрый день!';
        $this->sendMessageWithDelay($chatId, $this->renderTemplate($greetingTpl, $vars), 0);

        // Send initial question (two variants)
        if (empty($objectData['objectCount']) || $objectData['objectCount'] === 'ноль') {
            $initialTpl = $messages['initial_question_no_deals'] ?? (
                'Я — ИИ компании Capital Mars. Мы работали с вами {formattedAddDate}. Видим, вы ее снова сдаете — верно? Если да, можем подключиться к сдаче вашей квартиры?'
            );
        } else {
            $initialTpl = $messages['initial_question_with_deals'] ?? (
                'Я — ИИ компании Capital Mars. Мы уже {objectCount} сдавали вашу квартиру на {address}. {name}, вы ее снова сдаете — верно? Если да, можем подключиться к сдаче вашей квартиры?'
            );
        }

        $this->sendMessageWithDelay($chatId, $this->renderTemplate($initialTpl, $vars), 2000);

        // Update session state
        $session->update([
            'dialog_state' => ['state' => self::STATE_INITIAL_QUESTION],
        ]);

        // Update dialog
        $dialog->update([
            'current_state' => self::STATE_INITIAL_QUESTION,
        ]);

        Log::info("Dialog initialized for chatId: {$chatId}");
    }

    /**
     * Process incoming message
     */
    public function processIncomingMessage(string $chatId, string $messageText, array $meta = []): void
    {
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

        // Get current state
        $currentState = $session->dialog_state['state'] ?? self::STATE_INITIAL;

        // Handle negative intents
        if ($this->containsNegativeIntent($messageText)) {
            $this->handleNegativeIntent($chatId, $dialog, $session);
            return;
        }

        // Handle pause intents
        if ($this->containsPauseIntent($messageText)) {
            $this->sendMessageWithDelay($chatId, 'Хорошо, жду вашего подтверждения. Напишите, когда можно продолжить.');
            return;
        }

        // Route message by state
        match ($currentState) {
            self::STATE_INITIAL_QUESTION => $this->handleInitialQuestionResponse($chatId, $messageText, $session, $dialog),
            self::STATE_PRICE_CONFIRMATION => $this->handlePriceConfirmationResponse($chatId, $messageText, $session, $dialog),
            self::STATE_PRICE_UPDATE => $this->handlePriceUpdateResponse($chatId, $messageText, $session, $dialog),
            self::STATE_COMMISSION_INFO => $this->handleCommissionInfoResponse($chatId, $messageText, $session, $dialog),
            default => Log::warning("Unknown state: {$currentState}"),
        };
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
            
            // Save assistant message
            $dialog = Dialog::getOrCreate($chatId);
            Message::create([
                'dialog_id' => $dialog->dialog_id,
                'role' => 'assistant',
                'content' => $message,
            ]);

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
}

