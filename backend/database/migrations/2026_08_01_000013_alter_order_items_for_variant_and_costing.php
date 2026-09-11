<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('order_items', 'product_variant_id')) {
                $table->uuid('product_variant_id')->nullable()->after('product_id');
            }
            if (!Schema::hasColumn('order_items', 'line_discount_amount')) {
                $table->decimal('line_discount_amount', 15, 2)->default(0)->after('unit_price');
            }
            if (!Schema::hasColumn('order_items', 'line_total')) {
                $table->decimal('line_total', 15, 2)->default(0)->after('line_discount_amount');
            }
            if (!Schema::hasColumn('order_items', 'recipe_id')) {
                $table->uuid('recipe_id')->nullable()->after('line_total');
            }
        });

        if (Schema::hasColumn('order_items', 'line_total') && Schema::hasColumn('order_items', 'unit_price')) {
            DB::table('order_items')->update([
                'line_total' => DB::raw('coalesce(unit_price, 0) * coalesce(quantity, 1)')
            ]);
        }

        Schema::table('order_items', function (Blueprint $table) {
            try {
                $table->foreign('product_variant_id')->references('id')->on('product_variants')->nullOnDelete();
            } catch (\Throwable $e) {
                // ignore if already exists or DB engine limitation
            }
            try {
                $table->foreign('recipe_id')->references('id')->on('recipes')->nullOnDelete();
            } catch (\Throwable $e) {
                // ignore if already exists or DB engine limitation
            }
            $table->index('product_variant_id');
            $table->index('recipe_id');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            foreach (['product_variant_id', 'recipe_id'] as $fk) {
                if (Schema::hasColumn('order_items', $fk)) {
                    try {
                        $table->dropForeign([$fk]);
                    } catch (\Throwable $e) {
                        // ignore
                    }
                }
            }

            foreach (['product_variant_id', 'line_discount_amount', 'line_total', 'recipe_id'] as $column) {
                if (Schema::hasColumn('order_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
