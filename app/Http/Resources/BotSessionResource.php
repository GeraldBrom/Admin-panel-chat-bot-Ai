<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BotSessionResource extends JsonResource
{
    /**
     * Преобразовать ресурс в массив.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'chat_id' => $this->chat_id,
            'object_id' => $this->object_id,
            'platform' => $this->platform,
            'bot_config_id' => $this->bot_config_id,
            'status' => $this->status,
            'dialog_state' => $this->dialog_state,
            'metadata' => $this->metadata,
            'started_at' => $this->started_at?->toISOString(),
            'stopped_at' => $this->stopped_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            // Включить dialog_id и messages, если диалог загружен
            'dialog_id' => $this->whenLoaded('dialog', function () {
                return $this->dialog?->dialog_id;
            }),
            'messages' => $this->whenLoaded('dialog', function () {
                if (!$this->dialog) {
                    return [];
                }
                
                // Проверяем, загружено ли отношение messages
                if (!$this->dialog->relationLoaded('messages')) {
                    return [];
                }
                
                // Если загружено, но пустое - возвращаем пустой массив
                $messages = $this->dialog->messages;
                if ($messages->isEmpty()) {
                    return [];
                }
                
                return $messages->map(function ($message) {
                    return [
                        'id' => $message->id,
                        'dialog_id' => $message->dialog_id,
                        'role' => $message->role,
                        'content' => $message->content,
                        'tokens_in' => $message->tokens_in,
                        'tokens_out' => $message->tokens_out,
                        'meta' => $message->meta,
                        'created_at' => $message->created_at?->toISOString(),
                    ];
                })->values();
            }),
        ];
    }
}

