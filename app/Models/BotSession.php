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
     * Get the dialog for this session
     */
    public function dialog(): BelongsTo
    {
        // BotSession.chat_id = Dialog.client_id
        return $this->belongsTo(Dialog::class, 'chat_id', 'client_id');
    }

    /**
     * Get the bot config for this session
     */
    public function config(): BelongsTo
    {
        return $this->belongsTo(BotConfig::class, 'bot_config_id');
    }

    /**
     * Scope: Get active sessions
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'running');
    }

    /**
     * Scope: Get sessions for platform
     */
    public function scopeForPlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }

    /**
     * Stop the session
     */
    public function stop(): bool
    {
        return $this->update([
            'status' => 'stopped',
            'stopped_at' => now(),
        ]);
    }

    /**
     * Check if session is active
     */
    public function isActive(): bool
    {
        return $this->status === 'running';
    }
}

