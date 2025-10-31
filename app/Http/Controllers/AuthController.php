<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private AuthService $authService
    ) {}

    /**
     * Авторизация пользователя
     */
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->login(
                $request->email,
                $request->password,
                $request->boolean('remember', false)
            );

            return response()->json([
                'message' => 'Авторизация успешна!',
                'user' => new UserResource($result['user']),
                'token' => $result['token'],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Ошибка авторизации',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Login error: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Ошибка при авторизации',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Выход из системы
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $this->authService->logout($request->user());

            return response()->json([
                'message' => 'Вы успешно вышли из системы',
            ]);
        } catch (\Exception $e) {
            Log::error('Logout error: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Ошибка при выходе',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Получить данные текущего пользователя
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => new UserResource($request->user()),
        ]);
    }

    /**
     * Выход из всех устройств
     */
    public function logoutFromAllDevices(Request $request): JsonResponse
    {
        try {
            $this->authService->logoutFromAllDevices($request->user());

            return response()->json([
                'message' => 'Вы вышли из всех устройств',
            ]);
        } catch (\Exception $e) {
            Log::error('Logout from all devices error: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Ошибка при выходе из всех устройств',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

