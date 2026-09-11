<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            if (!Schema::hasColumn('expenses', 'rent_duration_days')) {
                // Duration in days for rent expenses (e.g. a lease paid for N days)
                $table->integer('rent_duration_days')->nullable()->after('payment_method');
            }
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            if (Schema::hasColumn('expenses', 'rent_duration_days')) {
                $table->dropColumn('rent_duration_days');
            }
        });
    }
};
