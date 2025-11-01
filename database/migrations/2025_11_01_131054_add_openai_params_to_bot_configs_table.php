<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Добавляет параметры для тонкой настройки OpenAI API:
     * - openai_model: выбор модели GPT (gpt-4o, gpt-4o-mini, gpt-5-2025-08-07 и т.д.)
     * - openai_service_tier: уровень приоритета запросов (auto, default, flex)
     */
    public function up(): void
    {
        Schema::table('bot_configs', function (Blueprint $table) {
            $table->string('openai_model')->default('gpt-5-2025-08-07')->after('vector_stores')
                ->comment('Модель OpenAI: gpt-4o, gpt-4o-mini, gpt-5-2025-08-07, и т.д.');
            $table->string('openai_service_tier')->default('flex')->after('openai_model')
                ->comment('Уровень сервиса OpenAI: auto (автоматический), default (стандартный), flex (гибкий, дешевле)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bot_configs', function (Blueprint $table) {
            $table->dropColumn(['openai_model', 'openai_service_tier']);
        });
    }
};

