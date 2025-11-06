<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Таблица сценарных ботов - хранит конфигурацию ботов без использования ИИ.
     * Боты работают по заранее заданным сценариям с предопределенными ответами.
     */
    public function up(): void
    {
        Schema::create('scenario_bots', function (Blueprint $table) {
            // Автоинкрементный ID бота
            $table->id()->comment('Уникальный ID сценарного бота');
            
            // Название бота (для идентификации в админке)
            $table->string('name')->comment('Название сценарного бота (например: "Бот консультации по квартирам")');
            
            // Описание бота
            $table->text('description')->nullable()->comment('Описание назначения и логики работы бота');
            
            // Платформа, для которой предназначен бот
            $table->string('platform')->default('whatsapp')->comment('Платформа мессенджера: whatsapp, telegram, max');
            
            // Приветственное сообщение
            $table->text('welcome_message')->nullable()->comment('Первое сообщение, которое отправляет бот при старте диалога');
            
            // ID начального шага сценария
            $table->unsignedBigInteger('start_step_id')->nullable()->comment('ID начального шага сценария (scenario_steps.id)');
            
            // Статус активности бота
            $table->boolean('is_active')->default(false)->comment('Флаг активности: только один бот на платформе может быть активным');
            
            // Дополнительные настройки в JSON
            $table->json('settings')->nullable()->comment('JSON с дополнительными настройками: таймауты, переменные и т.д.');
            
            // Временные метки создания и обновления
            $table->timestamps();

            // Индексы для быстрого поиска
            $table->index('platform');
            $table->index('is_active');
            $table->index(['platform', 'is_active']); // Композитный индекс для поиска активного бота на платформе
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scenario_bots');
    }
};

