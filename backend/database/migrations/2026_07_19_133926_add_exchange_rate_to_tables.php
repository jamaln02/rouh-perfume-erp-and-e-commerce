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
        Schema::table('expenses', function (Blueprint $table) {
            $table->decimal('exchange_rate', 10, 2)->nullable()->after('currency');
            $table->decimal('amount_syp', 15, 2)->nullable()->after('exchange_rate');
        });

        Schema::table('raw_materials', function (Blueprint $table) {
            $table->decimal('exchange_rate', 10, 2)->nullable()->after('currency');
            $table->decimal('cost_per_unit_syp', 15, 2)->nullable()->after('exchange_rate');
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->string('currency')->default('SYP')->after('total_cost');
            $table->decimal('exchange_rate', 10, 2)->nullable()->after('currency');
            $table->decimal('total_cost_syp', 15, 2)->nullable()->after('exchange_rate');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->string('currency')->default('SYP')->after('total_price');
            $table->decimal('exchange_rate', 10, 2)->nullable()->after('currency');
            $table->decimal('total_price_syp', 15, 2)->nullable()->after('exchange_rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn(['exchange_rate', 'amount_syp']);
        });

        Schema::table('raw_materials', function (Blueprint $table) {
            $table->dropColumn(['exchange_rate', 'cost_per_unit_syp']);
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropColumn(['currency', 'exchange_rate', 'total_cost_syp']);
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['currency', 'exchange_rate', 'total_price_syp']);
        });
    }
};
