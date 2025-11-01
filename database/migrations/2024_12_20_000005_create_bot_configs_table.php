<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Таблица конфигураций бота - хранит различные настройки AI-ассистента для разных платформ.
     * Позволяет управлять поведением бота, промптами, температурой и векторными хранилищами.
     */
    public function up(): void
    {
        Schema::create('bot_configs', function (Blueprint $table) {
            // Автоинкрементный ID конфигурации
            $table->id()->comment('Уникальный ID конфигурации бота');
            
            // Название конфигурации (для идентификации в админке)
            $table->string('name')->comment('Название конфигурации (например: "Capital Mars WhatsApp Bot v1")');
            
            // Платформа, для которой предназначена конфигурация
            $table->string('platform')->comment('Платформа мессенджера: whatsapp, telegram, max');
            
            // Системный промпт для GPT (определяет личность и стиль общения бота)
            $table->longText('prompt')->comment('Системный промпт для OpenAI GPT, определяющий личность, стиль и правила поведения бота');
            
            // Описание сценария диалога (опционально)
            $table->longText('scenario_description')->nullable()->comment('Текстовое описание сценария диалога для администраторов');
            
            // Температура генерации (0.0 - детерминированно, 1.0 - креативно)
            $table->decimal('temperature', 3, 2)->default(0.7)->comment('Температура OpenAI от 0.00 до 1.00 (0 = детерминированно, 1 = креативно)');
            
            // Максимальное количество токенов в ответе
            $table->integer('max_tokens')->default(2000)->comment('Максимальное количество токенов в одном ответе GPT');
            
            // Флаг активности конфигурации
            $table->boolean('is_active')->default(false)->comment('Флаг активности: только одна конфигурация для платформы может быть активной');
            
            // Дополнительные настройки в JSON (шаблоны сообщений, переменные и т.д.)
            $table->json('settings')->nullable()->comment('JSON с дополнительными настройками: шаблоны сообщений, переменные, триггеры и т.д.');
            
            // Временные метки создания и обновления
            $table->timestamps();

            // Индексы для быстрого поиска
            $table->index('platform');
            $table->index('is_active');
            $table->index(['platform', 'is_active']); // Композитный индекс для поиска активной конфигурации на платформе
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_configs');
    }
};

