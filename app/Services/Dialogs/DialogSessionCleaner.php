<?php

namespace App\Services\Dialogs;

use App\Models\Dialog;
use App\Models\Message;
use App\Models\BotSession;
use App\Models\Fact;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class DialogSessionCleaner
{
    private const STATE_INITIAL = 'initial';

    /**
     * Очистка активной сессии чата без удаления самой сессии
     * Удаляет все сообщения, чтобы начать новый диалог без контекста
     */
    public function clear(string $chatId): void
    {
        Log::info("🧹 Начинаем очистку сессии для chatId: {$chatId}");

        try {
            // Получаем диалог
            $dialog = Dialog::where('client_id', $chatId)
                ->orWhere('dialog_id', 'like', "%{$chatId}")
                ->first();
            
            if (!$dialog) {
                Log::warning("⚠️ Диалог не найден для chatId: {$chatId}");
                
                // Пытаемся найти все возможные диалоги для отладки
                $allDialogs = Dialog::where('client_id', 'like', "%{$chatId}%")
                    ->orWhere('dialog_id', 'like', "%{$chatId}%")
                    ->get(['dialog_id', 'client_id']);
                
                Log::info("📋 Найденные диалоги для отладки:", [
                    'search_chat_id' => $chatId,
                    'found_dialogs' => $allDialogs->toArray(),
                ]);
                
                return;
            }

            Log::info("📍 Диалог найден", [
                'dialog_id' => $dialog->dialog_id,
                'client_id' => $dialog->client_id,
            ]);

            // Подсчитываем количество сообщений ДО удаления
            $messagesCountBefore = Message::where('dialog_id', $dialog->dialog_id)->count();
            $factsCountBefore = Fact::where('dialog_id', $dialog->dialog_id)->count();
            
            Log::info("📊 Перед удалением", [
                'messages' => $messagesCountBefore,
                'facts' => $factsCountBefore,
            ]);

            // Удаляем все сообщения диалога
            $deletedMessagesCount = Message::where('dialog_id', $dialog->dialog_id)->delete();
            
            // Удаляем все факты диалога
            $deletedFactsCount = Fact::where('dialog_id', $dialog->dialog_id)->delete();
            
            // Проверяем количество ПОСЛЕ удаления
            $messagesCountAfter = Message::where('dialog_id', $dialog->dialog_id)->count();
            $factsCountAfter = Fact::where('dialog_id', $dialog->dialog_id)->count();

            Log::info("📊 После удаления", [
                'messages' => $messagesCountAfter,
                'facts' => $factsCountAfter,
            ]);

            // Очищаем summary и provider_conversation_id
            $dialog->update([
                'summary' => null,
                'provider_conversation_id' => null,
                'current_state' => self::STATE_INITIAL,
            ]);

            // Получаем сессию и обнуляем dialog_state
            $session = BotSession::where('chat_id', $chatId)->first();
            if ($session) {
                $session->update([
                    'dialog_state' => ['state' => self::STATE_INITIAL],
                ]);
            }

            // Очищаем кеш буфера сообщений
            $bufferKey = "message_buffer_{$chatId}";
            $processingKey = "processing_scheduled_{$chatId}";
            Cache::forget($bufferKey);
            Cache::forget($processingKey);

            Log::info("✅ Сессия успешно очищена", [
                'chatId' => $chatId,
                'dialog_id' => $dialog->dialog_id,
                'deleted_messages' => $deletedMessagesCount,
                'deleted_facts' => $deletedFactsCount,
                'messages_remaining' => $messagesCountAfter,
                'facts_remaining' => $factsCountAfter,
            ]);

        } catch (\Exception $e) {
            Log::error("❌ Ошибка при очистке сессии", [
                'chatId' => $chatId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}

