<?php

namespace App\Services;

use App\Models\ChatKitSession;
use App\Models\ChatKitMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ChatKitService - работа с OpenAI Agent Builder (Responses API)
 * 
 * Этот сервис интегрируется с OpenAI Agent Builder для создания
 * интеллектуальных диалогов через предварительно настроенных агентов.
 */
class ChatKitService
{
    private string $apiKey;
    private string $agentId;
    private bool $useProxy;
    private ?string $proxyHost;
    private ?string $proxyPort;
    private RemoteDatabaseService $remoteDatabaseService;

    public function __construct(RemoteDatabaseService $remoteDatabaseService)
    {
        $this->apiKey = config('services.openai.api_key');
        $this->agentId = config('services.chatkit.agent_id', env('AGENT_ID'));
        $this->useProxy = config('services.openai.use_proxy', false);
        $this->proxyHost = config('services.openai.proxy_host');
        $this->proxyPort = config('services.openai.proxy_port');
        $this->remoteDatabaseService = $remoteDatabaseService;
    }

    /**
     * Обработать входящее сообщение через OpenAI Agent
     * 
     * @param string $chatId ID чата (номер WhatsApp)
     * @param string $userMessage Текст сообщения от пользователя
     * @param int $objectId ID объекта недвижимости
     * @return array ['reply' => string, 'intent' => string|null, 'structured' => array|null]
     */
    public function handleIncomingMessage(string $chatId, string $userMessage, int $objectId): array
    {
        // 1. Получить или создать сессию
        $session = $this->getOrCreateSession($chatId, $objectId);
        
        // 2. Загрузить историю диалога
        $history = $session->getHistory(10);
        
        // 3. Получить переменные из CRM
        $vars = $this->getVariablesFromCrm($chatId, $objectId);
        
        // 4. Вызвать OpenAI Agent
        $response = $this->callAgent($history, $userMessage, $vars);
        
        // 5. Сохранить сообщения в БД
        $session->addMessage('user', $userMessage);
        
        // Сохраняем ответ только если он не пустой
        if (!empty($response['reply'])) {
            $session->addMessage('assistant', $response['reply'], [
                'intent' => $response['intent'] ?? null,
                'structured' => $response['structured'] ?? null,
            ]);
        }
        
        // 6. Обновить контекст сессии
        $session->update([
            'context' => [
                'last_message_at' => now()->toIso8601String(),
                'messages_count' => $session->messages()->count(),
                'vars' => $vars,
            ],
        ]);
        
        return $response;
    }

