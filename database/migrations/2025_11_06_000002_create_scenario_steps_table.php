<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Таблица шагов сценария - хранит отдельные шаги диалога с вариантами ответов.
     * Каждый шаг может иметь несколько вариантов ответа с переходом на следующий шаг.
     */
    public function up(): void
    {
        Schema::create('scenario_steps', function (Blueprint $table) {
            // Автоинкрементный ID шага
            $table->id()->comment('Уникальный ID шага сценария');
            
            // ID сценарного бота (связь с scenario_bots)
            $table->unsignedBigInteger('scenario_bot_id')->comment('ID сценарного бота (scenario_bots.id)');
            
            // Название шага (для идентификации в редакторе)
            $table->string('name')->comment('Название шага (например: "Приветствие", "Выбор типа квартиры")');
            
            // Сообщение, которое отправляет бот на этом шаге
            $table->text('message')->comment('Текст сообщения, которое отправит бот на этом шаге');
            
            // Тип шага
            $table->enum('step_type', ['message', 'question', 'menu', 'final'])->default('message')->comment('Тип шага: message (просто сообщение), question (ожидается ответ), menu (список кнопок), final (конечный шаг)');
            
            // Варианты ответов в JSON (для типа menu или question)
            $table->json('options')->nullable()->comment('JSON массив с вариантами ответов: [{text: "Да", next_step_id: 5}, {text: "Нет", next_step_id: 6}]');
            
            // ID следующего шага по умолчанию (если нет вариантов)
            $table->unsignedBigInteger('next_step_id')->nullable()->comment('ID следующего шага по умолчанию (scenario_steps.id)');
            
            // Условие перехода (опционально, для сложной логики)
            $table->text('condition')->nullable()->comment('Условие перехода на следующий шаг (например: "contains:да" или "equals:1")');
            
            // Позиция шага для визуального редактора
            $table->integer('position_x')->default(0)->comment('Позиция по оси X в визуальном редакторе');
            $table->integer('position_y')->default(0)->comment('Позиция по оси Y в визуальном редакторе');
            
            // Порядок сортировки
            $table->integer('order')->default(0)->comment('Порядок сортировки шагов');
            
            // Временные метки создания и обновления
            $table->timestamps();

            // Внешние ключи
            $table->foreign('scenario_bot_id')->references('id')->on('scenario_bots')->onDelete('cascade');

            // Индексы для быстрого поиска
            $table->index('scenario_bot_id');
            $table->index('next_step_id');
            $table->index('step_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scenario_steps');
    }
};

