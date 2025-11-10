<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAIService
{
    private string $apiKey;
    private bool $useProxy;
    private ?string $proxyHost;
    private ?string $proxyPort;

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key');
        $this->useProxy = config('services.openai.use_proxy', false);
        $this->proxyHost = config('services.openai.proxy_host');
        $this->proxyPort = config('services.openai.proxy_port');
    }

    /**
     * Чат с использованием Responses API и File Search (RAG)
     * 
     * OpenAI File Search автоматически ищет релевантные документы в указанных Vector Stores
     * и использует их для генерации более точных ответов на основе ваших данных.
     * 
     * @param string $systemPrompt Системный промпт для ассистента
     * @param array $history История диалога [['role' => 'user|assistant', 'content' => '...']]
     * @param float|null $temperature Температура (0.0-2.0) - контролирует случайность ответов
     * @param int|null $maxTokens Максимальное количество токенов в ответе
     * @param array $vectorStoreIds Массив ID векторных хранилищ (например: ['vs_abc123', 'vs_xyz789'])
     * @param string|null $model Модель OpenAI (по умолчанию gpt-4o)
     * @param string|null $serviceTier Уровень сервиса: 'auto', 'default', 'flex' (по умолчанию 'flex')
     * @param float|null $topP Параметр top_p (0.0-1.0) - альтернатива temperature для контроля разнообразия
     * 
     * @return array ['content' => string, 'response_id' => string|null, 'usage' => ['prompt_tokens'=>int,'completion_tokens'=>int]]
     */
    public function chatWithRag(
        string $systemPrompt,
        array $history,
        ?float $temperature = null,
        ?int $maxTokens = null,
        array $vectorStoreIds = [],
        ?string $model = null,
        ?string $serviceTier = null,
        ?float $topP = null
    ): array {
        
        $input = [];
        
        // Vector Stores передаются через параметр tools, не нужно добавлять их в промпт
        $input[] = [
            'role' => 'system',
            'content' => $systemPrompt,
        ];
        foreach ($history as $msg) {
            if (!isset($msg['role'], $msg['content'])) {
                continue;
            }
            $input[] = [
                'role' => $msg['role'],
                'content' => (string) $msg['content'],
            ];
        }

        $modelName = $model ?? 'gpt-4o';
        
        $payload = [
            'model' => $modelName,
            'input' => $input,
            'max_output_tokens' => $maxTokens ?? 2000,
            'service_tier' => $serviceTier ?? 'flex',
        ];

        // Добавляем temperature и topP если они указаны
        if ($temperature !== null) {
            $payload['temperature'] = $temperature;
        }
        if ($topP !== null) {
            $payload['top_p'] = $topP;
        }

        // Если указаны Vector Stores, добавляем File Search tool
        // OpenAI автоматически будет искать релевантные документы в указанных базах
        // и использовать их для более точных ответов (RAG - Retrieval-Augmented Generation)
        if (!empty($vectorStoreIds)) {
            $payload['tools'] = [ [
                'type' => 'file_search',
                'vector_store_ids' => array_values($vectorStoreIds),
            ] ];
        }

        try {
            $response = $this->getHttpClient()->post("{$this->getBaseUrl()}/responses", $payload);

            if ($response->successful()) {
                $responseId = $response->json('id');
                $rawOutput = $response->json('output') ?? [];

                // Извлекаем текст из структуры Responses API
                $content = '';
                $output = $rawOutput;
                
                // Ищем элемент с type: 'message' в массиве output
                if (is_array($output) && !empty($output)) {
                    $messageOutput = null;
                    foreach ($output as $item) {
                        if (isset($item['type']) && $item['type'] === 'message') {
                            $messageOutput = $item;
                            break;
                        }
                    }
                    
                    if ($messageOutput) {
                        $outputContent = $messageOutput['content'] ?? '';
                        
                        if (is_string($outputContent)) {
                            $content = trim($outputContent);
                        } elseif (is_array($outputContent)) {
                            // Ищем output_text в content
                            foreach ($outputContent as $contentItem) {
                                if (isset($contentItem['type']) && $contentItem['type'] === 'output_text' && isset($contentItem['text'])) {
                                    $content = trim($contentItem['text']);
                                    break;
                                }
                            }
                        }
                    }
                }

                $usage = [
                    'prompt_tokens' => (int) ($response->json('usage.input_tokens') ?? $response->json('usage.prompt_tokens') ?? 0),
                    'completion_tokens' => (int) ($response->json('usage.output_tokens') ?? $response->json('usage.completion_tokens') ?? 0),
                ];

                if ($content === '') {
                    Log::warning('OpenAI Responses API вернул пустой контент, fallback на chat/completions', [
                        'response_id' => $responseId,
                        'output_structure' => $rawOutput,
                        'usage' => $usage,
                    ]);
                    
                    // Fallback на chat/completions API, если Responses API не вернул message
                    return $this->chat($systemPrompt, $history, $temperature, $maxTokens, null, null, $model, $topP);
                }

                Log::info('✅ OpenAI Responses API успешный ответ с RAG', [
                    'response_id' => $responseId,
                    'content_length' => mb_strlen($content),
                    'vector_stores_used' => count($vectorStoreIds),
                    'model' => $modelName,
                    'temperature' => $temperature,
                    'top_p' => $topP,
                    'usage' => $usage,
                ]);

                return [
                    'content' => $content,
                    'response_id' => $responseId,
                    'usage' => $usage,
                ];
            }

            Log::error('OpenAI chatWithRag failed ошибка', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        } catch (\Illuminate\Http\Client\RequestException | \Illuminate\Http\Client\ConnectionException $e) {
            Log::warning('OpenAI Responses API timeout/connection error, falling back to chat/completions ошибка таймаута или проблем с соединением', [
                'error' => $e->getMessage()
            ]);
            return $this->chat($systemPrompt, $history, $temperature, $maxTokens, null, null, $model, $topP);
        } catch (\Throwable $e) {
            Log::error('OpenAI chatWithRag exception ошибка', [ 'error' => $e->getMessage() ]);
        }

        return $this->chat($systemPrompt, $history, $temperature, $maxTokens, null, null, $model, $topP);
    }

    /**
     * Единая точка входа для чата: системный промпт + история → ответ ассистента
     * Возвращает массив: ['content' => string, 'response_id' => string|null, 'usage' => ['prompt_tokens'=>int,'completion_tokens'=>int]]
     * 
     * @param string $systemPrompt Системный промпт
     * @param array $history История диалога
     * @param float|null $temperature Температура (0.0-2.0)
     * @param int|null $maxTokens Максимальное количество токенов
     * @param string|null $vectorStoreIdMain ID основного векторного хранилища (deprecated, используйте chatWithRag)
     * @param string|null $vectorStoreIdObjections ID векторного хранилища возражений (deprecated, используйте chatWithRag)
     * @param string|null $model Модель OpenAI
     * @param float|null $topP Параметр top_p (0.0-1.0)
     */
    public function chat(string $systemPrompt, array $history, ?float $temperature = null, ?int $maxTokens = null, ?string $vectorStoreIdMain = null, ?string $vectorStoreIdObjections = null, ?string $model = null, ?float $topP = null): array
    {
        $messages = [];
        $systemParts = $systemPrompt;
        if ($vectorStoreIdMain || $vectorStoreIdObjections) {
            $systemParts .= "\n\nБаза знаний: ";
            if ($vectorStoreIdMain) {
                $systemParts .= "основная=" . $vectorStoreIdMain;
            }
            if ($vectorStoreIdObjections) {
                $systemParts .= ($vectorStoreIdMain ? ", " : "") . "возражения=" . $vectorStoreIdObjections;
            }
            $systemParts .= ". Используй факты из базы знаний. Не выдумывай.";
        }

        $messages[] = [
            'role' => 'system',
            'content' => $systemParts,
        ];
        foreach ($history as $msg) {
            if (!isset($msg['role'], $msg['content'])) {
                continue;
            }
            $messages[] = [
                'role' => $msg['role'],
                'content' => (string) $msg['content'],
            ];
        }

        $modelName = $model ?? 'gpt-4o';
        
        $payload = [
            'model' => $modelName,
            'messages' => $messages,
            'max_completion_tokens' => $maxTokens ?? 2000,
        ];

        // Добавляем temperature и topP если они указаны
        if ($temperature !== null) {
            $payload['temperature'] = $temperature;
        }
        if ($topP !== null) {
            $payload['top_p'] = $topP;
        }

        try {
            $response = $this->getHttpClient()
                ->connectTimeout(10)
                ->retry(2, 1000)
                ->post("{$this->getBaseUrl()}/chat/completions", $payload);

            if ($response->successful()) {
                $content = trim($response->json('choices.0.message.content', ''));
                $responseId = $response->json('id');
                $usage = [
                    'prompt_tokens' => (int) ($response->json('usage.prompt_tokens') ?? 0),
                    'completion_tokens' => (int) ($response->json('usage.completion_tokens') ?? 0),
                ];

                if ($content === '') {
                    Log::warning('OpenAI chat/completions вернул пустой контент', [
                        'response_id' => $responseId,
                        'choices' => $response->json('choices'),
                        'usage' => $usage,
                    ]);
                }

                return [
                    'content' => $content,
                    'response_id' => $responseId,
                    'usage' => $usage,
                ];
            }

            Log::error('OpenAI chat failed ошибка', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        } catch (\Throwable $e) {
            Log::error('OpenAI chat exception ошибка', [ 'error' => $e->getMessage() ]);
        }

        return [
            'content' => '',
            'response_id' => null,
            'usage' => ['prompt_tokens' => 0, 'completion_tokens' => 0],
        ];
    }

    /**
     * Подстановка переменных в промпт из контекста
     * 
     * Заменяет плейсхолдеры вида ${variable_name} или {variable_name} на значения из массива контекста.
     * Например: "${stateOwnerNameClean}" заменится на значение из $context['stateOwnerNameClean']
     * 
     * @param string $prompt Промпт с плейсхолдерами
     * @param array $context Ассоциативный массив переменных для подстановки
     * 
     * @return string Промпт с подставленными значениями
     */
    public function replacePromptVariables(string $prompt, array $context): string
    {
        $result = $prompt;
        
        foreach ($context as $key => $value) {
            // Преобразуем значение в строку
            $stringValue = is_array($value) || is_object($value) 
                ? json_encode($value, JSON_UNESCAPED_UNICODE) 
                : (string) $value;
            
            // Заменяем плейсхолдеры в разных форматах: ${key}, {key}
            $result = str_replace('${' . $key . '}', $stringValue, $result);
            $result = str_replace('{' . $key . '}', $stringValue, $result);
        }
        
        return $result;
    }

    /**
     * Поиск в векторном хранилище
     * 
     * Выполняет семантический поиск в указанном векторном хранилище OpenAI.
     * 
     * @param string $vectorStoreId ID векторного хранилища (например: 'vs_abc123')
     * @param string $query Поисковый запрос
     * @param int $maxResults Максимальное количество результатов (по умолчанию 10)
     * 
     * @return array Массив результатов: [['file_id' => string, 'filename' => string, 'score' => float], ...]
     */
    public function searchVectorStore(string $vectorStoreId, string $query, int $maxResults = 10): array
    {
        try {
            $payload = [
                'query' => $query,
                'max_num_results' => $maxResults,
            ];

            $response = $this->getHttpClient()
                ->post("{$this->getBaseUrl()}/vector_stores/{$vectorStoreId}/search", $payload);

            if ($response->successful()) {
                $data = $response->json('data', []);
                
                $results = [];
                foreach ($data as $item) {
                    $results[] = [
                        'file_id' => $item['file_id'] ?? '',
                        'filename' => $item['filename'] ?? '',
                        'score' => (float) ($item['score'] ?? 0.0),
                    ];
                }

                Log::info('✅ OpenAI Vector Store поиск успешен', [
                    'vector_store_id' => $vectorStoreId,
                    'query_length' => mb_strlen($query),
                    'results_count' => count($results),
                ]);

                return $results;
            }

            Log::error('OpenAI Vector Store поиск failed', [
                'vector_store_id' => $vectorStoreId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

        } catch (\Throwable $e) {
            Log::error('OpenAI Vector Store поиск exception', [
                'vector_store_id' => $vectorStoreId,
                'error' => $e->getMessage()
            ]);
        }

        return [];
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

