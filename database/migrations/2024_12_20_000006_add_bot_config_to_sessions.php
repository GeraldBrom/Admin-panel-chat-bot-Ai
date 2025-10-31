<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bot_sessions', function (Blueprint $table) {
            $table->foreignId('bot_config_id')->nullable()->after('platform')->constrained('bot_configs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bot_sessions', function (Blueprint $table) {
            $table->dropForeign(['bot_config_id']);
            $table->dropColumn('bot_config_id');
        });
    }
};

