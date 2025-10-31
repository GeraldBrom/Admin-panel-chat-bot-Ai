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
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->string('dialog_id');
            $table->enum('role', ['user', 'assistant', 'system']);
            $table->longText('content'); // JSON для мультимодальности
            $table->integer('tokens_in')->nullable()->default(0);
            $table->integer('tokens_out')->nullable()->default(0);
            $table->json('meta')->nullable(); // message_id WhatsApp, media_url и т.п.
            $table->timestamps();

            // Связь с dialogs
            $table->foreign('dialog_id')->references('dialog_id')->on('dialogs')->onDelete('cascade');
            $table->index('dialog_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};

