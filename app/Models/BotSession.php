<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BotSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'chat_id',
        'object_id',
        'platform',
        'bot_config_id',
        'status',
        'dialog_state',
        'metadata',
        'started_at',
        'stopped_at',
    ];

    protected $casts = [
        'dialog_state' => 'array',
        'metadata' => 'array',
        'started_at' => 'datetime',
        'stopped_at' => 'datetime',
        'object_id' => 'integer',
        'bot_config_id' => 'integer',
    ];

    /**
     * Получить диалог для этой сессии
     */
    public function dialog(): BelongsTo
    {
        // BotSession.chat_id = Dialog.client_id
        return $this->belongsTo(Dialog::class, 'chat_id', 'client_id');
    }

    /**
     * Получить конфигурацию бота для этой сессии
     */
    public function config(): BelongsTo
    {
        return $this->belongsTo(BotConfig::class, 'bot_config_id');
    }

    /**
     * Scope: Получить активные сессии
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'running');
    }

    /**
     * Scope: Получить сессии для платформы
     */
    public function scopeForPlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }

    /**
     * Остановка сессии
     */
    public function stop(): bool
    {
        return $this->update([
            'status' => 'stopped',
            'stopped_at' => now(),
        ]);
    }

    /**
     * Проверить, активна ли сессия
     */
    public function isActive(): bool
    {
        return $this->status === 'running';
    }
}

