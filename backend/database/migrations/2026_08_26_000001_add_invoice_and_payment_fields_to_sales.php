<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            if (!Schema::hasColumn('sales', 'invoice_number')) $table->string('invoice_number', 80)->nullable()->unique();
            if (!Schema::hasColumn('sales', 'order_id')) $table->string('order_id', 80)->nullable()->index();
            if (!Schema::hasColumn('sales', 'payment_status')) $table->string('payment_status', 30)->default('unpaid')->index();
            if (!Schema::hasColumn('sales', 'paid_amount')) $table->decimal('paid_amount', 15, 2)->default(0);
            if (!Schema::hasColumn('sales', 'remaining_amount')) $table->decimal('remaining_amount', 15, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            foreach (['invoice_number','order_id','payment_status','paid_amount','remaining_amount'] as $column) {
                if (Schema::hasColumn('sales', $column)) {
                    if ($column === 'invoice_number') $table->dropUnique(['invoice_number']);
                    $table->dropColumn($column);
                }
            }
        });
    }
};
