<?php

namespace App\Http\Controllers;

use App\Http\Resources\BotConfigResource;
use App\Models\BotConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class BotConfigController extends Controller
{
    /**
     * Список всех конфигураций ботов (опционально фильтруется по платформе)
     */
    public function index(Request $request): JsonResponse
    {
        $query = BotConfig::query();

        if ($request->has('platform')) {
            $query->where('platform', $request->platform);
        }

        $configs = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'data' => BotConfigResource::collection($configs),
        ]);
    }

    /**
     * Создание новой конфигурации бота
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'platform' => 'required|string|in:whatsapp',
            'prompt' => 'required|string',
            'scenario_description' => 'nullable|string',
            'temperature' => 'nullable|numeric|min:0|max:2',
            'max_tokens' => 'nullable|integer|min:1|max:4000',
            'vector_store_id_main' => 'nullable|string|max:255',
            'vector_store_id_objections' => 'nullable|string|max:255',
            'kickoff_message' => 'nullable|string',
            'vector_stores' => 'nullable|array',
            'vector_stores.*.name' => 'required|string|max:255',
            'vector_stores.*.id' => 'required|string|max:255',
            'openai_model' => 'nullable|string|max:100',
            'openai_service_tier' => 'nullable|string|in:auto,default,flex',
            'settings' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $config = BotConfig::create($request->all());

            return response()->json([
                'message' => 'Bot config created successfully',
                'data' => new BotConfigResource($config),
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to create bot config', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to create bot config',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Обновление конфигурации бота
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $config = BotConfig::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'prompt' => 'sometimes|string',
            'scenario_description' => 'nullable|string',
            'temperature' => 'nullable|numeric|min:0|max:2',
            'max_tokens' => 'nullable|integer|min:1|max:4000',
            'vector_store_id_main' => 'nullable|string|max:255',
            'vector_store_id_objections' => 'nullable|string|max:255',
            'kickoff_message' => 'nullable|string',
            'vector_stores' => 'nullable|array',
            'vector_stores.*.name' => 'required|string|max:255',
            'vector_stores.*.id' => 'required|string|max:255',
            'openai_model' => 'nullable|string|max:100',
            'openai_service_tier' => 'nullable|string|in:auto,default,flex',
            'settings' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $config->update($request->only([
                'name',
                'prompt',
                'scenario_description',
                'temperature',
                'max_tokens',
                'vector_store_id_main',
                'vector_store_id_objections',
                'kickoff_message',
                'vector_stores',
                'openai_model',
                'openai_service_tier',
                'settings',
            ]));

            return response()->json([
                'message' => 'Bot config updated successfully',
                'data' => new BotConfigResource($config->fresh()),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update bot config', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to update bot config',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Удаление конфигурации бота
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $config = BotConfig::findOrFail($id);
            $config->delete();

            return response()->json([
                'message' => 'Bot config deleted successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete bot config', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to delete bot config',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

