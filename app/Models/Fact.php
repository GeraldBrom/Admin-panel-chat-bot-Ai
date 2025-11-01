<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Fact extends Model
{
    use HasFactory;

    protected $fillable = [
        'dialog_id',
        'key',
        'value',
        'source_message_id',
        'confidence',
        'discovered_at',
    ];

    protected $casts = [
        'confidence' => 'decimal:2',
        'discovered_at' => 'datetime',
    ];

    /**
     * Получить диалог, к которому принадлежит этот факт
     */
    public function dialog(): BelongsTo
    {
        return $this->belongsTo(Dialog::class, 'dialog_id', 'dialog_id');
    }

    /**
     * Получить все факты для диалога
     */
    public static function getForDialog(string $dialogId): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('dialog_id', $dialogId)
            ->orderBy('discovered_at', 'desc')
            ->get();
    }
}

