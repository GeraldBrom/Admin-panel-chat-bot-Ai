<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Добавляет новый формат для множественных векторных хранилищ OpenAI.
     * Формат: [{"name": "Основная база", "id": "vs_123..."}, {"name": "Возражения", "id": "vs_456..."}]
     * Заменяет устаревшие поля vector_store_id_main и vector_store_id_objections.
     */
    public function up(): void
    {
        Schema::table('bot_configs', function (Blueprint $table) {
            $table->json('vector_stores')->nullable()->after('kickoff_message')
                ->comment('JSON массив векторных хранилищ OpenAI: [{"name": "...", "id": "vs_..."}]');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bot_configs', function (Blueprint $table) {
            $table->dropColumn('vector_stores');
        });
    }
};

