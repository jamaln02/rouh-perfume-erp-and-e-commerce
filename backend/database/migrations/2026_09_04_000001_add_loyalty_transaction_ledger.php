<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('loyalty_transactions')) {
            Schema::create('loyalty_transactions', function (Blueprint $table) {
                $table->id();
                $table->string('user_id', 64)->nullable()->index();
                $table->unsignedBigInteger('customer_id')->nullable()->index();
                $table->string('order_id', 64)->nullable()->index();
                $table->enum('type', ['redeem', 'earn'])->index();
                $table->integer('points_delta');
                $table->decimal('discount_amount', 12, 2)->default(0);
                $table->string('status', 20)->default('active')->index();
                $table->string('reference', 180)->nullable();
                $table->text('metadata')->nullable();
                $table->timestamps();
                $table->unique(['order_id', 'type'], 'loyalty_order_type_unique');
            });
        }

        if (!Schema::hasColumn('orders', 'loyalty_points_redeemed')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->unsignedInteger('loyalty_points_redeemed')->default(0)->after('loyalty_points_earned');
                $table->decimal('loyalty_discount_amount', 12, 2)->default(0)->after('loyalty_points_redeemed');
            });
        }
        if (!Schema::hasColumn('orders', 'loyalty_redeemed_at')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->timestamp('loyalty_redeemed_at')->nullable()->after('loyalty_discount_amount');
            });
        }
        if (!Schema::hasColumn('coupon_redemptions', 'voided_at')) {
            Schema::table('coupon_redemptions', function (Blueprint $table) {
                $table->timestamp('voided_at')->nullable()->index()->after('redeemed_at');
                $table->string('void_reason', 500)->nullable()->after('voided_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('coupon_redemptions', 'void_reason')) {
            Schema::table('coupon_redemptions', function (Blueprint $table) { $table->dropColumn(['void_reason', 'voided_at']); });
        }
        if (Schema::hasColumn('orders', 'loyalty_redeemed_at')) Schema::table('orders', function (Blueprint $table) { $table->dropColumn('loyalty_redeemed_at'); });
        if (Schema::hasColumn('orders', 'loyalty_discount_amount')) Schema::table('orders', function (Blueprint $table) { $table->dropColumn('loyalty_discount_amount'); });
        if (Schema::hasColumn('orders', 'loyalty_points_redeemed')) Schema::table('orders', function (Blueprint $table) { $table->dropColumn('loyalty_points_redeemed'); });
        Schema::dropIfExists('loyalty_transactions');
    }
};
