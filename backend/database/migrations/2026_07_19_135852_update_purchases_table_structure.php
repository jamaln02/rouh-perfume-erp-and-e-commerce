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
        Schema::table('purchases', function (Blueprint $table) {
            // Add new columns for the new system (only if they don't exist)
            if (!Schema::hasColumn('purchases', 'invoice_number')) {
                $table->string('invoice_number')->nullable()->after('supplier');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            // Remove only the new column we added
            if (Schema::hasColumn('purchases', 'invoice_number')) {
                $table->dropColumn('invoice_number');
            }
        });
    }
};
