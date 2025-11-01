<?php

namespace App\Jobs;

use App\Services\DialogService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessGreenApiWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var array<mixed> */
    private array $payload;

    /**
     * Create a new job instance.
     *
     * @param array<mixed> $payload
     */
    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    public function handle(DialogService $dialogService): void
    {
        try {
            $processed = 0;

            if (isset($this->payload['messages']) && is_array($this->payload['messages'])) {
                foreach ($this->payload['messages'] as $message) {
                    $normalized = $this->normalizeMessage(is_array($message) ? $message : []);
                    if (!$normalized) {
                        continue;
                    }
                    $dialogService->processIncomingMessage(
                        $normalized['chatId'],
                        $normalized['messageText'],
                        $normalized['meta']
                    );
                    $processed++;
                }
            } else {
                $message = $this->payload['message'] ?? $this->payload['body'] ?? $this->payload;
                $normalized = $this->normalizeMessage(is_array($message) ? $message : []);
                if ($normalized) {
                    $dialogService->processIncomingMessage(
                        $normalized['chatId'],
                        $normalized['messageText'],
                        $normalized['meta']
                    );
                    $processed++;
                }
            }

            Log::info('[GreenAPI Webhook Job] Processed messages', [
                'processed' => $processed,
            ]);
        } catch (\Throwable $e) {
            Log::error('[GreenAPI Webhook Job] Failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $this->payload,
            ]);
        }
    }

    /**
     * @param array<mixed> $message
     * @return array{chatId:string,messageText:string,meta:array<mixed>}|null
     */
    private function normalizeMessage(array $message = null): ?array
    {
        if (!$message) {
            return null;
        }

        $chatId = $message['chatId']
            ?? ($message['senderData']['chatId'] ?? null);

        $messageText = $message['textMessage']
            ?? ($message['messageData']['textMessageData']['textMessage'] ?? null);

        if (!$chatId || !$messageText) {
            return null;
        }

        $meta = [
            'messageId' => $message['idMessage'] ?? null,
            'timestamp' => $message['timestamp'] ?? null,
            'typeMessage' => $message['typeMessage'] ?? ($message['messageData']['typeMessage'] ?? null),
            'raw' => $message,
        ];

        return [
            'chatId' => $chatId,
            'messageText' => $messageText,
            'meta' => $meta,
        ];
    }
}


