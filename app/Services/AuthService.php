<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(
        private UserRepository $userRepository
    ) {}

    /**
     * Регистрация нового пользователя
     */
    public function register(array $data): array
    {
        // Проверка, что email не занят
        if ($this->userRepository->findByEmail($data['email'])) {
            throw ValidationException::withMessages([
                'email' => ['Этот email уже зарегистрирован.'],
            ]);
        }

        // Создание пользователя
        $user = $this->userRepository->create($data);

        // Создание токена
        $token = $user->createToken('auth-token')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    /**
     * Авторизация пользователя
     */
    public function login(string $email, string $password, bool $remember = false): array
    {
        // Поиск пользователя
        $user = $this->userRepository->findByEmail($email);

        // Проверка существования и пароля
        if (!$user || !$this->userRepository->checkPassword($user, $password)) {
            throw ValidationException::withMessages([
                'email' => ['Неверные учетные данные.'],
            ]);
        }

        // Удаление старых токенов (опционально)
        if (!$remember) {
            $user->tokens()->delete();
        }

        // Создание нового токена
        $tokenName = $remember ? 'auth-token-remember' : 'auth-token';
        $token = $user->createToken($tokenName)->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    /**
     * Выход из системы (удаление текущего токена)
     */
    public function logout(User $user): void
    {
        // Удаление текущего токена
        $user->currentAccessToken()->delete();
    }

    /**
     * Выход из всех устройств (удаление всех токенов)
     */
    public function logoutFromAllDevices(User $user): void
    {
        $user->tokens()->delete();
    }

    /**
     * Получить текущего пользователя
     */
    public function me(): ?User
    {
        return Auth::guard('sanctum')->user();
    }
}

