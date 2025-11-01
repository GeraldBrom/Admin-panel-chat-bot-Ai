<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAIService
{
    private string $apiKey;
    private string $vectorStoreId;
    private bool $useProxy;
    private ?string $proxyHost;
    private ?string $proxyPort;

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key');
        $this->vectorStoreId = config('services.openai.vector_store_id');
        $this->useProxy = config('services.openai.use_proxy', false);
        $this->proxyHost = config('services.openai.proxy_host');
        $this->proxyPort = config('services.openai.proxy_port');
    }

    /**
     * Chat using Responses API with File Search (RAG)
     * Returns array: ['content' => string, 'response_id' => string|null, 'usage' => ['prompt_tokens'=>int,'completion_tokens'=>int]]
     */
    public function chatWithRag(
        string $systemPrompt,
        array $history,
        ?float $temperature = null,
        ?int $maxTokens = null,
        array $vectorStoreIds = []
    ): array {
        // Build Responses API input
        $input = [];
        // Augment system prompt with vector store context (API-side attachment may be unavailable)
        if (!empty($vectorStoreIds)) {
            $systemPrompt .= "\n\nИспользуй знания из следующих векторных баз (если доступны на стороне провайдера): " . implode(', ', $vectorStoreIds) . ". Отвечай только фактами, не выдумывай.";
        }
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

        $payload = [
            'model' => 'gpt-5-2025-08-07',
            'input' => $input,
            'max_output_tokens' => $maxTokens ?? 500,
            'service_tier' => 'flex',
        ];

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

                // Extract text from Responses API structure
                $content = '';
                $output = $response->json('output') ?? [];
                
                // Новый формат: output[0].content = строка или массив с type: output_text
                if (is_array($output) && !empty($output)) {
                    $firstOutput = $output[0] ?? [];
                    $outputContent = $firstOutput['content'] ?? '';
                    
                    if (is_string($outputContent)) {
                        $content = trim($outputContent);
                    } elseif (is_array($outputContent)) {
                        // Ищем output_text
                        foreach ($outputContent as $item) {
                            if (isset($item['type']) && $item['type'] === 'output_text' && isset($item['text'])) {
                                $content = trim($item['text']);
                                break;
                            }
                        }
                    }
                }

                $usage = [
                    'prompt_tokens' => (int) ($response->json('usage.input_tokens') ?? $response->json('usage.prompt_tokens') ?? 0),
                    'completion_tokens' => (int) ($response->json('usage.output_tokens') ?? $response->json('usage.completion_tokens') ?? 0),
                ];

                return [
                    'content' => $content,
                    'response_id' => $responseId,
                    'usage' => $usage,
                ];
            }

            Log::error('OpenAI chatWithRag failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        } catch (\Illuminate\Http\Client\RequestException | \Illuminate\Http\Client\ConnectionException $e) {
            // Таймаут или проблемы с соединением — сразу фолбэк на обычный chat
            Log::warning('OpenAI Responses API timeout/connection error, falling back to chat/completions', [
                'error' => $e->getMessage()
            ]);
            return $this->chat($systemPrompt, $history, $temperature, $maxTokens);
        } catch (\Throwable $e) {
            Log::error('OpenAI chatWithRag exception', [ 'error' => $e->getMessage() ]);
        }

        // Fallback to standard chat completion with the same (augmented) system prompt
        return $this->chat($systemPrompt, $history, $temperature, $maxTokens);
    }

    /**
     * Single chat entrypoint: system prompt + history → assistant reply
     * Returns array: ['content' => string, 'response_id' => string|null, 'usage' => ['prompt_tokens'=>int,'completion_tokens'=>int]]
     */
    public function chat(string $systemPrompt, array $history, ?float $temperature = null, ?int $maxTokens = null, ?string $vectorStoreIdMain = null, ?string $vectorStoreIdObjections = null): array
    {
        // history: [['role' => 'user'|'assistant', 'content' => '...'], ...]
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

        $payload = [
            'model' => 'gpt-5-2025-08-07',
            'messages' => $messages,
            'max_completion_tokens' => $maxTokens ?? 500,
            'service_tier' => 'flex',
        ];
        // gpt-5 не поддерживает произвольное значение temperature — используем дефолт (не передаём параметр)
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

                return [
                    'content' => $content,
                    'response_id' => $responseId,
                    'usage' => $usage,
                ];
            }

            Log::error('OpenAI chat failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        } catch (\Throwable $e) {
            Log::error('OpenAI chat exception', [ 'error' => $e->getMessage() ]);
        }

        return [
            'content' => '',
            'response_id' => null,
            'usage' => ['prompt_tokens' => 0, 'completion_tokens' => 0],
        ];
    }

    /**
     * Get base URL for OpenAI API
     */
    private function getBaseUrl(): string
    {
        return 'https://api.openai.com/v1';
    }

    /**
     * Get HTTP client with optional proxy
     */
    private function getHttpClient()
    {
        $client = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type' => 'application/json',
        ]);

        $options = [
            'timeout' => 60, // 60 секунд таймаут для медленных запросов
            'connect_timeout' => 15, // Увеличен таймаут подключения
            'curl' => [
                CURLOPT_DNS_CACHE_TIMEOUT => 300,
                CURLOPT_TCP_KEEPALIVE => 1,
                CURLOPT_TCP_KEEPIDLE => 120,
                CURLOPT_TCP_KEEPINTVL => 60,
                CURLOPT_FRESH_CONNECT => false, // Использовать пул соединений
                CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4, // Использовать только IPv4
                CURLOPT_DNS_USE_GLOBAL_CACHE => false, // Отключить глобальный DNS кэш
                CURLOPT_NOSIGNAL => 1, // Избежать проблем с потоками (КРИТИЧНО!)
                CURLOPT_FORBID_REUSE => 0, // Разрешить переиспользование соединений
                // Прямой резолв DNS для OpenAI API (решает проблему getaddrinfo() thread)
                CURLOPT_RESOLVE => [
                    'api.openai.com:443:104.18.7.192',
                    'api.openai.com:443:104.18.6.192',
                ],
            ],
        ];

        // Proxy support via .env (USE_PROXY, PROXY_HOST, PROXY_PORT)
        if ($this->useProxy && $this->proxyHost && $this->proxyPort) {
            $proxyUri = sprintf('socks5://%s:%s', $this->proxyHost, $this->proxyPort);
            $options['proxy'] = $proxyUri;
        }

        return $client->withOptions($options);
    }

    // Removed legacy helper methods: cleanOwnerName, analyzeResponse, isObjection, handleObjection
}

