<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatKitSession extends Model
{
    use HasFactory;

    protected $table = 'chatkit_sessions';

    protected $fillable = [
        'chat_id',
        'object_id',
        'platform',
        'agent_id',
        'status',
        'context',
        'metadata',
        'started_at',
        'stopped_at',
    ];

    protected $casts = [
        'context' => 'array',
        'metadata' => 'array',
        'started_at' => 'datetime',
        'stopped_at' => 'datetime',
        'object_id' => 'integer',
    ];

    /**
     * Получить все сообщения для этой сессии
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ChatKitMessage::class, 'session_id');
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

    /**
     * Получить историю сообщений для OpenAI Agent
     */
    public function getHistory(int $limit = 10): array
    {
        return $this->messages()
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->reverse()
            ->map(function ($message) {
                return [
                    'role' => $message->role,
                    'content' => $message->content,
                ];
            })
            ->toArray();
    }

    /**
     * Добавить сообщение в историю
     */
    public function addMessage(string $role, string $content, array $meta = []): ChatKitMessage
    {
        return $this->messages()->create([
            'role' => $role,
            'content' => $content,
            'meta' => $meta,
        ]);
    }
}

