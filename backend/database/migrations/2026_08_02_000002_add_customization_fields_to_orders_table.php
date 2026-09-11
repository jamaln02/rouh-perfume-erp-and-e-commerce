<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'oil_grams')) {
                $table->decimal('oil_grams', 12, 2)->nullable()->after('notes');
            }
            if (!Schema::hasColumn('orders', 'bottle_size_ml')) {
                $table->decimal('bottle_size_ml', 12, 2)->nullable()->after('oil_grams');
            }
            if (!Schema::hasColumn('orders', 'is_gift')) {
                $table->boolean('is_gift')->default(false)->after('bottle_size_ml');
            }
            if (!Schema::hasColumn('orders', 'gift_type')) {
                $table->string('gift_type')->nullable()->after('is_gift');
            }
            if (!Schema::hasColumn('orders', 'gift_message')) {
                $table->text('gift_message')->nullable()->after('gift_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'gift_message')) {
                $table->dropColumn('gift_message');
            }
            if (Schema::hasColumn('orders', 'gift_type')) {
                $table->dropColumn('gift_type');
            }
            if (Schema::hasColumn('orders', 'is_gift')) {
                $table->dropColumn('is_gift');
            }
            if (Schema::hasColumn('orders', 'bottle_size_ml')) {
                $table->dropColumn('bottle_size_ml');
            }
            if (Schema::hasColumn('orders', 'oil_grams')) {
                $table->dropColumn('oil_grams');
            }
        });
    }
};
