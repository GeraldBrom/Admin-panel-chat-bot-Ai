<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dialogs', function (Blueprint $table) {
            $table->string('dialog_id')->primary(); // client_id + brand
            $table->string('client_id'); // номер WhatsApp
            $table->string('brand')->default('capital_mars'); // бренд/проект
            $table->mediumText('summary')->nullable(); // свёртка диалога для контекста
            $table->string('provider_conversation_id')->nullable(); // ID разговора у провайдера (OpenAI thread_id)
            $table->string('current_state')->default('initial'); // текущее состояние диалога
            $table->json('metadata')->nullable(); // дополнительные метаданные
            $table->timestamps();

            // Индексы
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

