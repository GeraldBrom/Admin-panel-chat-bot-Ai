<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BotConfigResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'platform' => $this->platform,
            'prompt' => $this->prompt,
            'scenario_description' => $this->scenario_description,
            'temperature' => (float) $this->temperature,
            'max_tokens' => $this->max_tokens,
            'vector_store_id_main' => $this->vector_store_id_main,
            'vector_store_id_objections' => $this->vector_store_id_objections,
            'kickoff_message' => $this->kickoff_message,
            'vector_stores' => $this->vector_stores ?? [],
            'openai_model' => $this->openai_model ?? 'gpt-5-2025-08-07',
            'openai_service_tier' => $this->openai_service_tier ?? 'flex',
            'settings' => $this->settings,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