    /**
     * Вызвать OpenAI Agent (Agent Builder)
     * 
     * @param array $history История диалога [['role'=>'user|assistant', 'content'=>'...']]
     * @param string $latestUserText Последнее сообщение пользователя
     * @param array $vars Переменные для контекста (из CRM)
     * @return array ['reply' => string, 'text' => string, 'intent' => string|null, 'structured' => array|null]
     */
    public function callAgent(array $history, string $latestUserText, array $vars = []): array
    {
        // Собираем вход для Responses API
        $input = [];
        
        foreach ($history as $msg) {
            $input[] = [
                'role' => $msg['role'],
                'content' => $msg['content'],
            ];
        }
        
        // Добавляем последнее сообщение пользователя
        $input[] = [
            'role' => 'user',
            'content' => $latestUserText,
        ];
        
        // Подготавливаем messages для Chat Completions API
        // Агент уже настроен в Agent Builder, просто передаем историю
        $messages = [];
        
        // Если есть переменные из CRM - добавляем их как первое сообщение для контекста
        if (!empty($vars)) {
            $contextText = "Информация об объекте:\n";
            $contextText .= "Владелец: {$vars['owner_name_clean']}\n";
            $contextText .= "Адрес: {$vars['address']}\n";
            $contextText .= "Цена: {$vars['price']}\n";
            $contextText .= "{$vars['commission_text']}";
            
            $messages[] = [
                'role' => 'system',
                'content' => $contextText
            ];
        }
        
        // Добавляем историю диалога
        foreach ($input as $msg) {
            $messages[] = $msg;
        }
        
        $payload = [
            'model' => 'gpt-4o',
            'messages' => $messages,
            'max_tokens' => 2000,
        ];

        try {
            $response = $this->getHttpClient()
                ->connectTimeout(10)
                ->timeout(25)
                ->post("{$this->getBaseUrl()}/chat/completions", $payload);

            if ($response->successful()) {
                $json = $response->json();
                
                // Извлекаем ответ из Chat Completions API
                $content = $json['choices'][0]['message']['content'] ?? null;
                
                if ($content) {
                    Log::info('ChatKit Agent successful response', [
                        'response_length' => mb_strlen($content),
                        'usage' => $json['usage'] ?? null,
                    ]);
                    
                    return [
                        'reply' => trim($content),
                        'text' => trim($content),
                        'intent' => null,
                        'structured' => null,
                    ];
                }
                
                // Fallback
                Log::warning('ChatKit Agent вернул пустой ответ', ['response' => $json]);
                return [
                    'reply' => null,
                    'text' => '',
                    'intent' => null,
                    'structured' => null,
                ];
            }

            Log::error('ChatKit Agent API failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'agent_id' => $this->agentId,
            ]);
            
            // Возвращаем пустой ответ при ошибке API (не отправляем сообщение пользователю)
            return [
                'reply' => null,
                'text' => '',
                'intent' => null,
                'structured' => null,
            ];
            
        } catch (\Throwable $e) {
            Log::error('ChatKit Agent exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'agent_id' => $this->agentId,
            ]);
            
