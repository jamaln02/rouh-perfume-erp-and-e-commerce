<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds loyalty tracking columns to the orders table.
 *
 * - loyalty_awarded_at: timestamp marking when loyalty points were granted
 *   for this order. Acts as an idempotency guard so that re-running
 *   preparation or payment registration never double-awards points.
 * - loyalty_points_earned: the number of points credited to the customer,
 *   stored for reporting and audit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'loyalty_awarded_at')) {
                $table->timestamp('loyalty_awarded_at')->nullable()->after('cancelled_at');
            }
            if (!Schema::hasColumn('orders', 'loyalty_points_earned')) {
                $table->unsignedInteger('loyalty_points_earned')->default(0)->after('loyalty_awarded_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'loyalty_points_earned')) {
                $table->dropColumn('loyalty_points_earned');
            }
            if (Schema::hasColumn('orders', 'loyalty_awarded_at')) {
                $table->dropColumn('loyalty_awarded_at');
            }
        });
    }
};
