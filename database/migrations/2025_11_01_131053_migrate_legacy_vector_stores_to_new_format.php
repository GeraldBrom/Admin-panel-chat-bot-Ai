<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Переносим существующие vector_store_id_main и vector_store_id_objections в новый формат vector_stores
        $configs = DB::table('bot_configs')->get();

        foreach ($configs as $config) {
            $vectorStores = [];

            // Если есть основная база знаний
            if (!empty($config->vector_store_id_main)) {
                $vectorStores[] = [
                    'name' => 'Основная база знаний',
                    'id' => $config->vector_store_id_main,
                ];
            }

            // Если есть база возражений
            if (!empty($config->vector_store_id_objections)) {
                $vectorStores[] = [
                    'name' => 'База возражений',
                    'id' => $config->vector_store_id_objections,
                ];
            }

            // Обновляем только если есть что мигрировать и если vector_stores пустой
            if (!empty($vectorStores)) {
                $existingVectorStores = json_decode($config->vector_stores, true);
                
                // Если vector_stores уже заполнен, пропускаем
                if (empty($existingVectorStores)) {
                    DB::table('bot_configs')
                        ->where('id', $config->id)
                        ->update(['vector_stores' => json_encode($vectorStores)]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Откатываем миграцию - очищаем vector_stores
        DB::table('bot_configs')->update(['vector_stores' => null]);
    }
};

