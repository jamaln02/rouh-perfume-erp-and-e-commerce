<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            if (!Schema::hasColumn('expenses', 'status')) {
                $table->string('status', 20)->default('draft')->after('notes');
            }
            if (!Schema::hasColumn('expenses', 'payment_account_id')) {
                $table->foreignId('payment_account_id')->nullable()->after('status')->constrained('financial_accounts')->nullOnDelete();
            }
            if (!Schema::hasColumn('expenses', 'attachment_path')) {
                $table->string('attachment_path', 500)->nullable()->after('payment_account_id');
            }
            if (!Schema::hasColumn('expenses', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('attachment_path')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('expenses', 'confirmed_by')) {
                $table->foreignId('confirmed_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('expenses', 'confirmed_at')) {
                $table->timestamp('confirmed_at')->nullable()->after('confirmed_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            foreach (['payment_account_id', 'created_by', 'confirmed_by'] as $fk) {
                if (Schema::hasColumn('expenses', $fk)) {
                    try {
                        $table->dropForeign([$fk]);
                    } catch (\Throwable $e) {
                        // ignore
                    }
                }
            }

            foreach (['status', 'payment_account_id', 'attachment_path', 'created_by', 'confirmed_by', 'confirmed_at'] as $column) {
                if (Schema::hasColumn('expenses', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
