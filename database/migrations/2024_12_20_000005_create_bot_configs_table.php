<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_configs', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // название конфигурации
            $table->string('platform'); // whatsapp, telegram, max
            $table->longText('prompt'); // системный промпт для ChatGPT
            $table->longText('scenario_description')->nullable(); // описание сценария
            $table->decimal('temperature', 3, 2)->default(0.7); // температура для OpenAI
            $table->integer('max_tokens')->default(2000); // максимальное количество токенов
            $table->boolean('is_active')->default(false); // активная конфигурация
            $table->json('settings')->nullable(); // дополнительные настройки и шаблоны сообщений
            $table->timestamps();

            $table->index('platform');
            $table->index('is_active');
            $table->index(['platform', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_configs');
    }
};

