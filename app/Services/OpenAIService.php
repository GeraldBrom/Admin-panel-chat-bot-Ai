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

        // Proxy support via .env (USE_PROXY, PROXY_HOST, PROXY_PORT)
        if ($this->useProxy && $this->proxyHost && $this->proxyPort) {
            $proxyUri = sprintf('socks5://%s:%s', $this->proxyHost, $this->proxyPort);
            $client = $client->withOptions([
                'proxy' => $proxyUri,
            ]);
        }

        return $client;
    }

    /**
     * Clean owner name from raw data
     */
    public function cleanOwnerName(string $rawName): string
    {
        try {
            $response = $this->getHttpClient()->post("{$this->getBaseUrl()}/chat/completions", [
                'model' => 'gpt-4o',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => "Ты - помощник для очистки имен от лишних символов и сокращений.\n\n" .
                            "Твоя задача - извлечь только чистое имя человека, убрав все лишнее:\n" .
                            "- Сокращения (соб, др., собст., собственник, владелец и т.п.)\n" .
                            "- Фразы приветствия (добрый день, здравствуйте, привет и т.п.)\n" .
                            "- Знаки препинания и специальные символы (кроме дефиса в составных именах)\n" .
                            "- Лишние пробелы\n" .
                            "- Цифры (если они не часть имени)\n" .
                            "- Скобки и их содержимое\n\n" .
                            "Примеры:\n" .
                            '"Анна соб" → "Анна"' . "\n" .
                            '"Анна соб, добрый день!" → "Анна"' . "\n" .
                            '"Иван др." → "Иван"' . "\n" .
                            '"Мария 123" → "Мария"' . "\n" .
                            '"Петр (соб)" → "Петр"' . "\n\n" .
                            'Верни ТОЛЬКО очищенное имя, без дополнительных пояснений. ' .
                            'Если имя состоит из нескольких слов (имя и фамилия), сохраняй оба.',
                    ],
                    [
                        'role' => 'user',
                        'content' => "Очисти следующее имя от лишних символов и сокращений:\n\n\"{$rawName}\"",
                    ],
                ],
                'temperature' => 0.1,
                'max_tokens' => 50,
            ]);

            if ($response->successful()) {
                $cleanedName = trim($response->json('choices.0.message.content', $rawName));
                Log::info("Name cleaned: \"{$rawName}\" → \"{$cleanedName}\"");
                return $cleanedName;
            }

            Log::error('OpenAI cleanOwnerName failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return $rawName;
        } catch (\Exception $e) {
            Log::error('OpenAI cleanOwnerName error', [
                'error' => $e->getMessage(),
            ]);
            return $rawName;
        }
    }

    /**
     * Analyze user response (positive/negative/neutral)
     */
    public function analyzeResponse(string $responseText, ?string $systemPrompt = null, ?float $temperature = null, ?int $maxTokens = null): ?bool
    {
        try {
            $response = $this->getHttpClient()->post("{$this->getBaseUrl()}/chat/completions", [
                'model' => 'gpt-4o',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $systemPrompt ?? (
                            "Ты - помощник для анализа ответов клиентов в бизнес-диалоге.\n\n" .
                            "Определи, является ли ответ положительным (согласие, подтверждение) или отрицательным (отказ, несогласие).\n\n" .
                            "ВАЖНО: Если клиент отвечает кратко в бизнес-диалоге, это обычно означает согласие.\n\n" .
                            "ПОЛОЖИТЕЛЬНЫЕ: да, согласен, верно, правильно, хорошо, ок, понял, ладно, давай\n" .
                            "ОТРИЦАТЕЛЬНЫЕ: нет, не согласен, неверно, неправильно, не надо, не хочу\n" .
                            "НЕЙТРАЛЬНЫЕ: ТОЛЬКО приветствия без ответа, благодарности без ответа, вопросы\n\n" .
                            "Отвечай ТОЛЬКО одним словом:\n" .
                            "- 'true' если ПОЛОЖИТЕЛЬНЫЙ\n" .
                            "- 'false' если ОТРИЦАТЕЛЬНЫЙ\n" .
                            "- 'neutral' если НЕЙТРАЛЬНЫЙ\n\n" .
                            "Не добавляй пояснений."
                        ),
                    ],
                    [
                        'role' => 'user',
                        'content' => "Проанализируй следующий ответ клиента:\n\n\"{$responseText}\"",
                    ],
                ],
                'temperature' => $temperature ?? 0.2,
                'max_tokens' => $maxTokens ?? 10,
            ]);

            if ($response->successful()) {
                $result = strtolower(trim($response->json('choices.0.message.content', '')));

                if (str_contains($result, 'neutral')) {
                    return null;
                }

                if (str_contains($result, 'true')) {
                    return true;
                }

                if (str_contains($result, 'false')) {
                    return false;
                }

                return null;
            }

            return null;
        } catch (\Exception $e) {
            Log::error('OpenAI analyzeResponse error', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Check if message is an objection
     */
    public function isObjection(string $message, ?string $systemPrompt = null, ?float $temperature = null, ?int $maxTokens = null): bool
    {
        try {
            $response = $this->getHttpClient()->post("{$this->getBaseUrl()}/chat/completions", [
                'model' => 'gpt-4o',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $systemPrompt ?? (
                            "Ты - помощник для анализа сообщений клиентов в контексте аренды недвижимости.\n\n" .
                            "ВОЗРАЖЕНИЯ:\n" .
                            "- Сомнения о комиссии (большая комиссия, высокий процент)\n" .
                            "- Вопросы о качестве услуг (зачем агентство, я сам найду)\n" .
                            "- Недоверие (вы точно сдадите, были проблемы раньше)\n" .
                            "- Мягкий отказ с сомнением (не уверен, подумаю, не знаю)\n" .
                            "- Отказ с причиной или сомнением\n\n" .
                            "НЕ ВОЗРАЖЕНИЯ:\n" .
                            "- Простые информационные вопросы (какой адрес, когда созвонимся)\n" .
                            "- Явное согласие (да, хорошо, согласен, давайте)\n" .
                            "- Второй отказ и более или жесткий отказ БЕЗ объяснений\n" .
                            "- Приветствия и благодарности без контекста\n\n" .
                            "ВАЖНО: Если клиент объясняет причину отказа или выражает сомнение - это ВОЗРАЖЕНИЕ!\n\n" .
                            "Отвечай ТОЛЬКО 'true' или 'false'"
                        ),
                    ],
                    [
                        'role' => 'user',
                        'content' => "Проанализируй сообщение:\n\n\"{$message}\"\n\nЭто возражение?",
                    ],
                ],
                'temperature' => $temperature ?? 0.3,
                'max_tokens' => $maxTokens ?? 10,
            ]);

            if ($response->successful()) {
                $result = strtolower(trim($response->json('choices.0.message.content', '')));
                return str_contains($result, 'true');
            }

            return false;
        } catch (\Exception $e) {
            Log::error('OpenAI isObjection error', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Handle objection with RAG (File Search)
     */
    public function handleObjection(string $objection): string
    {
        // TODO: Implement RAG with Vector Store
        // This requires beta API for assistants
        Log::info("Handling objection: {$objection}");

        // Fallback response
        return 'Понимаю ваши сомнения. Давайте я свяжу вас с нашим специалистом, который подробно ответит на все вопросы.';
    }
}

