<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bundles', function (Blueprint $table) {
            if (!Schema::hasColumn('bundles', 'offer_type')) $table->string('offer_type', 40)->default('fixed_bundle')->after('id');
            if (!Schema::hasColumn('bundles', 'paid_quantity')) $table->unsignedInteger('paid_quantity')->default(0)->after('offer_type');
            if (!Schema::hasColumn('bundles', 'free_quantity')) $table->unsignedInteger('free_quantity')->default(0)->after('paid_quantity');
            if (!Schema::hasColumn('bundles', 'target_product_id')) $table->uuid('target_product_id')->nullable()->after('free_quantity');
            if (!Schema::hasColumn('bundles', 'allowed_sizes')) $table->text('allowed_sizes')->nullable()->after('target_product_id');
            if (!Schema::hasColumn('bundles', 'price_by_size')) $table->text('price_by_size')->nullable()->after('allowed_sizes');
            if (!Schema::hasColumn('bundles', 'usage_limit')) $table->unsignedInteger('usage_limit')->nullable()->after('stock');
        });

        Schema::table('order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('order_items', 'offer_id')) $table->string('offer_id', 64)->nullable()->after('recipe_id');
            if (!Schema::hasColumn('order_items', 'offer_name')) $table->string('offer_name', 255)->nullable()->after('offer_id');
            $table->index('offer_id');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            if (Schema::hasColumn('order_items', 'offer_id')) { try { $table->dropIndex(['offer_id']); } catch (\Throwable $e) {} }
            foreach (['offer_name', 'offer_id'] as $column) if (Schema::hasColumn('order_items', $column)) $table->dropColumn($column);
        });
        Schema::table('bundles', function (Blueprint $table) {
            foreach (['usage_limit','price_by_size','allowed_sizes','target_product_id','free_quantity','paid_quantity','offer_type'] as $column) {
                if (Schema::hasColumn('bundles', $column)) $table->dropColumn($column);
            }
        });
    }
};
