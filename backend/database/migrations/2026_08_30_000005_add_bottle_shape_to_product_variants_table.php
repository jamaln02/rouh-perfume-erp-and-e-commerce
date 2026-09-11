<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds bottle_shape to the product_variants table.
 *
 * ROUH offers several bottle shapes (round, square, rectangular, cylindrical,
 * flacon, travel). This column lets the admin record the physical bottle
 * shape per variant so the storefront can display and filter by shape.
 *
 * Common values: round | square | rectangular | cylindrical | flacon | travel
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            if (!Schema::hasColumn('product_variants', 'bottle_shape')) {
                $table->string('bottle_shape', 50)->nullable()->after('volume_ml');
            }
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            if (Schema::hasColumn('product_variants', 'bottle_shape')) {
                $table->dropColumn('bottle_shape');
            }
        });
    }
};
