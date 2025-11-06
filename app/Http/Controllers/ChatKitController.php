<?php

namespace App\Http\Controllers;

use App\Models\ChatKitSession;
use App\Services\ChatKitService;
use App\Services\GreenApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * ChatKitController - управление сессиями ChatKit Agent
 */
class ChatKitController extends Controller
{
    public function __construct(
        private ChatKitService $chatKitService,
        private GreenApiService $greenApiService
    ) {}

    /**
     * Получить список всех сессий ChatKit
     */
    public function index(Request $request): JsonResponse
    {
        $query = ChatKitSession::with('messages');
        
        // Фильтр по статусу
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        // Фильтр по платформе
        if ($request->has('platform')) {
            $query->where('platform', $request->platform);
        }
        
        $sessions = $query->orderBy('created_at', 'desc')->paginate(20);
        
        return response()->json([
            'data' => $sessions->items(),
            'meta' => [
                'current_page' => $sessions->currentPage(),
                'last_page' => $sessions->lastPage(),
                'per_page' => $sessions->perPage(),
                'total' => $sessions->total(),
            ],
        ]);
    }

    /**
     * Получить конкретную сессию
     */
    public function show(string $chatId): JsonResponse
    {
        $session = ChatKitSession::where('chat_id', $chatId)
            ->with('messages')
            ->first();
        
        if (!$session) {
            return response()->json([
                'message' => 'Session not found',
            ], 404);
        }
        
        return response()->json([
            'data' => $session,
        ]);
    }

    /**
     * Создать и запустить новую сессию ChatKit
     */
    public function start(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'chat_id' => 'required|string',
            'object_id' => 'required|integer',
            'platform' => 'nullable|string|in:whatsapp,telegram,max',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            Log::info('Starting ChatKit session', [
                'chat_id' => $request->chat_id,
                'object_id' => $request->object_id,
                'platform' => $request->platform ?? 'whatsapp',
            ]);
            
            $session = $this->chatKitService->getOrCreateSession(
                $request->chat_id,
                $request->object_id,
                $request->platform ?? 'whatsapp'
            );
            
            Log::info('ChatKit session created/found', [
                'session_id' => $session->id,
                'chat_id' => $session->chat_id,
                'status' => $session->status,
            ]);
            
            // Пытаемся отправить приветственное сообщение
            try {
                $vars = $this->chatKitService->getVariablesFromCrm($request->chat_id, $request->object_id);
                $kickoffMessage = $this->generateKickoffMessage($vars);
                
                // Сохраняем kickoff сообщение в историю
                $session->addMessage('assistant', $kickoffMessage, ['type' => 'kickoff']);
                
                // Отправляем в WhatsApp
                $this->greenApiService->sendMessage($request->chat_id, $kickoffMessage);
                
                Log::info('Kickoff message sent', [
                    'chat_id' => $request->chat_id,
                    'message_length' => mb_strlen($kickoffMessage),
                ]);
            } catch (\Exception $e) {
                Log::warning('Не удалось отправить kickoff сообщение (это не критично)', [
                    'chat_id' => $request->chat_id,
                    'error' => $e->getMessage(),
                ]);
            }

            return response()->json([
                'message' => 'ChatKit session started successfully',
                'data' => $session->load('messages'),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to start ChatKit session', [
                'chat_id' => $request->chat_id ?? 'unknown',
                'object_id' => $request->object_id ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to start ChatKit session',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Остановить сессию ChatKit
     */
    public function stop(string $chatId): JsonResponse
    {
        try {
            $stopped = $this->chatKitService->stopSession($chatId);
            
            if (!$stopped) {
                return response()->json([
                    'message' => 'Session not found',
                ], 404);
            }

            return response()->json([
                'message' => 'ChatKit session stopped successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to stop ChatKit session', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to stop ChatKit session',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Остановить все активные сессии ChatKit
     */
    public function stopAll(): JsonResponse
    {
        try {
            $sessions = ChatKitSession::active()->get();
            $stopped = 0;
            
            foreach ($sessions as $session) {
                if ($session->stop()) {
                    $stopped++;
                }
            }

            return response()->json([
                'message' => "Stopped {$stopped} ChatKit sessions",
                'stopped' => $stopped,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to stop all ChatKit sessions', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to stop all ChatKit sessions',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Очистить историю сессии
     */
    public function clearSession(string $chatId): JsonResponse
    {
        try {
            $cleared = $this->chatKitService->clearSession($chatId);
            
            if (!$cleared) {
                return response()->json([
                    'message' => 'Session not found',
                ], 404);
            }

            return response()->json([
                'message' => 'ChatKit session history cleared successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to clear ChatKit session', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to clear ChatKit session',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Webhook для обработки входящих сообщений через ChatKit Agent
     */
    public function webhook(Request $request): JsonResponse
    {
        try {
            Log::info('ChatKit webhook received', ['payload' => $request->all()]);
            
            // Парсим данные из webhook (формат Green API)
            $message = $request->input('messages.0');
            
            if (!$message) {
                return response()->json(['status' => 'no_message'], 200);
            }
            
            $from = $message['from'] ?? '';
            $text = trim($message['text']['body'] ?? '');
            
            if (empty($from) || empty($text)) {
                return response()->json(['status' => 'invalid_data'], 200);
            }
            
            // Получаем или создаем сессию
            $session = ChatKitSession::where('chat_id', $from)->first();
            
            if (!$session || $session->status !== 'running') {
                Log::info('ChatKit session not found or not running', ['chat_id' => $from]);
                return response()->json(['status' => 'session_not_found'], 200);
            }
            
            // Обрабатываем сообщение через ChatKit Agent
            $response = $this->chatKitService->handleIncomingMessage(
                $from,
                $text,
                $session->object_id
            );
            
            // Отправляем ответ в WhatsApp
            $this->greenApiService->sendMessage($from, $response['reply']);
            
            return response()->json([
                'status' => 'success',
                'reply' => $response['reply'],
            ]);
            
        } catch (\Exception $e) {
            Log::error('ChatKit webhook error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Генерировать приветственное сообщение с подстановкой переменных
     */
    private function generateKickoffMessage(array $vars): string
    {
        $template = "{owner_name_clean}, добрый день!\n\n"
                  . "Я — ИИ-ассистент Capital Mars. Помогу со сдачей квартиры по адресу: {address}.\n\n"
                  . "Актуально ли объявление?";
        
        foreach ($vars as $key => $value) {
            $template = str_replace("{{$key}}", $value, $template);
        }
        
        return $template;
    }
}

