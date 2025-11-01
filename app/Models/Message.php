<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'dialog_id',
        'role',
        'content',
        'previous_response_id',
        'tokens_in',
        'tokens_out',
        'meta',
    ];

    protected $casts = [
        'content' => 'string',
        'previous_response_id' => 'string',
        'meta' => 'array',
        'tokens_in' => 'integer',
        'tokens_out' => 'integer',
    ];

    /**
     * Получить диалог, к которому принадлежит это сообщение
     */
    public function dialog(): BelongsTo
    {
        return $this->belongsTo(Dialog::class, 'dialog_id', 'dialog_id');
    }

    /**
     * Получить недавние сообщения для диалога (для контекста)
     */
    public static function getRecentMessages(string $dialogId, int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('dialog_id', $dialogId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->reverse();
    }
}

