<?php

namespace App\Http\Controllers;

use App\Models\ScenarioBot;
use App\Models\ScenarioStep;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ScenarioStepController extends Controller
{
    /**
     * Получить все шаги для бота
     */
    public function index(int $botId): JsonResponse
    {
        $bot = ScenarioBot::findOrFail($botId);
        $steps = $bot->steps()->orderBy('order')->get();

        return response()->json([
            'data' => $steps,
        ]);
    }

    /**
     * Получить конкретный шаг
     */
    public function show(int $botId, int $stepId): JsonResponse
    {
        $step = ScenarioStep::where('scenario_bot_id', $botId)
            ->findOrFail($stepId);

        return response()->json([
            'data' => $step,
        ]);
    }

    /**
     * Создать новый шаг
     */
    public function store(Request $request, int $botId): JsonResponse
    {
        $bot = ScenarioBot::findOrFail($botId);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'message' => 'required|string',
            'step_type' => 'required|in:message,question,menu,final',
            'options' => 'nullable|array',
            'options.*.text' => 'required_with:options|string',
            'options.*.next_step_id' => 'nullable|integer|exists:scenario_steps,id',
            'next_step_id' => 'nullable|integer|exists:scenario_steps,id',
            'condition' => 'nullable|string',
            'position_x' => 'nullable|integer',
            'position_y' => 'nullable|integer',
            'order' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Ошибка валидации',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $data['scenario_bot_id'] = $botId;

        $step = ScenarioStep::create($data);

        Log::info('[ScenarioStepController] Создан новый шаг', [
            'step_id' => $step->id,
            'bot_id' => $botId,
            'name' => $step->name,
        ]);

        return response()->json([
            'data' => $step,
            'message' => 'Шаг успешно создан',
        ], 201);
    }

    /**
     * Обновить шаг
     */
    public function update(Request $request, int $botId, int $stepId): JsonResponse
    {
        $step = ScenarioStep::where('scenario_bot_id', $botId)
            ->findOrFail($stepId);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'message' => 'sometimes|string',
            'step_type' => 'sometimes|in:message,question,menu,final',
            'options' => 'nullable|array',
            'options.*.text' => 'required_with:options|string',
            'options.*.next_step_id' => 'nullable|integer|exists:scenario_steps,id',
            'next_step_id' => 'nullable|integer|exists:scenario_steps,id',
            'condition' => 'nullable|string',
            'position_x' => 'nullable|integer',
            'position_y' => 'nullable|integer',
            'order' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Ошибка валидации',
                'errors' => $validator->errors(),
            ], 422);
        }

        $step->update($validator->validated());

        Log::info('[ScenarioStepController] Обновлен шаг', [
            'step_id' => $stepId,
            'bot_id' => $botId,
            'name' => $step->name,
        ]);

        return response()->json([
            'data' => $step->fresh(),
            'message' => 'Шаг успешно обновлен',
        ]);
    }

    /**
     * Удалить шаг
     */
    public function destroy(int $botId, int $stepId): JsonResponse
    {
        $step = ScenarioStep::where('scenario_bot_id', $botId)
            ->findOrFail($stepId);

        // Проверяем, не является ли этот шаг начальным для бота
        $bot = ScenarioBot::where('id', $botId)
            ->where('start_step_id', $stepId)
            ->first();

        if ($bot) {
            return response()->json([
                'message' => 'Невозможно удалить начальный шаг. Сначала установите другой начальный шаг.',
            ], 422);
        }

        $stepName = $step->name;
        $step->delete();

        Log::info('[ScenarioStepController] Удален шаг', [
            'step_id' => $stepId,
            'bot_id' => $botId,
            'name' => $stepName,
        ]);

        return response()->json([
            'message' => 'Шаг успешно удален',
        ]);
    }

    /**
     * Массово обновить порядок шагов
     */
    public function updateOrder(Request $request, int $botId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'steps' => 'required|array',
            'steps.*.id' => 'required|integer|exists:scenario_steps,id',
            'steps.*.order' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Ошибка валидации',
                'errors' => $validator->errors(),
            ], 422);
        }

        foreach ($request->steps as $stepData) {
            ScenarioStep::where('scenario_bot_id', $botId)
                ->where('id', $stepData['id'])
                ->update(['order' => $stepData['order']]);
        }

        Log::info('[ScenarioStepController] Обновлен порядок шагов', [
            'bot_id' => $botId,
            'steps_count' => count($request->steps),
        ]);

        return response()->json([
            'message' => 'Порядок шагов успешно обновлен',
        ]);
    }

    /**
     * Массово обновить позиции шагов (для визуального редактора)
     */
    public function updatePositions(Request $request, int $botId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'steps' => 'required|array',
            'steps.*.id' => 'required|integer|exists:scenario_steps,id',
            'steps.*.position_x' => 'required|integer',
            'steps.*.position_y' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Ошибка валидации',
                'errors' => $validator->errors(),
            ], 422);
        }

        foreach ($request->steps as $stepData) {
            ScenarioStep::where('scenario_bot_id', $botId)
                ->where('id', $stepData['id'])
                ->update([
                    'position_x' => $stepData['position_x'],
                    'position_y' => $stepData['position_y'],
                ]);
        }

        Log::info('[ScenarioStepController] Обновлены позиции шагов', [
            'bot_id' => $botId,
            'steps_count' => count($request->steps),
        ]);

        return response()->json([
            'message' => 'Позиции шагов успешно обновлены',
        ]);
    }
}

