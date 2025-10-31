<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dialog extends Model
{
    use HasFactory;

    protected $primaryKey = 'dialog_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'dialog_id',
        'client_id',
        'brand',
        'summary',
        'provider_conversation_id',
        'current_state',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * Get all messages in this dialog
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'dialog_id', 'dialog_id');
    }

    /**
     * Get all facts discovered in this dialog
     */
    public function facts(): HasMany
    {
        return $this->hasMany(Fact::class, 'dialog_id', 'dialog_id');
    }

    /**
     * Generate dialog_id from client_id and brand
     */
    public static function generateDialogId(string $clientId, string $brand = 'capital_mars'): string
    {
        return "{$brand}_{$clientId}";
    }

    /**
     * Get or create dialog
     */
    public static function getOrCreate(string $clientId, string $brand = 'capital_mars'): self
    {
        $dialogId = self::generateDialogId($clientId, $brand);
        
        return self::firstOrCreate(
            ['dialog_id' => $dialogId],
            [
                'client_id' => $clientId,
                'brand' => $brand,
                'current_state' => 'initial',
            ]
        );
    }
}

