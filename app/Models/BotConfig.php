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
        'vector_store_id_main',
        'vector_store_id_objections',
        'settings',
    ];

    protected $casts = [
        'temperature' => 'decimal:2',
        'max_tokens' => 'integer',
        'vector_store_id_main' => 'string',
        'vector_store_id_objections' => 'string',
        'settings' => 'array',
    ];

    /**
     * Scope: Get configs for platform
     */
    public function scopeForPlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }

    // Поле активности удалено из бизнес-логики: конфигурации выбираются явно при создании бота
}

