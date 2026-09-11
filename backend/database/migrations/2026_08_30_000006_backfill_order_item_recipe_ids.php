<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            DB::table('order_items')
                ->whereNull('recipe_id')
                ->whereNotNull('product_variant_id')
                ->orderBy('id')
                ->get()
                ->each(function ($item) {
                    $recipeId = DB::table('recipes')
                        ->where('product_variant_id', $item->product_variant_id)
                        ->where('is_active', true)
                        ->orderByDesc('version')
                        ->value('id');

                    if ($recipeId) {
                        DB::table('order_items')
                            ->where('id', $item->id)
                            ->update(['recipe_id' => $recipeId, 'updated_at' => now()]);
                    }
                });
        });
    }

    public function down(): void
    {
        // Intentionally left empty: removing frozen recipe references would destroy
        // historical order preparation provenance.
    }
};
