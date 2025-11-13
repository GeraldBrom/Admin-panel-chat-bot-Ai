<?php

namespace App\Services\Dialogs;

use App\Services\Messaging\MessageSender;
use App\Services\Extraction\FactExtractor;
use Illuminate\Support\Facades\Log;
use App\Models\BotSession;
use App\Models\Dialog;
use App\Models\Message;

class IncomingMessageHandler
{
    public function __construct(
        private MessageSender $messageSender,
        private FactExtractor $factExtractor,
        private MessageBufferService $messageBufferService,
    ) {}

    /**
     * Обработка входящего сообщения с буферизацией
     */
    public function handle(string $chatId, string $messageText, array $meta = []): void
    {
        try {
            Log::info("📨 Получено сообщение от chatId: {$chatId}", [
                'message' => $messageText,
            ]);

            // Получить сессию
            $session = BotSession::where('chat_id', $chatId)
                ->where('status', 'running')
                ->first();

            if (!$session) {
                Log::warning("Не найдена активная сессия для chatId: {$chatId}");
                return;
            }

            if ($messageText === '{{SWE001}}') {
                // Получаем диалог для сохранения сообщения
                $dialog = Dialog::getOrCreate($chatId);
                
                $errorMessage = 'Пожалуйста, отправьте сообщение еще раз, я не смог увидеть ваш ответ';
                
                // Отправляем сообщение в WhatsApp
                $this->messageSender->sendWithDelay($chatId, $errorMessage, 0);
                
                // Сохраняем сообщение в БД для отображения на frontend
                Message::create([
                    'dialog_id' => $dialog->dialog_id,
                    'role' => 'assistant',
                    'content' => $errorMessage,
                    'previous_response_id' => null,
                    'tokens_in' => 0,
                    'tokens_out' => 0,
                ]);
                
                return;
            }

            // Получить диалог
            $dialog = Dialog::getOrCreate($chatId);

            // Сохранить сообщение пользователя
            $userMessage = Message::create([
                'dialog_id' => $dialog->dialog_id,
                'role' => 'user',
                'content' => $messageText,
                'meta' => $meta,
            ]);

            // Извлекаем факты из сообщения пользователя
            $this->factExtractor->extractFromMessage($dialog, $userMessage);
            
            // Добавляем сообщение в буфер
            $this->messageBufferService->bufferMessage($chatId, $userMessage->id);
            
            Log::info("✅ Сообщение добавлено в буфер для chatId: {$chatId}");
        } catch (\Throwable $e) {
            Log::error("Ошибка при обработке сообщения для chatId: {$chatId}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'message' => $messageText,
            ]);
        }
    }
}
