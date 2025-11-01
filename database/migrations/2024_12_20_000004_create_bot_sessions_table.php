<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Таблица сессий бота - управляет активными диалогами бота с клиентами.
     * Связывает чаты с объектами недвижимости и отслеживает состояние разговора.
     */
    public function up(): void
    {
        Schema::create('bot_sessions', function (Blueprint $table) {
            // Автоинкрементный ID сессии
            $table->id()->comment('Уникальный ID сессии бота');
            
            // ID чата в мессенджере (WhatsApp номер с @c.us)
            $table->string('chat_id')->comment('ID чата в мессенджере (например: 79034340422@c.us для WhatsApp)');
            
            // ID объекта недвижимости из удаленной БД (myhomeday)
            $table->unsignedBigInteger('object_id')->comment('ID объекта недвижимости из удаленной базы данных myhomeday');
            
            // Платформа мессенджера
            $table->string('platform')->default('whatsapp')->comment('Платформа мессенджера: whatsapp, telegram, max');
            
            // ID конфигурации бота (связь с bot_configs)
            $table->unsignedBigInteger('bot_config_id')->nullable()->comment('ID конфигурации бота (bot_configs.id) - определяет промпт, температуру, модель GPT и другие параметры');
            
            // Статус сессии
            $table->string('status')->default('running')->comment('Статус сессии: running (активна), paused (на паузе), stopped (остановлена), completed (завершена)');
            
            // Состояние конечного автомата (FSM) диалога в JSON
            $table->json('dialog_state')->nullable()->comment('JSON с текущим состоянием FSM диалога (state, данные сценария и т.д.)');
            
            // Дополнительные метаданные сессии: информация об объекте, клиенте, статистика
            $table->json('metadata')->nullable()->comment('JSON с метаданными: object_id, owner_name, address, phone, email, total_messages, user_messages, total_facts, has_summary, finalized_at и т.д.');
            
            // Время начала сессии
            $table->timestamp('started_at')->useCurrent()->comment('Дата и время запуска сессии');
            
            // Время остановки сессии (NULL если активна)
            $table->timestamp('stopped_at')->nullable()->comment('Дата и время остановки сессии (NULL если сессия активна)');
            
            // Временные метки создания и обновления
            $table->timestamps();

            // Индексы для быстрого поиска
            $table->index('chat_id');
            $table->index('object_id');
            $table->index('status');
            $table->index(['chat_id', 'platform']); // Композитный индекс для поиска сессии в конкретном чате на платформе
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bot_sessions');
    }
};

