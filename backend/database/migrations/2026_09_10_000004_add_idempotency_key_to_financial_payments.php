<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('financial_payments') || Schema::hasColumn('financial_payments', 'idempotency_key')) {
            return;
        }

        Schema::table('financial_payments', function (Blueprint $table): void {
            $table->string('idempotency_key', 120)->nullable()->unique()->after('payment_number');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('financial_payments') || !Schema::hasColumn('financial_payments', 'idempotency_key')) {
            return;
        }

        Schema::table('financial_payments', function (Blueprint $table): void {
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn('idempotency_key');
        });
    }
};
