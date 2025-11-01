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
        Schema::table('bot_configs', function (Blueprint $table) {
            $table->string('openai_model')->default('gpt-5-2025-08-07')->after('vector_stores');
            $table->string('openai_service_tier')->default('flex')->after('openai_model');
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

