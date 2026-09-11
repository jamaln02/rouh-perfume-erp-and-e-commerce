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
        Schema::table('order_consumptions', function (Blueprint $table) {
            $table->decimal('total_material_cost', 15, 2)->default(0)->after('notes');
            $table->decimal('labor_cost', 15, 2)->default(0)->after('total_material_cost');
            $table->decimal('electricity_cost', 15, 2)->default(0)->after('labor_cost');
            $table->decimal('overhead_cost', 15, 2)->default(0)->after('electricity_cost');
            $table->decimal('total_production_cost', 15, 2)->default(0)->after('overhead_cost');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_consumptions', function (Blueprint $table) {
            $table->dropColumn([
                'total_material_cost',
                'labor_cost',
                'electricity_cost',
                'overhead_cost',
                'total_production_cost',
            ]);
        });
    }
};
