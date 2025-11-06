<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatKitMessage extends Model
{
    use HasFactory;

    protected $table = 'chatkit_messages';

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
     * Получить сессию, к которой принадлежит это сообщение
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(ChatKitSession::class, 'session_id');
    }
}

