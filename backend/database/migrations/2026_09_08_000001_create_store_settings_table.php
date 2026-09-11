<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_settings', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->longText('value')->nullable();
            $table->timestamps();
        });

        $defaults = [
            'shipping_free_threshold' => '5000',
            'shipping_city_rates' => json_encode([
                'damascus' => 250,
                'aleppo' => 80,
                'homs' => 50,
                'latakia' => 70,
                'tartus' => 70,
                'hama' => 60,
                'as-suwayda' => 50,
                'daraa' => 50,
                'deir ez-zor' => 100,
                'raqqa' => 100,
                'al-hasakah' => 120,
                'idlib' => 90,
                'quneitra' => 60,
            ], JSON_UNESCAPED_UNICODE),
            'loyalty_enabled' => '1',
            'loyalty_earn_amount' => '1000',
            'loyalty_earn_points' => '1',
            'loyalty_redeem_points' => '100',
            'loyalty_redeem_discount' => '500',
            'quiz_discount_percent' => '10',
            'social_instagram' => 'rouh_.parfum',
            'offers_title_ar' => 'العروض والمجموعات',
            'offers_title_en' => 'Bundles & Offers',
            'offers_subtitle_ar' => 'وفر أكثر مع مجموعاتنا الحصرية',
            'offers_subtitle_en' => 'Save more with our exclusive bundles',
        ];

        $now = now();
        foreach ($defaults as $key => $value) {
            DB::table('store_settings')->insert([
                'key' => $key,
                'value' => $value,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('store_settings');
    }
};
