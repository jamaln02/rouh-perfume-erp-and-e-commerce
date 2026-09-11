<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('store_settings')) return;

        $rates = [
            'damascus' => 250, 'aleppo' => 80, 'homs' => 50, 'latakia' => 70, 'tartus' => 70,
            'hama' => 60, 'as-suwayda' => 50, 'daraa' => 50, 'deir ez-zor' => 100, 'raqqa' => 100,
            'al-hasakah' => 120, 'idlib' => 90, 'quneitra' => 60,
        ];

        $now = now();
        DB::table('store_settings')->updateOrInsert(
            ['key' => 'shipping_free_threshold'],
            ['value' => '5000', 'updated_at' => $now, 'created_at' => $now]
        );
        DB::table('store_settings')->updateOrInsert(
            ['key' => 'shipping_city_rates'],
            ['value' => json_encode($rates, JSON_UNESCAPED_UNICODE), 'updated_at' => $now, 'created_at' => $now]
        );
    }

    public function down(): void
    {
        // Intentionally leave store settings intact; previous application values are not recoverable reliably.
    }
};
