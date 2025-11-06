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
        Schema::create('scenario_bot_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('scenario_bot_sessions')->onDelete('cascade');
            $table->enum('role', ['user', 'assistant', 'system'])->comment('Роль отправителя');
            $table->longText('content')->comment('Текст сообщения');
            $table->json('meta')->nullable()->comment('Метаданные сообщения');
            $table->timestamps();

            $table->index(['session_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scenario_bot_messages');
    }
};
