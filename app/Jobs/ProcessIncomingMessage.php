<?php

namespace App\Jobs;

use App\Services\DialogService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessIncomingMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $chatId,
        public string $messageText,
        public array $meta = []
    ) {}

    /**
     * Execute the job
     */
    public function handle(DialogService $dialogService): void
    {
        Log::info("Processing incoming message job", [
            'chatId' => $this->chatId,
            'message' => substr($this->messageText, 0, 50),
        ]);

        try {
            $dialogService->processIncomingMessage(
                $this->chatId,
                $this->messageText,
                $this->meta
            );
        } catch (\Exception $e) {
            Log::error("Error processing incoming message", [
                'chatId' => $this->chatId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure
     */
    public function failed(?\Throwable $exception): void
    {
        Log::error("Job failed: ProcessIncomingMessage", [
            'chatId' => $this->chatId,
            'error' => $exception?->getMessage(),
        ]);
    }
}

