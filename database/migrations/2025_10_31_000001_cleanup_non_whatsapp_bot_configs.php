<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Удаляем все конфигурации не WhatsApp
        DB::table('bot_configs')->whereNot('platform', 'whatsapp')->delete();
    }

    public function down(): void
    {
        // Ничего не восстанавливаем
    }
};


