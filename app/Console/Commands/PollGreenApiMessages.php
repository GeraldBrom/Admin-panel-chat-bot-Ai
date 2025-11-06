<?php

namespace App\Console\Commands;

use App\Models\ScenarioBotSession;
use App\Models\ChatKitSession;
use App\Services\DialogService;
use App\Services\GreenApiService;
use App\Services\ScenarioBotService;
use App\Services\ChatKitService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PollGreenApiMessages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'greenapi:poll {--minutes=1 : Число минут для lastIncomingMessages}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Опрос GREEN-API lastIncomingMessages и обработка входящих сообщений';

    public function handle(
        GreenApiService $greenApiService, 
        DialogService $dialogService,
        ScenarioBotService $scenarioBotService,
        ChatKitService $chatKitService
    ): int
    {
        $minutes = (int) $this->option('minutes');
        $minutes = $minutes > 0 ? $minutes : 1;

        $this->info("[greenapi:poll] Запрос последних сообщений за {$minutes} мин...");

        $messages = $greenApiService->getLastIncomingMessages($minutes);

        if (!is_array($messages) || empty($messages)) {
            $this->line('[greenapi:poll] Нет новых сообщений');
            return self::SUCCESS;
        }

        $processed = 0;
        foreach ($messages as $message) {
            try {
                // Дедупликация по idMessage (TTL 2 минуты)
                $id = $message['idMessage'] ?? null;
                if ($id && Cache::has("greenapi:processed:{$id}")) {
                    continue;
                }

                $normalized = $this->normalizeMessage($message);
                if (!$normalized) {
                    continue;
                }

                // Сначала проверяем сценарного бота
                if ($this->processScenarioBotMessage($normalized['chatId'], $normalized['messageText'], $scenarioBotService, $greenApiService)) {
                    $this->line("[greenapi:poll] ✅ Обработано сценарным ботом: {$normalized['chatId']}");
                    
                    if ($id) {
                        Cache::put("greenapi:processed:{$id}", true, now()->addMinutes(2));
                    }
                    
                    $processed++;
                    continue;
                }

                // Проверяем ChatKit Agent
                if ($this->processChatKitMessage($normalized['chatId'], $normalized['messageText'], $chatKitService, $greenApiService)) {
                    $this->line("[greenapi:poll] ✅ Обработано ChatKit Agent: {$normalized['chatId']}");
                    
                    if ($id) {
                        Cache::put("greenapi:processed:{$id}", true, now()->addMinutes(2));
                    }
                    
                    $processed++;
                    continue;
                }

                // Если нет ни сценарного, ни ChatKit - обрабатываем через обычный AI
                $dialogService->processIncomingMessage(
                    $normalized['chatId'],
                    $normalized['messageText'],
                    $normalized['meta']
                );

                if ($id) {
                    Cache::put("greenapi:processed:{$id}", true, now()->addMinutes(2));
                }

                $processed++;
            } catch (\Throwable $e) {
                Log::error('[greenapi:poll] Ошибка обработки сообщения', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        $this->info("[greenapi:poll] Обработано сообщений: {$processed}");
        return self::SUCCESS;
    }

    private function normalizeMessage(array $message = null): ?array
    {
        if (!$message) {
            return null;
        }

        $chatId = $message['chatId'] ?? null;
        $messageText = $message['textMessage']
            ?? ($message['messageData']['textMessageData']['textMessage'] ?? null);

        // Фильтруем только входящие текстовые
        $type = $message['type'] ?? $message['typeMessage'] ?? null;
        if (($type !== 'incoming') && ($type !== 'textMessage')) {
            // Пропускаем не-входящие и не-текстовые
            return null;
        }

        if (!$chatId || !$messageText) {
            return null;
        }

        $meta = [
            'messageId' => $message['idMessage'] ?? null,
            'timestamp' => $message['timestamp'] ?? null,
            'typeMessage' => $message['typeMessage'] ?? null,
            'raw' => $message,
        ];

        return [
            'chatId' => $chatId,
            'messageText' => $messageText,
            'meta' => $meta,
        ];
    }

    /**
     * Обработать сообщение через сценарного бота, если есть активная сессия
     * 
     * @return bool true если сообщение обработано сценарным ботом
     */
    private function processScenarioBotMessage(
        string $chatId, 
        string $messageText, 
        ScenarioBotService $scenarioBotService,
        GreenApiService $greenApiService
    ): bool
    {
        // Проверяем, есть ли активная сессия сценарного бота для этого чата
        $session = ScenarioBotSession::byChatId($chatId)
            ->active()
            ->first();

        if (!$session) {
            return false;
        }

        try {
            Log::info('[greenapi:poll] 🤖 Обрабатываем через сценарного бота', [
                'chatId' => $chatId,
                'session_id' => $session->id,
                'scenario_bot_id' => $session->scenario_bot_id,
            ]);

            // Обрабатываем сообщение через сценарного бота
            $response = $scenarioBotService->processMessage($chatId, $messageText);

            if ($response) {
                // Отправляем ответ пользователю через Green API
                $greenApiService->sendMessage($chatId, $response['message']);

                Log::info('[greenapi:poll] ✅ Отправлен ответ от сценарного бота', [
                    'chatId' => $chatId,
                    'session_completed' => $response['session_completed'] ?? false,
                ]);
            }

            return true;
        } catch (\Exception $e) {
            Log::error('[greenapi:poll] ❌ Ошибка обработки сценарного бота', [
                'chatId' => $chatId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * Обработать сообщение через ChatKit Agent, если есть активная сессия
     * 
     * @return bool true если сообщение обработано через ChatKit
     */
    private function processChatKitMessage(
        string $chatId, 
        string $messageText, 
        ChatKitService $chatKitService,
        GreenApiService $greenApiService
    ): bool
    {
        // Проверяем, есть ли активная сессия ChatKit для этого чата
        $session = ChatKitSession::where('chat_id', $chatId)
            ->where('status', 'running')
            ->first();

        if (!$session) {
            return false;
        }

        try {
            Log::info('[greenapi:poll] 🤖 Обрабатываем через ChatKit Agent', [
                'chatId' => $chatId,
                'session_id' => $session->id,
                'agent_id' => $session->agent_id,
            ]);

            // Обрабатываем сообщение через ChatKit Agent
            $response = $chatKitService->handleIncomingMessage(
                $chatId,
                $messageText,
                $session->object_id
            );

            if ($response && !empty($response['reply'])) {
                // Отправляем ответ пользователю через Green API
                $greenApiService->sendMessage($chatId, $response['reply']);

                Log::info('[greenapi:poll] ✅ Отправлен ответ от ChatKit Agent', [
                    'chatId' => $chatId,
                    'reply_length' => mb_strlen($response['reply']),
                    'intent' => $response['intent'] ?? null,
                ]);
                
                return true;
            }
            
            // Если ответ пустой - логируем и НЕ блокируем обработку другими ботами
            Log::warning('[greenapi:poll] ChatKit Agent не вернул ответ', [
                'chatId' => $chatId,
            ]);
            
            return false;
        } catch (\Exception $e) {
            Log::error('[greenapi:poll] ❌ Ошибка обработки ChatKit', [
                'chatId' => $chatId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }
}


