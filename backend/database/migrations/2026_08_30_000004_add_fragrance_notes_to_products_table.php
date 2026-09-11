<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds fragrance pyramid notes and a precise fragrance family to products.
 *
 * - top_notes / heart_notes / base_notes: JSON arrays of note names
 *   (English). These power the product page fragrance pyramid display and
 *   the comparison feature.
 * - fragrance_family: a more granular classification than the existing
 *   `fragrance` column (e.g. "Oriental Woody", "Floral Aquatic",
 *   "Citrus Aromatic"). Kept as a separate column so the legacy
 *   `fragrance` default remains backward-compatible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'top_notes')) {
                $table->json('top_notes')->nullable()->after('fragrance');
            }
            if (!Schema::hasColumn('products', 'heart_notes')) {
                $table->json('heart_notes')->nullable()->after('top_notes');
            }
            if (!Schema::hasColumn('products', 'base_notes')) {
                $table->json('base_notes')->nullable()->after('heart_notes');
            }
            if (!Schema::hasColumn('products', 'fragrance_family')) {
                $table->string('fragrance_family', 100)->nullable()->after('base_notes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'fragrance_family')) {
                $table->dropColumn('fragrance_family');
            }
            if (Schema::hasColumn('products', 'base_notes')) {
                $table->dropColumn('base_notes');
            }
            if (Schema::hasColumn('products', 'heart_notes')) {
                $table->dropColumn('heart_notes');
            }
            if (Schema::hasColumn('products', 'top_notes')) {
                $table->dropColumn('top_notes');
            }
        });
    }
};
