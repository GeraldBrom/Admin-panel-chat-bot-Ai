<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Таблица сообщений ChatKit - хранит историю диалогов через OpenAI Agent.
     */
    public function up(): void
    {
        Schema::create('chatkit_messages', function (Blueprint $table) {
            $table->id()->comment('Уникальный ID сообщения');
            
            // Связь с сессией
            $table->unsignedBigInteger('session_id')->comment('ID сессии ChatKit');
            
            // Роль отправителя
            $table->string('role')->comment('Роль: user, assistant, system');
            
            // Содержание сообщения
            $table->text('content')->comment('Текст сообщения');
            
            // Метаданные (intent, tokens, structured output и т.д.)
            $table->json('meta')->nullable()->comment('Метаданные: intent, tokens, structured_output');
            
            // Временные метки
            $table->timestamps();
            
            // Индексы
            $table->index('session_id');
            $table->index('role');
            $table->index('created_at');
            
            // Внешний ключ
            $table->foreign('session_id')
                  ->references('id')
                  ->on('chatkit_sessions')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chatkit_messages');
    }
};

