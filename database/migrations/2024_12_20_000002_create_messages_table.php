<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Таблица сообщений - хранит все сообщения в диалогах между клиентом и ботом.
     * Поддерживает отслеживание использования токенов OpenAI.
     */
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            // Автоинкрементный ID сообщения
            $table->id()->comment('Уникальный ID сообщения');
            
            // Связь с диалогом
            $table->string('dialog_id')->comment('ID диалога, к которому относится сообщение');
            
            // Роль отправителя (user - клиент, assistant - бот, system - системное)
            $table->enum('role', ['user', 'assistant', 'system'])->comment('Роль отправителя: user (клиент), assistant (бот), system (системное)');
            
            // Содержимое сообщения (может быть JSON для мультимодальности: текст, изображения и т.д.)
            $table->longText('content')->comment('Текст сообщения или JSON для мультимодальных данных (текст, изображения, файлы)');
            
            // Количество входящих токенов (prompt tokens) для сообщений от OpenAI
            $table->integer('tokens_in')->nullable()->default(0)->comment('Количество входящих токенов (prompt tokens) использованных OpenAI');
            
            // Количество исходящих токенов (completion tokens) для сообщений от OpenAI
            $table->integer('tokens_out')->nullable()->default(0)->comment('Количество исходящих токенов (completion tokens) использованных OpenAI');
            
            // Метаданные сообщения: WhatsApp message_id, timestamp, media_url и другие данные
            $table->json('meta')->nullable()->comment('JSON с метаданными: WhatsApp messageId, timestamp, typeMessage, media_url и т.д.');
            
            // Временные метки создания и обновления
            $table->timestamps();

            // Связь с таблицей dialogs (каскадное удаление при удалении диалога)
            $table->foreign('dialog_id')->references('dialog_id')->on('dialogs')->onDelete('cascade');
            
            // Индексы для быстрого поиска
            $table->index('dialog_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};

