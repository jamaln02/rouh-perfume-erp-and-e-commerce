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
        Schema::table('order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('order_items', 'product_id')) {
                $table->string('product_id', 64)->nullable()->after('order_id');
            }
            if (!Schema::hasColumn('order_items', 'product_name')) {
                $table->string('product_name', 255)->nullable()->after('product_id');
            }
            if (!Schema::hasColumn('order_items', 'size')) {
                $table->string('size', 50)->nullable()->after('product_name');
            }
            if (!Schema::hasColumn('order_items', 'price')) {
                $table->decimal('price', 12, 2)->default(0)->after('size');
            }
            if (!Schema::hasColumn('order_items', 'line_total')) {
                $table->decimal('line_total', 15, 2)->default(0)->after('price');
            }
            if (!Schema::hasColumn('order_items', 'product_variant_id')) {
                $table->string('product_variant_id', 64)->nullable()->after('line_total');
            }
        });

        // Copy data from existing columns if they exist
        if (Schema::hasColumn('order_items', 'price') && Schema::hasColumn('order_items', 'unit_price')) {
            DB::table('order_items')->update([
                'price' => DB::raw('unit_price')
            ]);
        }
        if (Schema::hasColumn('order_items', 'line_total') && Schema::hasColumn('order_items', 'unit_price') && Schema::hasColumn('order_items', 'quantity')) {
            DB::table('order_items')->update([
                'line_total' => DB::raw('coalesce(unit_price, 0) * coalesce(quantity, 1)')
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $columns = ['product_id', 'product_name', 'size', 'price', 'line_total', 'product_variant_id'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('order_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
