<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('stock_requests', function (Blueprint $table) {
            $table->foreignId('approved_by')->nullable()->after('requested_by')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->foreignId('rejected_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable()->after('rejected_by');
            $table->text('rejection_reason')->nullable()->after('rejected_at');
            $table->foreignId('fulfilled_by')->nullable()->after('rejection_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('fulfilled_at')->nullable()->after('fulfilled_by');
            $table->foreignId('purchase_id')->nullable()->after('fulfilled_at')->constrained('purchases')->nullOnDelete();
            $table->index(['status','approved_at']);
        });
    }
    public function down(): void
    {
        Schema::table('stock_requests', function (Blueprint $table) {
            $table->dropForeign(['approved_by']); $table->dropForeign(['rejected_by']); $table->dropForeign(['fulfilled_by']); $table->dropForeign(['purchase_id']);
            $table->dropIndex(['status','approved_at']);
            $table->dropColumn(['approved_by','approved_at','rejected_by','rejected_at','rejection_reason','fulfilled_by','fulfilled_at','purchase_id']);
        });
    }
};
