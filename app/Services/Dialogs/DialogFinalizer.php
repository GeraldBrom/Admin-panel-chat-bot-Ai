<?php

namespace App\Services\Dialogs;

use App\Models\Dialog;
use App\Models\Message;
use App\Models\BotSession;
use App\Models\Fact;
use App\Services\Extraction\FactExtractor;
use Illuminate\Support\Facades\Log;

class DialogFinalizer
{
    private const STATE_COMPLETED = 'completed';

    public function __construct(
        private FactExtractor $factExtractor,
        private DialogSummaryService $dialogSummaryService,
    ) {}

    /**
     * Финализация диалога при остановке бота
     * Вызывается когда бот останавливается - извлекает все факты и генерирует резюме
     */
    public function finalize(string $chatId): void
    {
        Log::info("🏁 Начинаем финализацию диалога для chatId: {$chatId}");

        try {
            // Получаем диалог
            $dialog = Dialog::where('client_id', $chatId)->orWhere('dialog_id', 'like', "%{$chatId}")->first();
            
            if (!$dialog) {
                Log::warning("Диалог не найден для chatId: {$chatId}");
                return;
            }

            // Получаем все сообщения пользователя для извлечения фактов
            $userMessages = Message::where('dialog_id', $dialog->dialog_id)
                ->where('role', 'user')
                ->get();

            Log::info("📨 Найдено сообщений пользователя для анализа", [
                'dialog_id' => $dialog->dialog_id,
                'messages_count' => $userMessages->count(),
            ]);

            // Извлекаем факты из каждого сообщения пользователя (если еще не извлечены)
            $factsExtracted = 0;
            foreach ($userMessages as $message) {
                // Проверяем, извлекались ли уже факты из этого сообщения
                $existingFacts = Fact::where('source_message_id', $message->id)->count();
                
                if ($existingFacts === 0) {
                    Log::info("🔍 Извлекаем факты из сообщения #{$message->id}");
                    $this->factExtractor->extractFromMessage($dialog, $message);
                    $factsExtracted++;
                }
            }

            Log::info("✅ Факты извлечены из сообщений", [
                'dialog_id' => $dialog->dialog_id,
                'processed_messages' => $factsExtracted,
            ]);

            // Генерируем финальное резюме диалога (независимо от количества сообщений)
            if ($userMessages->count() > 0) {
                Log::info("📝 Генерируем финальное резюме диалога");
                $this->dialogSummaryService->generate($dialog, true); // true = принудительная генерация
            }

            // Собираем статистику для metadata
            $totalFacts = Fact::where('dialog_id', $dialog->dialog_id)->count();
            $totalMessages = Message::where('dialog_id', $dialog->dialog_id)->count();
            $userMessagesCount = Message::where('dialog_id', $dialog->dialog_id)
                ->where('role', 'user')
                ->count();

            // Обновляем статус диалога и добавляем статистику в metadata
            $currentMetadata = $dialog->metadata ?? [];
            $dialog->update([
                'current_state' => self::STATE_COMPLETED,
                'metadata' => array_merge($currentMetadata, [
                    'finalized_at' => now()->toIso8601String(),
                    'total_messages' => $totalMessages,
                    'user_messages' => $userMessagesCount,
                    'total_facts' => $totalFacts,
                    'has_summary' => !empty($dialog->summary),
                ]),
            ]);

            // Обновляем metadata в сессии бота
            $session = BotSession::where('chat_id', $chatId)->first();
            if ($session) {
                $sessionMetadata = $session->metadata ?? [];
                $session->update([
                    'metadata' => array_merge($sessionMetadata, [
                        'finalized_at' => now()->toIso8601String(),
                        'total_messages' => $totalMessages,
                        'user_messages' => $userMessagesCount,
                        'total_facts' => $totalFacts,
                        'has_summary' => !empty($dialog->summary),
                    ]),
                ]);
            }

            Log::info("🎉 Диалог успешно финализирован", [
                'dialog_id' => $dialog->dialog_id,
                'total_facts' => $totalFacts,
                'total_messages' => $totalMessages,
                'has_summary' => !empty($dialog->summary),
            ]);

        } catch (\Exception $e) {
            Log::error("❌ Ошибка при финализации диалога", [
                'chatId' => $chatId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}

