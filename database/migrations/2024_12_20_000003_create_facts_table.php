<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Таблица фактов - автоматически извлекает и хранит ключевую информацию из сообщений клиента.
     * Использует OpenAI для интеллектуального извлечения фактов (цена, комнаты, адрес и т.д.).
     */
    public function up(): void
    {
        Schema::create('facts', function (Blueprint $table) {
            // Автоинкрементный ID факта
            $table->id()->comment('Уникальный ID факта');
            
            // Связь с диалогом
            $table->string('dialog_id')->comment('ID диалога, к которому относится факт');
            
            // Ключ факта (например: "price", "rooms", "area", "floor", "location")
            $table->string('key')->comment('Тип факта: price (цена), rooms (комнаты), area (площадь), floor (этаж), location (адрес), available_from (доступность), tenant_preferences (предпочтения), contact_info (контакты), special_conditions (особые условия)');
            
            // Значение факта в текстовом виде
            $table->text('value')->comment('Значение факта в текстовом формате (например: "50000 рублей", "2 комнаты", "Москва, Тверская")');
            
            // ID сообщения, из которого был извлечен факт
            $table->unsignedBigInteger('source_message_id')->nullable()->comment('ID сообщения из таблицы messages, из которого был извлечен факт');
            
            // Уверенность GPT в правильности извлечения (0.00 - 1.00)
            $table->decimal('confidence', 3, 2)->default(1.00)->comment('Уровень уверенности в правильности факта от 0.00 до 1.00 (определяется GPT)');
            
            // Временная метка обнаружения факта
            $table->timestamp('discovered_at')->useCurrent()->comment('Дата и время обнаружения факта');
            
            // Временные метки создания и обновления
            $table->timestamps();

            // Связь с таблицей dialogs (каскадное удаление при удалении диалога)
            $table->foreign('dialog_id')->references('dialog_id')->on('dialogs')->onDelete('cascade');
            
            // Индексы для быстрого поиска
            $table->index('dialog_id');
            $table->index('key');
            $table->index(['dialog_id', 'key']); // Композитный индекс для поиска конкретного факта в диалоге
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('facts');
    }
};

