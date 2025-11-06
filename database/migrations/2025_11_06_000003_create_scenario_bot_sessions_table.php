<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Таблица сессий сценарных ботов - отслеживает активные диалоги пользователей.
     */
    public function up(): void
    {
        Schema::create('scenario_bot_sessions', function (Blueprint $table) {
            // Автоинкрементный ID сессии
            $table->id()->comment('Уникальный ID сессии');
            
            // ID сценарного бота
            $table->unsignedBigInteger('scenario_bot_id')->comment('ID сценарного бота (scenario_bots.id)');
            
            // ID чата в мессенджере
            $table->string('chat_id')->comment('ID чата в мессенджере (например: 79034340422@c.us для WhatsApp)');
            
            // ID объекта недвижимости
            $table->unsignedBigInteger('object_id')->nullable()->comment('ID объекта недвижимости из удаленной базы данных myhomeday');
            
            // Платформа мессенджера
            $table->string('platform')->default('whatsapp')->comment('Платформа мессенджера: whatsapp, telegram, max');
            
            // ID текущего шага в сценарии
            $table->unsignedBigInteger('current_step_id')->nullable()->comment('ID текущего шага сценария (scenario_steps.id)');
            
            // Статус сессии
            $table->string('status')->default('running')->comment('Статус сессии: running (активна), paused (на паузе), stopped (остановлена), completed (завершена)');
            
            // Состояние диалога (собранные данные от пользователя)
            $table->json('dialog_data')->nullable()->comment('JSON с собранными данными от пользователя в ходе диалога');
            
            // Дополнительные метаданные
            $table->json('metadata')->nullable()->comment('JSON с метаданными: статистика сообщений, временные метки и т.д.');
            
            // Время начала сессии
            $table->timestamp('started_at')->useCurrent()->comment('Дата и время запуска сессии');
            
            // Время остановки сессии
            $table->timestamp('stopped_at')->nullable()->comment('Дата и время остановки сессии (NULL если сессия активна)');
            
            // Временные метки создания и обновления
            $table->timestamps();

            // Внешние ключи
            $table->foreign('scenario_bot_id')->references('id')->on('scenario_bots')->onDelete('cascade');
            $table->foreign('current_step_id')->references('id')->on('scenario_steps')->onDelete('set null');

            // Индексы для быстрого поиска
            $table->index('chat_id');
            $table->index('scenario_bot_id');
            $table->index('status');
            $table->index(['chat_id', 'platform']); // Композитный индекс
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scenario_bot_sessions');
    }
};

