<?php

namespace App\Services;

use App\Models\ScenarioBot;
use App\Models\ScenarioBotSession;
use App\Models\ScenarioBotMessage;
use App\Models\ScenarioStep;
use App\Services\Messaging\TemplateRenderer;
use App\Services\Messaging\MessageSender;
use App\Services\Extraction\ExtractOwnerNameWithAi;
use Illuminate\Support\Facades\Log;

class ScenarioBotService
{
    public function __construct(
        private GreenApiService $greenApiService,
        private RemoteDatabaseService $remoteDbService,
        private TemplateRenderer $templateRenderer,
        private MessageSender $messageSender,
        private ExtractOwnerNameWithAi $nameExtractor,
    ) {}

    /**
     * Запустить сессию сценарного бота
     */
    public function startSession(string $chatId, int $scenarioBotId, ?int $objectId = null, string $platform = 'whatsapp'): ScenarioBotSession
    {
        $scenarioBot = ScenarioBot::with('startStep')->findOrFail($scenarioBotId);

        // Проверяем, есть ли уже сессия для этого chat_id (любого статуса)
        $existingSession = ScenarioBotSession::byChatId($chatId)->first();

        if ($existingSession) {
            // Если сессия уже активна - просто возвращаем ее
            if ($existingSession->status === 'running') {
                Log::info('[ScenarioBotService] Сессия уже активна', [
                    'session_id' => $existingSession->id,
                    'chat_id' => $chatId,
                ]);
                return $existingSession;
            }

            // Если сессия остановлена - перезапускаем ее
            Log::info('[ScenarioBotService] Перезапуск остановленной сессии', [
                'session_id' => $existingSession->id,
                'chat_id' => $chatId,
                'old_status' => $existingSession->status,
            ]);

            $existingSession->update([
                'status' => 'running',
                'current_step_id' => $scenarioBot->start_step_id,
                'dialog_data' => ['current_step' => 1],
                'started_at' => now(),
                'stopped_at' => null,
            ]);
            
            // Перезагружаем сессию из БД чтобы убедиться что изменения сохранились
            $existingSession->refresh();
            
            Log::info('[ScenarioBotService] Сессия перезапущена, статус после обновления', [
                'session_id' => $existingSession->id,
                'chat_id' => $chatId,
                'status' => $existingSession->status,
                'dialog_data' => $existingSession->dialog_data,
            ]);

            // Отправляем приветственное сообщение + первый вопрос при перезапуске
            if ($scenarioBot->welcome_message) {
                try {
                    // Получаем переменные объекта для подстановки
                    $vars = $this->getObjectVariables($existingSession->object_id);
                    
                    // Рендерим приветственное сообщение с переменными
                    $message = $this->templateRenderer->render($scenarioBot->welcome_message, $vars);
                    
                    // Добавляем первый вопрос сценария
                    $scenario = $scenarioBot->settings['scenario'] ?? [];
                    if (!empty($scenario['step1_question'])) {
                        $message .= "\n\n" . $scenario['step1_question'];
                    }
                    
                    $this->messageSender->sendWithDelay($chatId, $message, 0);
                    
                    // Сохраняем сообщение в БД
                    ScenarioBotMessage::create([
                        'session_id' => $existingSession->id,
                        'role' => 'assistant',
                        'content' => $message,
                        'meta' => ['type' => 'welcome'],
                    ]);
                    
                    Log::info('[ScenarioBotService] Отправлено приветственное сообщение (перезапуск)', [
                        'session_id' => $existingSession->id,
                        'chat_id' => $chatId,
                    ]);
                } catch (\Exception $e) {
                    Log::error('[ScenarioBotService] Ошибка отправки приветственного сообщения', [
                        'session_id' => $existingSession->id,
                        'chat_id' => $chatId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return $existingSession->fresh(['messages']);
        }

        // Создаем новую сессию если ее еще нет
        $session = ScenarioBotSession::create([
            'scenario_bot_id' => $scenarioBotId,
            'chat_id' => $chatId,
            'object_id' => $objectId,
            'platform' => $platform,
            'current_step_id' => $scenarioBot->start_step_id,
            'status' => 'running',
            'dialog_data' => ['current_step' => 1],
            'metadata' => [
                'started_at' => now()->toIso8601String(),
            ],
            'started_at' => now(),
        ]);

        Log::info('[ScenarioBotService] Запущена новая сессия', [
            'session_id' => $session->id,
            'chat_id' => $chatId,
            'bot_id' => $scenarioBotId,
        ]);

        // Отправляем приветственное сообщение + первый вопрос клиенту через GreenAPI
        if ($scenarioBot->welcome_message) {
            try {
                // Получаем переменные объекта для подстановки
                $vars = $this->getObjectVariables($objectId);
                
                // Рендерим приветственное сообщение с переменными
                $message = $this->templateRenderer->render($scenarioBot->welcome_message, $vars);
                
                // Добавляем первый вопрос сценария
                $scenario = $scenarioBot->settings['scenario'] ?? [];
                if (!empty($scenario['step1_question'])) {
                    $message .= "\n\n" . $scenario['step1_question'];
                }
                
                $this->messageSender->sendWithDelay($chatId, $message, 0);
                
                // Сохраняем сообщение в БД
                ScenarioBotMessage::create([
                    'session_id' => $session->id,
                    'role' => 'assistant',
                    'content' => $message,
                    'meta' => ['type' => 'welcome'],
                ]);
                
                Log::info('[ScenarioBotService] Отправлено приветственное сообщение', [
                    'session_id' => $session->id,
                    'chat_id' => $chatId,
                    'message' => substr($message, 0, 100),
                ]);
            } catch (\Exception $e) {
                Log::error('[ScenarioBotService] Ошибка отправки приветственного сообщения', [
                    'session_id' => $session->id,
                    'chat_id' => $chatId,
                    'error' => $e->getMessage(),
                ]);
            }
        } else {
            Log::warning('[ScenarioBotService] У бота нет приветственного сообщения', [
                'bot_id' => $scenarioBotId,
            ]);
        }

        return $session->load('messages');
    }

    /**
     * Обработать входящее сообщение от пользователя
     */
    public function processMessage(string $chatId, string $message): ?array
    {
        Log::info('📨 Получено сообщение от chatId: ' . $chatId, ['message' => $message]);
        
        // Находим активную сессию для этого чата
        $session = ScenarioBotSession::with('scenarioBot')
            ->byChatId($chatId)
            ->active()
            ->first();

        if (!$session) {
            // Проверяем, есть ли вообще сессия
            $anySession = ScenarioBotSession::byChatId($chatId)->first();
            if ($anySession) {
                Log::warning('[ScenarioBotService] Сессия найдена, но не активна', [
                    'chat_id' => $chatId,
                    'session_id' => $anySession->id,
                    'status' => $anySession->status,
                    'expected_status' => 'running',
                ]);
            } else {
                Log::warning('[ScenarioBotService] Активная сессия не найдена', [
                    'chat_id' => $chatId,
                ]);
            }
            return null;
        }

        $bot = $session->scenarioBot;
        if (!$bot) {
            Log::error('[ScenarioBotService] Бот не найден для сессии', [
                'session_id' => $session->id,
            ]);
            return null;
        }

        // Сохраняем сообщение пользователя в БД
        ScenarioBotMessage::create([
            'session_id' => $session->id,
            'role' => 'user',
            'content' => $message,
        ]);

        // Получаем текущее состояние диалога
        $dialogData = $session->dialog_data ?? [];
        $currentStep = $dialogData['current_step'] ?? 1;

        // Нормализуем ответ пользователя
        $normalizedMessage = mb_strtolower(trim($message));

        Log::info('[ScenarioBotService] Обработка сообщения', [
            'session_id' => $session->id,
            'chat_id' => $chatId,
            'current_step' => $currentStep,
            'message' => $message,
        ]);

        // Логика сценария
        $response = $this->processScenarioStep($session, $currentStep, $normalizedMessage, $dialogData);

        // Применяем рендеринг переменных к сообщению
        $vars = $this->getObjectVariables($session->object_id);
        
        // Добавляем новую цену из dialog_data если она есть
        if (!empty($response['dialog_data']['new_price_formatted'])) {
            $vars['price'] = $response['dialog_data']['new_price_formatted'];
        }
        
        $response['message'] = $this->templateRenderer->render($response['message'], $vars);

        // Сохраняем ответ бота в БД
        ScenarioBotMessage::create([
            'session_id' => $session->id,
            'role' => 'assistant',
            'content' => $response['message'],
            'meta' => [
                'step' => $currentStep,
                'completed' => $response['completed'] ?? false,
            ],
        ]);

        // Обновляем session с новыми данными
        $session->update([
            'dialog_data' => $response['dialog_data'],
        ]);

        if ($response['completed']) {
            $session->update(['status' => 'completed']);
        }

        return [
            'message' => $response['message'],
            'session_completed' => $response['completed'] ?? false,
        ];
    }

    /**
     * Обработка конкретного шага сценария
     */
    private function processScenarioStep(ScenarioBotSession $session, $currentStep, string $userMessage, array $dialogData): array
    {
        $bot = $session->scenarioBot;
        $scenario = $bot->settings['scenario'] ?? [];
        
        Log::info('[ScenarioBotService] Шаг сценария', [
            'current_step' => $currentStep,
            'current_step_type' => gettype($currentStep),
            'user_message' => $userMessage,
        ]);

        // Шаг 1: Узнаем сдается ли квартира
        if ($currentStep == 1) {
            if (in_array($userMessage, ['да', 'yes', 'да!', 'yes!'])) {
                $dialogData['step_1_answer'] = 'да';
                $dialogData['is_rented'] = true;
                $dialogData['current_step'] = 2;
                
                return [
                    'message' => $scenario['step1_yes_response'] ?? '',
                    'dialog_data' => $dialogData,
                    'completed' => false,
                ];
            } elseif (in_array($userMessage, ['нет', 'no', 'нет!', 'no!'])) {
                $dialogData['step_1_answer'] = 'нет';
                $dialogData['is_rented'] = false;
                
                return [
                    'message' => $scenario['step1_no_response'] ?? '',
                    'dialog_data' => $dialogData,
                    'completed' => true,
                ];
            } else {
                $question = $scenario['step1_question'] ?? '';
                return [
                    'message' => $question ? "Пожалуйста, ответьте Да или Нет.\n\n{$question}" : '',
                    'dialog_data' => $dialogData,
                    'completed' => false,
                ];
            }
        }

        // Шаг 2: Согласен ли работать с нами
        if ($currentStep == 2) {
            if (in_array($userMessage, ['да', 'yes', 'да!', 'yes!'])) {
                $dialogData['step_2_answer'] = 'да';
                $dialogData['agrees_to_work'] = true;
                $dialogData['current_step'] = 3;
                
                return [
                    'message' => $scenario['step2_yes_response'] ?? '',
                    'dialog_data' => $dialogData,
                    'completed' => false,
                ];
            } elseif (in_array($userMessage, ['нет', 'no', 'нет!', 'no!'])) {
                $dialogData['step_2_answer'] = 'нет';
                $dialogData['agrees_to_work'] = false;
                
                return [
                    'message' => $scenario['step2_no_response'] ?? '',
                    'dialog_data' => $dialogData,
                    'completed' => true,
                ];
            } else {
                $question = $scenario['step1_yes_response'] ?? '';
                return [
                    'message' => $question ? "Пожалуйста, ответьте Да или Нет.\n\n{$question}" : '',
                    'dialog_data' => $dialogData,
                    'completed' => false,
                ];
            }
        }

        // Шаг 3: Проверка цены
        if ($currentStep == 3) {
            if (in_array($userMessage, ['да', 'yes', 'да!', 'yes!'])) {
                $dialogData['step_3_answer'] = 'да';
                $dialogData['price_confirmed'] = true;
                
                return [
                    'message' => $scenario['step3_yes_response'] ?? '',
                    'dialog_data' => $dialogData,
                    'completed' => true,
                ];
            } elseif (in_array($userMessage, ['нет', 'no', 'нет!', 'no!'])) {
                $dialogData['step_3_answer'] = 'нет';
                $dialogData['price_confirmed'] = false;
                $dialogData['current_step'] = 3.1; // Переход на подшаг
                
                return [
                    'message' => $scenario['step3_no_response'] ?? '',
                    'dialog_data' => $dialogData,
                    'completed' => false,
                ];
            } else {
                $question = $scenario['step2_yes_response'] ?? '';
                return [
                    'message' => $question ? "Пожалуйста, ответьте Да или Нет.\n\n{$question}" : '',
                    'dialog_data' => $dialogData,
                    'completed' => false,
                ];
            }
        }

        // Шаг 3.1: Ввод новой цены
        if ($currentStep == 3.1) {
            // Извлекаем цену из сообщения (удаляем все кроме цифр)
            $priceStr = preg_replace('/[^0-9]/', '', $userMessage);
            
            if (!empty($priceStr)) {
                $newPrice = (int)$priceStr;
                $dialogData['new_price'] = $newPrice;
                $dialogData['new_price_formatted'] = number_format($newPrice, 0, '.', ',') . ' руб';
                
                return [
                    'message' => $scenario['step3_1_final_message'] ?? '',
                    'dialog_data' => $dialogData,
                    'completed' => true,
                ];
            } else {
                $question = $scenario['step3_no_response'] ?? '';
                return [
                    'message' => $question ? "Пожалуйста, укажите цену числом.\n\n{$question}" : '',
                    'dialog_data' => $dialogData,
                    'completed' => false,
                ];
            }
        }

        // Неизвестный шаг
        return [
            'message' => "Произошла ошибка в сценарии. Обратитесь к администратору.",
            'dialog_data' => $dialogData,
            'completed' => true,
        ];
    }

    /**
     * Получить приветственное сообщение для бота
     */
    public function getWelcomeMessage(int $scenarioBotId): ?string
    {
        $bot = ScenarioBot::find($scenarioBotId);
        return $bot?->welcome_message;
    }

    /**
     * Остановить сессию
     */
    public function stopSession(string $chatId): bool
    {
        $session = ScenarioBotSession::byChatId($chatId)
            ->active()
            ->first();

        if (!$session) {
            return false;
        }

        $session->stop();

        Log::info('[ScenarioBotService] Сессия остановлена', [
            'session_id' => $session->id,
            'chat_id' => $chatId,
        ]);

        return true;
    }

    /**
     * Получить текущий шаг сессии
     */
    public function getCurrentStep(string $chatId): ?array
    {
        $session = ScenarioBotSession::with('currentStep')
            ->byChatId($chatId)
            ->active()
            ->first();

        if (!$session || !$session->currentStep) {
            return null;
        }

        $step = $session->currentStep;

        return [
            'step_id' => $step->id,
            'step_name' => $step->name,
            'message' => $step->message,
            'step_type' => $step->step_type,
            'options' => $step->options,
        ];
    }

    /**
     * Очистить (сбросить) сессию
     */
    public function resetSession(string $chatId): bool
    {
        $session = ScenarioBotSession::with('scenarioBot')
            ->byChatId($chatId)
            ->active()
            ->first();

        if (!$session) {
            return false;
        }

        // Сбрасываем на начальный шаг
        $session->update([
            'current_step_id' => $session->scenarioBot->start_step_id,
            'dialog_data' => [],
        ]);

        Log::info('[ScenarioBotService] Сессия сброшена', [
            'session_id' => $session->id,
            'chat_id' => $chatId,
        ]);

        return true;
    }

    /**
     * Получить все активные сессии для бота
     */
    public function getActiveSessions(int $scenarioBotId): \Illuminate\Database\Eloquent\Collection
    {
        return ScenarioBotSession::where('scenario_bot_id', $scenarioBotId)
            ->active()
            ->get();
    }

    /**
     * Получить данные объекта для подстановки переменных
     */
    private function getObjectVariables(?int $objectId): array
    {
        if (!$objectId) {
            return [];
        }

        try {
            $objectData = $this->remoteDbService->getObjectData($objectId);

            if (!$objectData) {
                Log::warning('[ScenarioBotService] Объект не найден в удаленной БД', [
                    'object_id' => $objectId,
                ]);
                return [];
            }

            // Извлекаем чистое имя владельца
            $ownerNameRaw = $objectData['owner_name'] ?? '';
            $ownerNameClean = $this->nameExtractor->extractOwnerNameWithAI($ownerNameRaw);

            // Формируем переменные как в DialogService (поддерживаем оба формата: с подчеркиванием и без)
            $formattedPrice = isset($objectData['price']) ? number_format($objectData['price'], 0, '.', ',') : '';
            
            // Получаем числовое значение deal_count для условной логики
            $dealCount = (int) ($objectData['deal_count'] ?? 0);
            $objectCountWord = $objectData['count'] ?? '0';
            $objectCountWithSuffix = $objectData['objectCountWithSuffix'] ?? '0 раз';
            
            // Условная логика: формируем текст в зависимости от количества сделок
            if ($dealCount === 0) {
                // Если сделок не было - используем другой текст без упоминания количества
                $rentalPhrase = "работали с вами по квартире на";
            } else {
                // Если были сделки - указываем количество со склонением
                $rentalPhrase = "{$objectCountWithSuffix} сдавали вашу квартиру на";
            }
            
            return [
                'owner_name' => $ownerNameRaw,
                'owner_name_clean' => $ownerNameClean,
                'ownernameclean' => $ownerNameClean, // Альтернативное написание без подчеркиваний
                'ownername' => $ownerNameClean,
                'address' => $objectData['address'] ?? '',
                'price' => $formattedPrice, // Теперь price тоже отформатирована
                'formatted_price' => $formattedPrice, // Для обратной совместимости
                'commission_client' => $objectData['commission_client'] ?? '',
                'objectCount' => $objectCountWord,
                'object_count' => $objectCountWord,
                'rental_phrase' => $rentalPhrase, // Условная фраза для приветственного сообщения
            ];
        } catch (\Exception $e) {
            Log::error('[ScenarioBotService] Ошибка получения данных объекта', [
                'object_id' => $objectId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

}

