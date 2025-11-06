<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScenarioBotSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'scenario_bot_id',
        'chat_id',
        'object_id',
        'platform',
        'current_step_id',
        'status',
        'dialog_data',
        'metadata',
        'started_at',
        'stopped_at',
    ];

    protected $casts = [
        'scenario_bot_id' => 'integer',
        'object_id' => 'integer',
        'current_step_id' => 'integer',
        'dialog_data' => 'array',
        'metadata' => 'array',
        'started_at' => 'datetime',
        'stopped_at' => 'datetime',
    ];

    /**
     * Получить бота сессии
     */
    public function scenarioBot(): BelongsTo
    {
        return $this->belongsTo(ScenarioBot::class);
    }

    /**
     * Получить текущий шаг сессии
     */
    public function currentStep(): BelongsTo
    {
        return $this->belongsTo(ScenarioStep::class, 'current_step_id');
    }

    /**
     * Получить все сообщения сессии
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ScenarioBotMessage::class, 'session_id');
    }

    /**
     * Scope: Получить активные сессии
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'running');
    }

    /**
     * Scope: Получить сессии по chat_id
     */
    public function scopeByChatId($query, string $chatId)
    {
        return $query->where('chat_id', $chatId);
    }

    /**
     * Переместить сессию на следующий шаг
     */
    public function moveToStep(int $stepId): void
    {
        $this->update([
            'current_step_id' => $stepId,
        ]);
    }

    /**
     * Сохранить данные диалога
     */
    public function saveDialogData(string $key, mixed $value): void
    {
        $data = $this->dialog_data ?? [];
        $data[$key] = $value;
        $this->update(['dialog_data' => $data]);
    }

    /**
     * Завершить сессию
     */
    public function complete(): void
    {
        $this->update([
            'status' => 'completed',
            'stopped_at' => now(),
        ]);
    }

    /**
     * Остановить сессию
     */
    public function stop(): void
    {
        $this->update([
            'status' => 'stopped',
            'stopped_at' => now(),
        ]);
    }
}

