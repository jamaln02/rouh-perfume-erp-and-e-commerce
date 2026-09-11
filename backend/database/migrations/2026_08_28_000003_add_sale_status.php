<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            if (!Schema::hasColumn('sales', 'sale_status')) {
                $table->string('sale_status', 20)->default('active')->index();
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('sales', 'sale_status')) {
            Schema::table('sales', fn (Blueprint $table) => $table->dropColumn('sale_status'));
        }
    }
};
