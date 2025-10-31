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
        'tokens_in',
        'tokens_out',
        'meta',
    ];

    protected $casts = [
        'content' => 'string', // JSON string
        'meta' => 'array',
        'tokens_in' => 'integer',
        'tokens_out' => 'integer',
    ];

    /**
     * Get the dialog this message belongs to
     */
    public function dialog(): BelongsTo
    {
        return $this->belongsTo(Dialog::class, 'dialog_id', 'dialog_id');
    }

    /**
     * Get recent messages for a dialog (for context)
     */
    public static function getRecentMessages(string $dialogId, int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('dialog_id', $dialogId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->reverse(); // reverse to get chronological order
    }
}

