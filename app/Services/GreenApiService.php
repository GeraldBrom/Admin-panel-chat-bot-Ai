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
            $response = Http::post($url, [
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
            $response = Http::get($url, [
                'minutes' => $minutes,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return is_array($data) ? $data : [];
            }

            Log::error('GreenAPI getLastIncomingMessages failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [];
        } catch (\Exception $e) {
            Log::error('GreenAPI getLastIncomingMessages error', [
                'error' => $e->getMessage(),
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
            $response = Http::get($url);

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
            Http::delete($url);
        } catch (\Exception $e) {
            Log::error('GreenAPI deleteNotification error', [
                'receiptId' => $receiptId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

