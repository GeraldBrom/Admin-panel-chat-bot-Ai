<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Обновляем kickoff_message для существующих bot_configs
        DB::table('bot_configs')
            ->where('platform', 'whatsapp')
            ->update([
                'kickoff_message' => "{ownernameclean}, добрый день!\n\nЯ — ИИ-ассистент Capital Mars. Мы уже {rental_phrase} {address}. {ownernameclean}, что объявление снова актуально — верно? Если да, готовы подключиться к сдаче.",
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Возвращаем старое kickoff_message
        DB::table('bot_configs')
            ->where('platform', 'whatsapp')
            ->update([
                'kickoff_message' => "{owner_name_clean}, добрый день!\n\nЯ — ИИ-ассистент Capital Mars. Мы уже {objectCount} сдавали вашу квартиру на {address}. Видим, что объявление снова актуально — верно? Если да, готовы подключиться к сдаче.",
            ]);
    }
};

