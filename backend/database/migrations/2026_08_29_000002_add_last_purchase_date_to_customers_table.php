<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('customers', 'last_purchase_date')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->date('last_purchase_date')->nullable()->after('loyalty_points');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('customers', 'last_purchase_date')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn('last_purchase_date');
            });
        }
    }
};
