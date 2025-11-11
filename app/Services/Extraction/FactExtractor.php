<?php

namespace App\Services\Extraction;

use App\Models\Dialog;
use App\Models\Message;
use App\Models\Fact;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\Log;

class FactExtractor
{
    public function __construct(
        private OpenAIService $openAIService
    ) {}

    /**
     * Извлечение ключевых фактов из сообщения пользователя
     */
    public function extractFromMessage(Dialog $dialog, Message $message): void
    {
        try {
            // Извлекаем факты только из сообщений пользователя
            if ($message->role !== 'user') {
                return;
            }

            $messageText = $message->content;
            
            // Промпт для извлечения фактов
            $extractionPrompt = "Проанализируй следующее сообщение клиента и извлеки ключевые факты в формате JSON.\n\n"
                . "Извлекай только ЯВНО указанные факты о:\n"
                . "- Цене недвижимости (ключ: \"price\")\n"
                . "- Количестве комнат (ключ: \"rooms\")\n"
                . "- Площади (ключ: \"area\")\n"
                . "- Этаже (ключ: \"floor\")\n"
                . "- Адресе/районе (ключ: \"location\")\n"
                . "- Дате доступности (ключ: \"available_from\")\n"
                . "- Предпочтениях по арендаторам (ключ: \"tenant_preferences\")\n"
                . "- Контактных данных (ключ: \"contact_info\")\n"
                . "- Особых условиях (ключ: \"special_conditions\")\n\n"
                . "Верни ТОЛЬКО JSON массив объектов формата: [{\"key\": \"название_ключа\", \"value\": \"значение\", \"confidence\": число_от_0_до_1}]\n"
                . "Если фактов нет, верни пустой массив [].\n\n"
                . "Сообщение клиента: \"{$messageText}\"";

            // Используем OpenAI для извлечения фактов
            $result = $this->openAIService->chat(
                'Ты - помощник для извлечения структурированных фактов из текста. Отвечай ТОЛЬКО валидным JSON массивом.',
                [['role' => 'user', 'content' => $extractionPrompt]],
                null, // temperature не используется
                300,
                null,
                null,
                'gpt-4o-mini'
            );

            $responseContent = trim($result['content'] ?? '');
            
            if (empty($responseContent)) {
                return;
            }

            // Очищаем ответ от markdown если есть
            $responseContent = preg_replace('/^```json\s*|\s*```$/s', '', $responseContent);
            $responseContent = trim($responseContent);

            // Парсим JSON
            $extractedFacts = json_decode($responseContent, true);

            if (!is_array($extractedFacts) || empty($extractedFacts)) {
                Log::info("Факты не найдены в сообщении", [
                    'dialog_id' => $dialog->dialog_id,
                    'message_id' => $message->id,
                ]);
                return;
            }

            // Сохраняем каждый факт
            $savedCount = 0;
            foreach ($extractedFacts as $fact) {
                if (!isset($fact['key'], $fact['value'])) {
                    continue;
                }

                // Проверяем, нет ли уже такого факта в диалоге
                $existingFact = Fact::where('dialog_id', $dialog->dialog_id)
                    ->where('key', $fact['key'])
                    ->first();

                $confidence = isset($fact['confidence']) ? (float) $fact['confidence'] : 1.00;
                $confidence = max(0.0, min(1.0, $confidence)); // Ограничиваем 0-1

                if ($existingFact) {
                    // Обновляем факт, если новая уверенность выше
                    if ($confidence >= $existingFact->confidence) {
                        $existingFact->update([
                            'value' => $fact['value'],
                            'source_message_id' => $message->id,
                            'confidence' => $confidence,
                            'discovered_at' => now(),
                        ]);
                        $savedCount++;
                    }
                } else {
                    // Создаем новый факт
                    Fact::create([
                        'dialog_id' => $dialog->dialog_id,
                        'key' => $fact['key'],
                        'value' => $fact['value'],
                        'source_message_id' => $message->id,
                        'confidence' => $confidence,
                        'discovered_at' => now(),
                    ]);
                    $savedCount++;
                }
            }

            if ($savedCount > 0) {
                Log::info("Извлечено и сохранено фактов", [
                    'dialog_id' => $dialog->dialog_id,
                    'message_id' => $message->id,
                    'facts_count' => $savedCount,
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Ошибка при извлечении фактов из сообщения", [
                'dialog_id' => $dialog->dialog_id,
                'message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

