<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            // Make manufacturing-specific columns nullable for frontend compatibility
            if (Schema::hasColumn('order_items', 'bottle_type')) {
                $table->string('bottle_type')->nullable()->change();
            }
            if (Schema::hasColumn('order_items', 'bottle_size_ml')) {
                $table->string('bottle_size_ml')->nullable()->change();
            }
            if (Schema::hasColumn('order_items', 'blend_id')) {
                $table->unsignedBigInteger('blend_id')->nullable()->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            // Revert columns back to NOT NULL
            if (Schema::hasColumn('order_items', 'bottle_type')) {
                $table->string('bottle_type')->nullable(false)->change();
            }
            if (Schema::hasColumn('order_items', 'bottle_size_ml')) {
                $table->string('bottle_size_ml')->nullable(false)->change();
            }
            if (Schema::hasColumn('order_items', 'blend_id')) {
                $table->unsignedBigInteger('blend_id')->nullable(false)->change();
            }
        });
    }
};
