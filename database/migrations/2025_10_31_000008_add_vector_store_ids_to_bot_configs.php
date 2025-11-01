<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * УСТАРЕВШАЯ МИГРАЦИЯ: Добавляет старые поля для векторных хранилищ.
     * Сохранена для обратной совместимости.
     * Используйте поле 'vector_stores' (JSON массив) вместо этих полей.
     */
    public function up(): void
    {
        Schema::table('bot_configs', function (Blueprint $table) {
            $table->string('vector_store_id_main')->nullable()->after('max_tokens')
                ->comment('УСТАРЕЛО: ID основного векторного хранилища OpenAI (используйте vector_stores)');
            $table->string('vector_store_id_objections')->nullable()->after('vector_store_id_main')
                ->comment('УСТАРЕЛО: ID векторного хранилища для возражений (используйте vector_stores)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bot_configs', function (Blueprint $table) {
            $table->dropColumn(['vector_store_id_main', 'vector_store_id_objections']);
        });
    }
};

?>

