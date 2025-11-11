<?php

namespace App\Services\Messaging;

class MessageFormatter
{
    /**
     * Конвертирует Markdown форматирование в WhatsApp форматирование
     * 
     * Markdown (от GPT):          WhatsApp:
     * **жирный**                  *жирный*
     * *курсив*                    _курсив_
     * ~~зачеркнутый~~             ~зачеркнутый~
     * `код`                       ```код```
     */
    public function convertMarkdownToWhatsApp(string $text): string
    {
        // 1. Конвертируем жирный: **текст** → *текст*
        $text = preg_replace('/\*\*(.+?)\*\*/u', '*$1*', $text);
        
        // 2. Конвертируем курсив Markdown в курсив WhatsApp: *текст* → _текст_
        // Но только если это не жирный текст из предыдущего шага
        // Ищем одиночные звездочки, которые не являются частью жирного текста
        $text = preg_replace('/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/u', '_$1_', $text);
        
        // 3. Конвертируем зачеркнутый: ~~текст~~ → ~текст~
        $text = preg_replace('/~~(.+?)~~/u', '~$1~', $text);
        
        // 4. Конвертируем моноширинный: `код` → ```код```
        $text = preg_replace('/`([^`]+?)`/u', '```$1```', $text);
        
        return $text;
    }
}

