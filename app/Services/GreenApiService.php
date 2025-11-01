<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GreenApiService
{
    private string $baseUrl;
    private string $idInstance;
    private string $apiToken;

    public function __construct()
    {
        $this->baseUrl = config('services.greenapi.url', 'https://1105.api.green-api.com');
        $this->idInstance = config('services.greenapi.id_instance');
        $this->apiToken = config('services.greenapi.api_token');
    }

    /**
     * Send message via WhatsApp
     */
    public function sendMessage(string $chatId, string $message): array
    {
        $url = "{$this->baseUrl}/waInstance{$this->idInstance}/sendMessage/{$this->apiToken}";
        
        try {
            $response = Http::timeout(30)
                ->connectTimeout(10)
                ->retry(3, 1000)
                ->withOptions([
                    'curl' => [
                        CURLOPT_DNS_CACHE_TIMEOUT => 300,
                        CURLOPT_TCP_KEEPALIVE => 1,
                        CURLOPT_TCP_KEEPIDLE => 120,
                        CURLOPT_TCP_KEEPINTVL => 60,
                        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4, // Использовать только IPv4
                        CURLOPT_DNS_USE_GLOBAL_CACHE => false, // Отключить глобальный DNS кэш
                        CURLOPT_NOSIGNAL => 1, // Избежать проблем с потоками
                    ],
                ])
                ->post($url, [
                    'chatId' => $chatId,
                    'message' => $message,
                ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('GreenAPI sendMessage failed', [
                'chatId' => $chatId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \Exception("Failed to send message: {$response->status()}");
        } catch (\Exception $e) {
            Log::error('GreenAPI sendMessage error', [
                'chatId' => $chatId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get last incoming messages
     */
    public function getLastIncomingMessages(int $minutes = 1): array
    {
        $url = "{$this->baseUrl}/waInstance{$this->idInstance}/lastIncomingMessages/{$this->apiToken}";

        try {
            // Нормализуем параметр minutes в допустимые рамки [1..60]
            $minutes = max(1, min(60, (int) $minutes));

            $response = Http::acceptJson()
                ->timeout(10)
                ->connectTimeout(5)
                ->retry(2, 200)
                ->withOptions([
                    'curl' => [
                        CURLOPT_DNS_CACHE_TIMEOUT => 300,
                        CURLOPT_TCP_KEEPALIVE => 1,
                        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                        CURLOPT_DNS_USE_GLOBAL_CACHE => false,
                        CURLOPT_NOSIGNAL => 1,
                    ],
                ])
                ->get($url, [
                    'minutes' => $minutes,
                ]);

            if (!$response->successful()) {
                Log::error('GreenAPI getLastIncomingMessages failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'url' => $url,
                    'minutes' => $minutes,
                ]);
                return [];
            }

            $data = $response->json();

            // Вариант 1: API возвращает массив сообщений
            if (is_array($data) && array_is_list($data)) {
                return $data;
            }

            // Вариант 2: API возвращает объект с ключом messages
            if (is_array($data) && isset($data['messages']) && is_array($data['messages'])) {
                return $data['messages'];
            }

            Log::warning('GreenAPI getLastIncomingMessages: unexpected response structure', [
                'parsed' => $data,
                'url' => $url,
                'minutes' => $minutes,
            ]);
            return [];
        } catch (\Exception $e) {
            Log::error('GreenAPI getLastIncomingMessages error', [
                'error' => $e->getMessage(),
                'url' => $url,
                'minutes' => $minutes,
            ]);
            return [];
        }
    }

    /**
     * Receive notification (webhook method)
     */
    public function receiveNotification(): ?array
    {
        $url = "{$this->baseUrl}/waInstance{$this->idInstance}/receiveNotification/{$this->apiToken}";

        try {
            $response = Http::timeout(10)
                ->connectTimeout(5)
                ->withOptions([
                    'curl' => [
                        CURLOPT_DNS_CACHE_TIMEOUT => 300,
                        CURLOPT_TCP_KEEPALIVE => 1,
                        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                        CURLOPT_DNS_USE_GLOBAL_CACHE => false,
                        CURLOPT_NOSIGNAL => 1,
                    ],
                ])
                ->get($url);

            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['receiptId'])) {
                    // Delete notification after processing
                    $this->deleteNotification($data['receiptId']);
                }

                return $data['body'] ?? null;
            }

            return null;
        } catch (\Exception $e) {
            Log::error('GreenAPI receiveNotification error', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Delete processed notification
     */
    private function deleteNotification(string $receiptId): void
    {
        $url = "{$this->baseUrl}/waInstance{$this->idInstance}/deleteNotification/{$this->apiToken}/{$receiptId}";
        
        try {
            Http::timeout(5)
                ->connectTimeout(3)
                ->withOptions([
                    'curl' => [
                        CURLOPT_DNS_CACHE_TIMEOUT => 300,
                        CURLOPT_TCP_KEEPALIVE => 1,
                        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                        CURLOPT_DNS_USE_GLOBAL_CACHE => false,
                        CURLOPT_NOSIGNAL => 1,
                    ],
                ])
                ->delete($url);
        } catch (\Exception $e) {
            Log::error('GreenAPI deleteNotification error', [
                'receiptId' => $receiptId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

