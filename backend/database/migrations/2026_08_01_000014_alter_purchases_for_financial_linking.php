<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            if (!Schema::hasColumn('purchases', 'supplier_id')) {
                $table->foreignId('supplier_id')->nullable()->after('supplier')->constrained('suppliers')->nullOnDelete();
            }
            if (!Schema::hasColumn('purchases', 'material_id')) {
                $table->foreignId('material_id')->nullable()->after('raw_material_id')->constrained('materials')->nullOnDelete();
            }
            if (!Schema::hasColumn('purchases', 'status')) {
                $table->string('status', 20)->default('draft')->after('notes');
            }
            if (!Schema::hasColumn('purchases', 'payment_status')) {
                $table->string('payment_status', 30)->default('unpaid')->after('status');
            }
            if (!Schema::hasColumn('purchases', 'paid_amount')) {
                $table->decimal('paid_amount', 15, 2)->default(0)->after('payment_status');
            }
            if (!Schema::hasColumn('purchases', 'remaining_amount')) {
                $table->decimal('remaining_amount', 15, 2)->default(0)->after('paid_amount');
            }
            if (!Schema::hasColumn('purchases', 'payment_account_id')) {
                $table->foreignId('payment_account_id')->nullable()->after('remaining_amount')->constrained('financial_accounts')->nullOnDelete();
            }
            if (!Schema::hasColumn('purchases', 'attachment_path')) {
                $table->string('attachment_path', 500)->nullable()->after('payment_account_id');
            }
            if (!Schema::hasColumn('purchases', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('attachment_path')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('purchases', 'confirmed_by')) {
                $table->foreignId('confirmed_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('purchases', 'confirmed_at')) {
                $table->timestamp('confirmed_at')->nullable()->after('confirmed_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            foreach (['supplier_id', 'material_id', 'payment_account_id', 'created_by', 'confirmed_by'] as $fk) {
                if (Schema::hasColumn('purchases', $fk)) {
                    try {
                        $table->dropForeign([$fk]);
                    } catch (\Throwable $e) {
                        // ignore
                    }
                }
            }

            foreach ([
                'supplier_id',
                'material_id',
                'status',
                'payment_status',
                'paid_amount',
                'remaining_amount',
                'payment_account_id',
                'attachment_path',
                'created_by',
                'confirmed_by',
                'confirmed_at',
            ] as $column) {
                if (Schema::hasColumn('purchases', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
