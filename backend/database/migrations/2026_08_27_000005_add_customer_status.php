<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (!Schema::hasColumn('customers', 'status')) {
                $table->string('status', 20)->default('active')->after('notes')->index();
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('customers', 'status')) {
            Schema::table('customers', fn (Blueprint $table) => $table->dropColumn('status'));
        }
    }
};
