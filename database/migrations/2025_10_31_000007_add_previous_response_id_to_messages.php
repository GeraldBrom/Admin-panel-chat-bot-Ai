<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Добавляет поле previous_response_id для хранения ID ответа от OpenAI API.
     * Используется для отслеживания конкретных ответов, дебаггинга и аналитики.
     * Формат: "resp_abc123..." или "chatcmpl-xyz789..." в зависимости от API endpoint.
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->string('previous_response_id')->nullable()->after('content')
                ->comment('ID ответа от OpenAI API (например: resp_abc123, chatcmpl-xyz789) для отслеживания и дебаггинга');
            $table->index('previous_response_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['previous_response_id']);
            $table->dropColumn('previous_response_id');
        });
    }
};

?>

