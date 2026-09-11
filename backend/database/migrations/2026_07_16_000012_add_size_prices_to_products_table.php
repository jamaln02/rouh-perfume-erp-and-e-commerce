<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->json('size_prices')->nullable()->after('sizes');
        });

        $products = DB::table('products')->select(['id', 'price', 'sizes'])->get();
        foreach ($products as $product) {
            $sizes = [];
            if (is_string($product->sizes)) {
                $decoded = json_decode($product->sizes, true);
                if (is_array($decoded)) {
                    $sizes = array_map('strval', $decoded);
                }
            }

            if (empty($sizes)) {
                $sizes = ['50ml', '100ml'];
            }

            $basePrice = (float) $product->price;
            $sizePrices = [];
            foreach ($sizes as $size) {
                $normalized = strtolower(trim($size));
                $sizePrices[$size] = str_contains($normalized, '100ml') ? $basePrice : $basePrice;
            }

            DB::table('products')->where('id', $product->id)->update([
                'size_prices' => json_encode($sizePrices),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('size_prices');
        });
    }
};
