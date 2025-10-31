<?php

namespace App\Http\Controllers;

use App\Http\Resources\BotSessionResource;
use App\Models\BotSession;
use App\Services\DialogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class BotController extends Controller
{
    public function __construct(
        private DialogService $dialogService
    ) {}

    /**
     * List all bot sessions
     */
    public function index(Request $request): JsonResponse
    {
        $sessions = BotSession::with('dialog.messages')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'data' => BotSessionResource::collection($sessions->items()),
            'meta' => [
                'current_page' => $sessions->currentPage(),
                'last_page' => $sessions->lastPage(),
                'per_page' => $sessions->perPage(),
                'total' => $sessions->total(),
            ],
        ]);
    }

    /**
     * Start bot session
     */
    public function start(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'chat_id' => 'required|string',
            'object_id' => 'required|integer',
            'bot_config_id' => 'nullable|integer|exists:bot_configs,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Initialize dialog
            $this->dialogService->initializeDialog(
                $request->chat_id,
                $request->object_id,
                $request->bot_config_id
            );

            // Пытаемся получить созданную/запущенную сессию, но не падаем, если её нет
            $session = BotSession::where('chat_id', $request->chat_id)
                ->orderBy('created_at', 'desc')
                ->first();

            return response()->json([
                'message' => 'Bot started successfully',
                'data' => $session ? new BotSessionResource($session) : null,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to start bot', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to start bot',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Stop bot session
     */
    public function stop(string $chatId): JsonResponse
    {
        $session = BotSession::where('chat_id', $chatId)->first();

        if ($session && $session->status === 'running') {
            $session->stop();
            return response()->json([
                'message' => 'Bot stopped successfully',
                'data' => new BotSessionResource($session->fresh()),
            ]);
        }

        // Идемпотентное поведение: если бота нет или уже остановлен — возвращаем 200
        return response()->json([
            'message' => 'Bot already stopped or not found',
            'data' => $session ? new BotSessionResource($session) : null,
        ]);
    }

    /**
     * Stop all bots
     */
    public function stopAll(): JsonResponse
    {
        try {
            $count = BotSession::where('status', 'running')->count();
            BotSession::where('status', 'running')->update([
                'status' => 'stopped',
                'stopped_at' => now(),
            ]);

            return response()->json([
                'message' => "Stopped {$count} bot(s)",
                'count' => $count,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to stop all bots', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to stop all bots',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get bot status
     */
    public function show(string $chatId): JsonResponse
    {
        $session = BotSession::with('dialog.messages')
            ->where('chat_id', $chatId)
            ->firstOrFail();

        return response()->json([
            'data' => new BotSessionResource($session),
        ]);
    }
}

