<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Таблица ChatKit сессий - управляет диалогами через OpenAI Agent Builder.
     */
    public function up(): void
    {
        Schema::create('chatkit_sessions', function (Blueprint $table) {
            $table->id()->comment('Уникальный ID сессии ChatKit');
            
            // ID чата в мессенджере (WhatsApp номер с @c.us)
            $table->string('chat_id')->unique()->comment('ID чата в мессенджере (например: 79034340422@c.us)');
            
            // ID объекта недвижимости
            $table->unsignedBigInteger('object_id')->comment('ID объекта недвижимости из удаленной БД');
            
            // Платформа
            $table->string('platform')->default('whatsapp')->comment('Платформа: whatsapp, telegram, max');
            
            // Agent ID из ChatKit
            $table->string('agent_id')->comment('ID агента из OpenAI Agent Builder');
            
            // Статус сессии
            $table->string('status')->default('running')->comment('Статус: running, paused, stopped, completed');
            
            // Контекст диалога (history + metadata)
            $table->json('context')->nullable()->comment('Контекст диалога: история сообщений, переменные CRM');
            
            // Дополнительные метаданные
            $table->json('metadata')->nullable()->comment('Метаданные: статистика, информация о клиенте');
            
            // Временные метки
            $table->timestamp('started_at')->useCurrent()->comment('Время начала сессии');
            $table->timestamp('stopped_at')->nullable()->comment('Время остановки сессии');
            $table->timestamps();
            
            // Индексы
            $table->index('chat_id');
            $table->index('object_id');
            $table->index('status');
            $table->index('agent_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chatkit_sessions');
    }
};

