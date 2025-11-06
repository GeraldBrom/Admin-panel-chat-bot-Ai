<?php

namespace App\Http\Controllers;

use App\Models\ScenarioBot;
use App\Models\ScenarioBotSession;
use App\Services\ScenarioBotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ScenarioBotController extends Controller
{
    public function __construct(
        private ScenarioBotService $scenarioBotService
    ) {}

    /**
     * Получить список всех сценарных ботов
     */
    public function index(Request $request): JsonResponse
    {
        $query = ScenarioBot::with('steps')->orderBy('created_at', 'desc');

        // Фильтр по платформе
        if ($request->has('platform')) {
            $query->forPlatform($request->platform);
        }

        // Фильтр по статусу активности
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $bots = $query->get();

        return response()->json([
            'data' => $bots,
        ]);
    }

    /**
     * Получить конкретного бота с шагами
     */
    public function show(int $id): JsonResponse
    {
        $bot = ScenarioBot::with(['steps' => function ($query) {
            $query->orderBy('order');
        }, 'startStep'])->findOrFail($id);

        return response()->json([
            'data' => $bot,
        ]);
    }

    /**
     * Создать нового сценарного бота
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'platform' => 'required|in:whatsapp,telegram,max',
            'welcome_message' => 'nullable|string',
            'is_active' => 'boolean',
            'settings' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Ошибка валидации',
                'errors' => $validator->errors(),
            ], 422);
        }

        $bot = ScenarioBot::create($validator->validated());

        // Если бот активен, деактивируем остальные
        if ($bot->is_active) {
            $bot->deactivateOthers();
        }

        Log::info('[ScenarioBotController] Создан новый сценарный бот', [
            'bot_id' => $bot->id,
            'name' => $bot->name,
        ]);

        return response()->json([
            'data' => $bot,
            'message' => 'Сценарный бот успешно создан',
        ], 201);
    }

    /**
     * Обновить сценарного бота
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $bot = ScenarioBot::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'platform' => 'sometimes|in:whatsapp,telegram,max',
            'welcome_message' => 'nullable|string',
            'start_step_id' => 'nullable|integer|exists:scenario_steps,id',
            'is_active' => 'boolean',
            'settings' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Ошибка валидации',
                'errors' => $validator->errors(),
            ], 422);
        }

        $bot->update($validator->validated());

        // Если бот стал активным, деактивируем остальные
        if ($request->has('is_active') && $request->boolean('is_active')) {
            $bot->deactivateOthers();
        }

        Log::info('[ScenarioBotController] Обновлен сценарный бот', [
            'bot_id' => $bot->id,
            'name' => $bot->name,
        ]);

        return response()->json([
            'data' => $bot->fresh(['steps', 'startStep']),
            'message' => 'Сценарный бот успешно обновлен',
        ]);
    }

    /**
     * Удалить сценарного бота
     */
    public function destroy(int $id): JsonResponse
    {
        $bot = ScenarioBot::findOrFail($id);
        
        $botName = $bot->name;
        $bot->delete();

        Log::info('[ScenarioBotController] Удален сценарный бот', [
            'bot_id' => $id,
            'name' => $botName,
        ]);

        return response()->json([
            'message' => 'Сценарный бот успешно удален',
        ]);
    }

    /**
     * Запустить сессию сценарного бота
     */
    public function startSession(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'scenario_bot_id' => 'required|integer|exists:scenario_bots,id',
            'chat_id' => 'required|string',
            'object_id' => 'nullable|integer',
            'platform' => 'required|in:whatsapp,telegram,max',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Ошибка валидации',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $session = $this->scenarioBotService->startSession(
                $request->chat_id,
                $request->scenario_bot_id,
                $request->object_id,
                $request->platform
            );

            return response()->json([
                'data' => $session->load(['scenarioBot', 'currentStep']),
                'message' => 'Сессия успешно запущена',
            ], 201);
        } catch (\Exception $e) {
            Log::error('[ScenarioBotController] Ошибка запуска сессии', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Ошибка запуска сессии: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Остановить сессию
     */
    public function stopSession(string $chatId): JsonResponse
    {
        $stopped = $this->scenarioBotService->stopSession($chatId);

        if (!$stopped) {
            return response()->json([
                'message' => 'Активная сессия не найдена',
            ], 404);
        }

        return response()->json([
            'message' => 'Сессия успешно остановлена',
        ]);
    }

    /**
     * Получить все сессии для бота
     */
    public function getSessions(int $id): JsonResponse
    {
        $sessions = ScenarioBotSession::with(['scenarioBot', 'currentStep', 'messages' => function ($query) {
                $query->orderBy('created_at', 'asc');
            }])
            ->where('scenario_bot_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $sessions,
        ]);
    }

    /**
     * Получить конкретную сессию
     */
    public function getSession(string $chatId): JsonResponse
    {
        $session = ScenarioBotSession::with(['scenarioBot', 'currentStep', 'messages' => function ($query) {
                $query->orderBy('created_at', 'asc');
            }])
            ->byChatId($chatId)
            ->first();

        if (!$session) {
            return response()->json([
                'message' => 'Сессия не найдена',
            ], 404);
        }

        return response()->json([
            'data' => $session,
        ]);
    }

    /**
     * Сбросить сессию (вернуть на начало)
     */
    public function resetSession(string $chatId): JsonResponse
    {
        $reset = $this->scenarioBotService->resetSession($chatId);

        if (!$reset) {
            return response()->json([
                'message' => 'Активная сессия не найдена',
            ], 404);
        }

        return response()->json([
            'message' => 'Сессия сброшена на начальный шаг',
        ]);
    }
}