            // Возвращаем пустой ответ при исключении (не отправляем сообщение пользователю)
            return [
                'reply' => null,
                'text' => '',
                'intent' => null,
                'structured' => null,
            ];
        }
    }

    /**
     * Получить или создать сессию ChatKit
     * При повторном вызове для существующей сессии:
     * - Если сессия активна (running) - возвращает её без изменений
     * - Если сессия неактивна - перезапускает её
     */
    public function getOrCreateSession(string $chatId, int $objectId, string $platform = 'whatsapp'): ChatKitSession
    {
        $session = ChatKitSession::where('chat_id', $chatId)->first();
        
        if ($session) {
            // Если сессия уже активна - просто возвращаем её
            if ($session->status === 'running') {
                Log::debug('ChatKit session already running', [
                    'session_id' => $session->id,
                    'chat_id' => $chatId,
                ]);
                return $session;
            }
            
            // Если сессия остановлена - перезапускаем её
            $previousStatus = $session->status;
            
            $session->update([
                'object_id' => $objectId,
                'platform' => $platform,
                'agent_id' => $this->agentId,
                'status' => 'running',
                'started_at' => now(),
                'stopped_at' => null,
            ]);
            
            Log::info('ChatKit session restarted', [
                'session_id' => $session->id,
                'chat_id' => $chatId,
                'previous_status' => $previousStatus,
            ]);
            
            return $session->fresh();
        }
        
        // Создаем новую сессию
        $session = ChatKitSession::create([
            'chat_id' => $chatId,
            'object_id' => $objectId,
            'platform' => $platform,
            'agent_id' => $this->agentId,
            'status' => 'running',
            'started_at' => now(),
            'metadata' => [
                'created_by' => 'api',
            ],
        ]);
        
        Log::info('ChatKit session created', [
            'session_id' => $session->id,
            'chat_id' => $chatId,
        ]);
        
        return $session;
    }

    /**
     * Получить переменные из CRM для контекста агента
     * 
     * @param string $chatId Номер телефона
     * @param int $objectId ID объекта недвижимости
     * @return array Массив переменных для подстановки в промпт агента
     */
    public function getVariablesFromCrm(string $chatId, int $objectId): array
    {
        // Дефолтные значения
        $defaults = [
            'owner_name_clean' => 'Коллеги',
            'address' => 'ваша квартира',
            'price' => '—',
            'commission_text' => 'Наша комиссия — 50% по факту заселения.',
        ];

        try {
            // Получаем данные из удалённой БД (myhomeday)
            $objectData = $this->remoteDatabaseService->getObjectData($objectId);
            
            if ($objectData) {
                $vars = [
                    'owner_name_clean' => $objectData['owner_name'] ?? $defaults['owner_name_clean'],
                    'address' => $objectData['address'] ?? $defaults['address'],
                    'price' => $objectData['price'] ? number_format($objectData['price'], 0, ',', ' ') . ' ₽' : $defaults['price'],
                    'commission_text' => $objectData['commission_text'] ?? $defaults['commission_text'],
                    'objectCount' => $objectData['objectCount'] ?? 1,
                ];
                
                // Фильтруем пустые значения и возвращаем с дефолтами
                return array_merge($defaults, array_filter($vars, fn($v) => $v !== null && $v !== ''));
            }
        } catch (\Throwable $e) {
            Log::warning('Не удалось получить данные из CRM', [
                'chat_id' => $chatId,
                'object_id' => $objectId,
                'error' => $e->getMessage(),
            ]);
        }

        return $defaults;
    }

    /**
     * Остановить сессию ChatKit
     */
    public function stopSession(string $chatId): bool
    {
        $session = ChatKitSession::where('chat_id', $chatId)->first();
        
        if ($session) {
            return $session->stop();
        }
        
        return false;
    }

    /**
     * Очистить историю сессии
     */
    public function clearSession(string $chatId): bool
    {
        $session = ChatKitSession::where('chat_id', $chatId)->first();
        
        if ($session) {
            // Удаляем все сообщения
            $session->messages()->delete();
            
            // Обнуляем контекст
            $session->update([
                'context' => null,
            ]);
            
            return true;
        }
        
        return false;
    }

    /**
     * Получить все активные сессии
     */
    public function getActiveSessions(): \Illuminate\Database\Eloquent\Collection
    {
        return ChatKitSession::active()
            ->with('messages')
            ->orderBy('started_at', 'desc')
            ->get();
    }

    /**
     * Получить сессию по chat_id
     */
    public function getSession(string $chatId): ?ChatKitSession
    {
        return ChatKitSession::where('chat_id', $chatId)
            ->with('messages')
            ->first();
    }

    /**
     * Получить базовый URL для OpenAI API
     */
    private function getBaseUrl(): string
    {
        return 'https://api.openai.com/v1';
    }

    /**
     * Получить HTTP-клиент с опциональным прокси
     */
    private function getHttpClient()
    {
        $client = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type' => 'application/json',
        ]);

        $options = [
            'timeout' => 60,
            'connect_timeout' => 15,
            'curl' => [
                CURLOPT_DNS_CACHE_TIMEOUT => 300,
                CURLOPT_TCP_KEEPALIVE => 1,
                CURLOPT_TCP_KEEPIDLE => 120,
                CURLOPT_TCP_KEEPINTVL => 60,
                CURLOPT_FRESH_CONNECT => false,
                CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                CURLOPT_DNS_USE_GLOBAL_CACHE => false,
                CURLOPT_NOSIGNAL => 1,
                CURLOPT_FORBID_REUSE => 0,
                CURLOPT_RESOLVE => [
                    'api.openai.com:443:104.18.7.192',
                    'api.openai.com:443:104.18.6.192',
                ],
            ],
        ];

        if ($this->useProxy && $this->proxyHost && $this->proxyPort) {
            $proxyUri = sprintf('socks5://%s:%s', $this->proxyHost, $this->proxyPort);
            $options['proxy'] = $proxyUri;
        }

        return $client->withOptions($options);
    }
}

