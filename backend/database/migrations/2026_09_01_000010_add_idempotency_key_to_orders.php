<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'idempotency_key')) {
                $table->string('idempotency_key', 100)->nullable()->unique();
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'idempotency_key')) {
            Schema::table('orders', fn (Blueprint $table) => $table->dropUnique(['idempotency_key'])->dropColumn('idempotency_key'));
        }
    }
};