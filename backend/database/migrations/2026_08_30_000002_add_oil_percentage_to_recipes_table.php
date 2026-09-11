<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds oil_percentage and alcohol_percentage to the recipes table.
 *
 * ROUH perfumes are made-to-order. Each recipe (per product variant) now
 * carries an explicit oil concentration. The default is 32% which is the
 * standard eau de parfum concentration used across the ROUH catalogue.
 *
 * - oil_percentage:     percentage of the bottle volume occupied by perfume
 *                       oil (default 32, range 1–100).
 * - alcohol_percentage: complementary percentage (100 − oil_percentage).
 *                       Stored redundantly for query convenience but kept in
 *                       sync via the RecipeController and Recipe model.
 *
 * The actual oil_ml / alcohol_ml quantities are derived at preparation time
 * from the variant volume_ml × percentage, ensuring consistency regardless
 * of bottle size.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            if (!Schema::hasColumn('recipes', 'oil_percentage')) {
                $table->decimal('oil_percentage', 5, 2)->default(32)->after('is_active');
            }
            if (!Schema::hasColumn('recipes', 'alcohol_percentage')) {
                $table->decimal('alcohol_percentage', 5, 2)->default(68)->after('oil_percentage');
            }
        });
    }

    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            if (Schema::hasColumn('recipes', 'alcohol_percentage')) {
                $table->dropColumn('alcohol_percentage');
            }
            if (Schema::hasColumn('recipes', 'oil_percentage')) {
                $table->dropColumn('oil_percentage');
            }
        });
    }
};
