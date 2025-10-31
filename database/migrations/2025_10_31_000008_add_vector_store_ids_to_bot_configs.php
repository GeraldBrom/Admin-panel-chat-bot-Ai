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
            $table->string('vector_store_id_main')->nullable()->after('max_tokens');
            $table->string('vector_store_id_objections')->nullable()->after('vector_store_id_main');
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

