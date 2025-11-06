<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScenarioStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'scenario_bot_id',
        'name',
        'message',
        'step_type',
        'options',
        'next_step_id',
        'condition',
        'position_x',
        'position_y',
        'order',
    ];

    protected $casts = [
        'options' => 'array',
        'next_step_id' => 'integer',
        'scenario_bot_id' => 'integer',
        'position_x' => 'integer',
        'position_y' => 'integer',
        'order' => 'integer',
    ];

    /**
     * Получить бота, к которому относится шаг
     */
    public function scenarioBot(): BelongsTo
    {
        return $this->belongsTo(ScenarioBot::class);
    }

    /**
     * Получить следующий шаг по умолчанию
     */
    public function nextStep(): BelongsTo
    {
        return $this->belongsTo(ScenarioStep::class, 'next_step_id');
    }

    /**
     * Получить все шаги, которые ссылаются на этот шаг
     */
    public function previousSteps(): HasMany
    {
        return $this->hasMany(ScenarioStep::class, 'next_step_id');
    }

    /**
     * Определить следующий шаг на основе пользовательского ввода
     */
    public function determineNextStep(string $userInput): ?int
    {
        // Если есть варианты ответов, ищем совпадение
        if ($this->options && is_array($this->options)) {
            foreach ($this->options as $option) {
                // Проверяем точное совпадение или содержание
                $optionText = $option['text'] ?? '';
                if (
                    strcasecmp(trim($userInput), trim($optionText)) === 0 ||
                    stripos(trim($userInput), trim($optionText)) !== false
                ) {
                    return $option['next_step_id'] ?? $this->next_step_id;
                }
            }
        }

        // Если есть условие, проверяем его
        if ($this->condition) {
            if ($this->evaluateCondition($userInput)) {
                return $this->next_step_id;
            }
        }

        // Возвращаем следующий шаг по умолчанию
        return $this->next_step_id;
    }

    /**
     * Оценить условие перехода
     */
    protected function evaluateCondition(string $userInput): bool
    {
        if (!$this->condition) {
            return true;
        }

        // Простые условия: contains:текст, equals:текст, regex:паттерн
        [$type, $value] = array_pad(explode(':', $this->condition, 2), 2, '');

        return match ($type) {
            'contains' => stripos($userInput, $value) !== false,
            'equals' => strcasecmp(trim($userInput), trim($value)) === 0,
            'regex' => (bool) preg_match('/' . $value . '/i', $userInput),
            default => true,
        };
    }

    /**
     * Scope: Получить шаги по типу
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('step_type', $type);
    }
}

