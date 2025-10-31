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
        'is_active',
        'settings',
    ];

    protected $casts = [
        'temperature' => 'decimal:2',
        'max_tokens' => 'integer',
        'is_active' => 'boolean',
        'settings' => 'array',
    ];

    /**
     * Scope: Get configs for platform
     */
    public function scopeForPlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }

    /**
     * Scope: Get active config
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get active config for platform
     */
    public static function getActiveForPlatform(string $platform): ?self
    {
        return self::where('platform', $platform)
            ->where('is_active', true)
            ->first();
    }
}

