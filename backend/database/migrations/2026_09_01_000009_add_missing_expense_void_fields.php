<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('expenses')) {
            return;
        }

        Schema::table('expenses', function (Blueprint $table) {
            if (!Schema::hasColumn('expenses', 'voided_at')) {
                $table->timestamp('voided_at')->nullable();
            }
            if (!Schema::hasColumn('expenses', 'voided_by')) {
                $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('expenses', 'void_reason')) {
                $table->text('void_reason')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('expenses')) {
            return;
        }

        Schema::table('expenses', function (Blueprint $table) {
            if (Schema::hasColumn('expenses', 'voided_by')) {
                $table->dropForeign(['voided_by']);
                $table->dropColumn('voided_by');
            }
            if (Schema::hasColumn('expenses', 'voided_at')) {
                $table->dropColumn('voided_at');
            }
            if (Schema::hasColumn('expenses', 'void_reason')) {
                $table->dropColumn('void_reason');
            }
        });
    }
};