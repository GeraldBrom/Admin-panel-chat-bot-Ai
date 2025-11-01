<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BotConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'platform',
        'prompt',
        'scenario_description',
        'temperature',
        'max_tokens',
        'kickoff_message',
        'vector_stores',
        'openai_model',
        'openai_service_tier',
        'settings',
    ];

    protected $casts = [
        'temperature' => 'decimal:2',
        'max_tokens' => 'integer',
        'kickoff_message' => 'string',
        'vector_stores' => 'array',
        'openai_model' => 'string',
        'openai_service_tier' => 'string',
        'settings' => 'array',
    ];

    /**
     * Scope: Получить конфигурации для платформы
     */
    public function scopeForPlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }

}

