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
        Schema::create('bot_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('chat_id'); // WhatsApp chat ID
            $table->unsignedBigInteger('object_id'); // ID объекта из базы myhomeday
            $table->string('platform')->default('whatsapp'); // whatsapp, telegram, max
            $table->string('status')->default('running'); // running, paused, stopped, completed
            $table->json('dialog_state')->nullable(); // текущее состояние FSM диалога
            $table->json('metadata')->nullable(); // дополнительные данные
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('stopped_at')->nullable();
            $table->timestamps();

            $table->index('chat_id');
            $table->index('object_id');
            $table->index('status');
            $table->index(['chat_id', 'platform']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bot_sessions');
    }
};

