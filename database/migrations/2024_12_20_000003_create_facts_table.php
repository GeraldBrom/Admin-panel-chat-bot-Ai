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
        Schema::create('facts', function (Blueprint $table) {
            $table->id();
            $table->string('dialog_id');
            $table->string('key'); // ключ факта (например: "object_price", "owner_name")
            $table->text('value'); // значение факта
            $table->unsignedBigInteger('source_message_id')->nullable(); // ID сообщения-источника
            $table->decimal('confidence', 3, 2)->default(1.00); // уверенность (0.00-1.00)
            $table->timestamp('discovered_at')->useCurrent(); // когда обнаружен
            $table->timestamps();

            // Связь с dialogs
            $table->foreign('dialog_id')->references('dialog_id')->on('dialogs')->onDelete('cascade');
            $table->index('dialog_id');
            $table->index('key');
            $table->index(['dialog_id', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('facts');
    }
};

