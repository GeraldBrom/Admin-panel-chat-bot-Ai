<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Добавляет связь между сессией бота и его конфигурацией.
     * Позволяет каждой сессии использовать свою конфигурацию с уникальными параметрами.
     */
    public function up(): void
    {
        Schema::table('bot_sessions', function (Blueprint $table) {
            // Добавляем внешний ключ для существующей колонки bot_config_id
            $table->foreign('bot_config_id')
                ->references('id')
                ->on('bot_configs')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bot_sessions', function (Blueprint $table) {
            // Удаляем только внешний ключ, колонка остается в таблице
            $table->dropForeign(['bot_config_id']);
        });
    }
};

