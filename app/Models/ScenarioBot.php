<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScenarioBot extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'platform',
        'welcome_message',
        'start_step_id',
        'is_active',
        'settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'settings' => 'array',
        'start_step_id' => 'integer',
    ];

    /**
     * Получить все шаги сценария бота
     */
    public function steps(): HasMany
    {
        return $this->hasMany(ScenarioStep::class)->orderBy('order');
    }

    /**
     * Получить начальный шаг сценария
     */
    public function startStep(): BelongsTo
    {
        return $this->belongsTo(ScenarioStep::class, 'start_step_id');
    }

    /**
     * Получить все активные сессии бота
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(ScenarioBotSession::class);
    }

    /**
     * Scope: Получить ботов для платформы
     */
    public function scopeForPlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }

    /**
     * Scope: Получить активных ботов
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Деактивировать все другие боты на той же платформе
     */
    public function deactivateOthers(): void
    {
        static::where('platform', $this->platform)
            ->where('id', '!=', $this->id)
            ->update(['is_active' => false]);
    }
}

