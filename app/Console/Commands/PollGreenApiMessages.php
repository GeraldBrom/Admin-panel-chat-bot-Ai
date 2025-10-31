<?php

namespace App\Console\Commands;

use App\Services\DialogService;
use App\Services\GreenApiService;
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

    public function handle(GreenApiService $greenApiService, DialogService $dialogService): int
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
}


