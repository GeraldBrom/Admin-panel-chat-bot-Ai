<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Создание администратора по умолчанию
        User::create([
            'name' => 'Администратор',
            'email' => 'admin@admin.com',
            'password' => Hash::make('admin123'),
            'email_verified_at' => now(),
        ]);

        // Можно добавить дополнительных пользователей
        User::create([
            'name' => 'Администратор-бот',
            'email' => 'admin-bot@admin.com',
            'password' => Hash::make('admin-bot123'),
            'email_verified_at' => now(),
        ]);

        $this->command->info('✅ Пользователи созданы успешно!');
        $this->command->info('');
        $this->command->info('📧 Администратор:');
        $this->command->info('   Email: admin@admin.com');
        $this->command->info('   Пароль: admin123');
        $this->command->info('');
        $this->command->info('📧 Менеджер:');
        $this->command->info('   Email: manager@admin.com');
        $this->command->info('   Пароль: manager123');
    }
}

