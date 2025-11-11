<?php

namespace App\Services\Messaging;

class TemplateRenderer
{
    /**
     * Рендеринг {placeholders} в шаблоне с предоставленными переменными
     */
    public function render(string $template, array $vars): string
    {
        $result = $template;
        foreach ($vars as $key => $value) {
            $result = str_replace('{' . $key . '}', (string) $value, $result);
        }
        return $result;
    }
}

