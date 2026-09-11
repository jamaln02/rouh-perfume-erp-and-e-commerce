<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (Schema::hasTable('expenses') && !Schema::hasColumn('expenses', 'status')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->string('status', 20)->default('posted')->after('payment_method');
                $table->timestamp('voided_at')->nullable()->after('status');
                $table->foreignId('voided_by')->nullable()->after('voided_at')->constrained('users')->nullOnDelete();
                $table->text('void_reason')->nullable()->after('voided_by');
            });
        }
    }
    public function down(): void {
        if (Schema::hasTable('expenses') && Schema::hasColumn('expenses', 'status')) {
            Schema::table('expenses', fn (Blueprint $table) => $table->dropForeign(['voided_by'])->dropColumn(['status','voided_at','voided_by','void_reason']));
        }
    }
};
