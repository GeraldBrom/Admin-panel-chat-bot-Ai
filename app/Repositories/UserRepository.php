<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;

class UserRepository
{
    /**
     * Найти пользователя по email
     */
    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    /**
     * Найти пользователя по ID
     */
    public function findById(int $id): ?User
    {
        return User::find($id);
    }

    /**
     * Создать нового пользователя
     */
    public function create(array $data): User
    {
        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);
    }

    /**
     * Обновить пользователя
     */
    public function update(User $user, array $data): bool
    {
        return $user->update($data);
    }

    /**
     * Удалить пользователя
     */
    public function delete(User $user): bool
    {
        return $user->delete();
    }

    /**
     * Получить всех пользователей
     */
    public function getAll(): Collection
    {
        return User::all();
    }

    /**
     * Проверить пароль пользователя
     */
    public function checkPassword(User $user, string $password): bool
    {
        return Hash::check($password, $user->password);
    }
}

