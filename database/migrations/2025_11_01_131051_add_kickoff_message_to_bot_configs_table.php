<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Добавляет поле kickoff_message - стартовое сообщение, которое бот отправляет при инициализации диалога.
     * Поддерживает переменные-плейсхолдеры: {owner_name_clean}, {address}, {objectCount} и т.д.
     */
    public function up(): void
    {
        Schema::table('bot_configs', function (Blueprint $table) {
            $table->text('kickoff_message')->nullable()->after('vector_store_id_objections')
                ->comment('Стартовое сообщение бота с поддержкой плейсхолдеров: {owner_name_clean}, {address}, {objectCount}');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bot_configs', function (Blueprint $table) {
            $table->dropColumn('kickoff_message');
        });
    }
};
