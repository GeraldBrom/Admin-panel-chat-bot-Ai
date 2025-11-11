<?php

namespace App\Services\Dialogs;

use App\Models\Dialog;
use App\Models\Message;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\Log;

class DialogSummaryService
{
    public function __construct(
        private OpenAIService $openAIService
    ) {}

    /**
     * Генерация краткого резюме диалога на основе истории сообщений
     * 
     * @param Dialog $dialog Диалог для которого генерируется резюме
     * @param bool $forceGenerate Принудительная генерация даже с малым количеством сообщений
     */
    public function generate(Dialog $dialog, bool $forceGenerate = false): void
    {
        try {
            // Получаем последние сообщения диалога
            $messages = Message::where('dialog_id', $dialog->dialog_id)
                ->orderBy('created_at', 'asc')
                ->get(['role', 'content']);

            // Если сообщений меньше 3 и не принудительная генерация, не генерируем summary
            if (!$forceGenerate && $messages->count() < 3) {
                return;
            }
            
            // При малом количестве сообщений проверяем минимум
            if ($messages->count() === 0) {
                Log::warning("Нет сообщений для генерации резюме", ['dialog_id' => $dialog->dialog_id]);
                return;
            }

            // Формируем контекст для summary
            $conversationText = $messages->map(function ($msg) {
                $roleLabel = $msg->role === 'user' ? 'Клиент' : 'Ассистент';
                return "{$roleLabel}: {$msg->content}";
            })->implode("\n");

            // Создаем промпт для генерации резюме
            $summaryPrompt = "Создай краткое резюме (2-3 предложения) следующего диалога между ассистентом Capital Mars и клиентом. Укажи основные темы, вопросы клиента и текущий статус обсуждения:\n\n{$conversationText}";

            // Используем OpenAI для генерации summary
            $result = $this->openAIService->chat(
                'Ты - помощник, который создает краткие резюме диалогов. Отвечай только кратким резюме.',
                [['role' => 'user', 'content' => $summaryPrompt]],
                null, // temperature не используется
                200, // Максимум 200 токенов для summary
                null,
                null,
                'gpt-4o-mini'
            );

            $summary = trim($result['content'] ?? '');

            if ($summary !== '') {
                $dialog->update(['summary' => $summary]);
                Log::info("Резюме диалога обновлено для dialog_id: {$dialog->dialog_id}", [
                    'summary_length' => mb_strlen($summary),
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Ошибка при генерации резюме диалога для dialog_id: {$dialog->dialog_id}", [
                'error' => $e->getMessage(),
            ]);
        }
    }
}

