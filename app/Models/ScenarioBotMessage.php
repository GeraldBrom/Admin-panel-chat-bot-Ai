<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScenarioBotMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id',
        'role',
        'content',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    /**
     * Получить сессию, к которой принадлежит сообщение
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(ScenarioBotSession::class, 'session_id');
    }
}

