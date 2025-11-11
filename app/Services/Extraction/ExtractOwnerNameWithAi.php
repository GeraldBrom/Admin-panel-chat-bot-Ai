<?php

namespace App\Services\Extraction;

use App\Services\OpenAIService;
use Illuminate\Support\Facades\Log;

class ExtractOwnerNameWithAi{

    public function __construct(
        private OpenAIService $openAIService
    ) {} 

     /**
     * Извлечение имени владельца с помощью ИИ
     */
    public function extractOwnerNameWithAI(string $rawName): string
    {
        // Если значение пустое или явно некорректное, возвращаем пустую строку
        $normalized = mb_strtolower(trim($rawName));
        if (
            empty($rawName) || 
            $normalized === '' || 
            $normalized === 'name' || 
            $normalized === 'клиент' ||
            $normalized === 'client'
        ) {
            Log::info("Пропуск извлечения имени - некорректное значение", ['raw_name' => $rawName]);
            return '';
        }

        try {
            Log::info("Извлечение имени владельца через ИИ", ['raw_name' => $rawName]);
            
            // Промпт для извлечения имени (основан на правилах из основного промпта)
            $extractionPrompt = "Из строки \"{$rawName}\" извлеки чистое имя владельца на русском языке.\n\n"
                . "Правила:\n"
                . "1. Удали скобки, кавычки, эмодзи, телефон/почту, теги типа «(собственник)», «ООО», «агент»\n"
                . "2. Удали капслок-приставки, хвосты после «/», «,», «—»\n"
                . "3. Нормализуй пробелы\n"
                . "4. Возьми первое слово, если это русское имя (буквы А-Я, Ё, дефис допустим)\n"
                . "5. Первая буква заглавная, остальные строчные\n"
                . "6. Если имя не найдено — верни пустую строку\n\n"
                . "ВАЖНО: Верни ТОЛЬКО имя (одно слово) или пустую строку. Без объяснений и лишнего текста.";

            // Используем быстрый и дешевый вызов GPT для извлечения имени
            $result = $this->openAIService->chat(
                'Ты - помощник для извлечения имён. Отвечай ТОЛЬКО извлечённым именем или пустой строкой.',
                [['role' => 'user', 'content' => $extractionPrompt]],
                0.0,  // Минимальная temperature для детерминированного результата
                50,   // Максимум 50 токенов (имя должно быть коротким)
                null,
                null,
                'gpt-4o-mini'  // Используем mini модель для экономии
            );

            $extractedName = trim($result['content'] ?? '');
            
            // Проверка: имя должно быть одним словом (или с дефисом) и на кириллице
            if (!empty($extractedName) && preg_match('/^[А-ЯЁ][а-яё]+(?:-[А-ЯЁ][а-яё]+)?$/u', $extractedName)) {
                Log::info("Имя успешно извлечено", [
                    'raw_name' => $rawName,
                    'extracted_name' => $extractedName,
                ]);
                return $extractedName;
            }
            
            Log::warning("ИИ не смогла извлечь корректное имя", [
                'raw_name' => $rawName,
                'ai_response' => $extractedName,
            ]);
            return '';
            
        } catch (\Exception $e) {
            Log::error("Ошибка при извлечении имени через ИИ", [
                'raw_name' => $rawName,
                'error' => $e->getMessage(),
            ]);
            return '';
        }
    }
}

   