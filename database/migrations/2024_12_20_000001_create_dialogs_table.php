<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Таблица диалогов - хранит информацию о разговорах с клиентами.
     * Каждый диалог привязан к клиенту (WhatsApp номер) и бренду.
     */
    public function up(): void
    {
        Schema::create('dialogs', function (Blueprint $table) {
            // Первичный ключ: составной ID из бренда и номера клиента (например: capital_mars_79034340422@c.us)
            $table->string('dialog_id')->primary()->comment('Уникальный ID диалога (формат: brand_client_id)');
            
            // Идентификатор клиента (номер WhatsApp с @c.us)
            $table->string('client_id')->comment('Номер WhatsApp клиента (например: 79034340422@c.us)');
            
            // Бренд/проект, к которому относится диалог
            $table->string('brand')->default('capital_mars')->comment('Название бренда/проекта (например: capital_mars)');
            
            // Краткое резюме диалога, генерируемое GPT для быстрого понимания контекста
            $table->mediumText('summary')->nullable()->comment('Краткое резюме диалога, генерируемое автоматически каждые 5 сообщений');
            
            // ID последнего ответа от OpenAI (response_id) для отслеживания
            $table->string('provider_conversation_id')->nullable()->comment('ID последнего ответа OpenAI (response_id) для связи с провайдером');
            
            // Текущее состояние диалога в конечном автомате
            $table->string('current_state')->default('initial')->comment('Состояние диалога: initial, active, completed');
            
            // Дополнительные метаданные (object_id, owner_name, address и т.д.)
            $table->json('metadata')->nullable()->comment('JSON с метаданными: object_id, owner_name, address, object_count, add_date, initialized_at');
            
            // Временные метки создания и обновления
            $table->timestamps();

            // Индексы для быстрого поиска
            $table->index('client_id');
            $table->index('brand');
            $table->index(['client_id', 'brand']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dialogs');
    }
};

