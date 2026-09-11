<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('audit_logs', 'reason')) $table->text('reason')->nullable()->after('status_code');
            if (!Schema::hasColumn('audit_logs', 'before_data')) $table->json('before_data')->nullable()->after('reason');
            if (!Schema::hasColumn('audit_logs', 'after_data')) $table->json('after_data')->nullable()->after('before_data');
            if (!Schema::hasColumn('audit_logs', 'changes')) $table->json('changes')->nullable()->after('after_data');
        });
    }

    public function down(): void
    {
        $cols = array_filter(['reason','before_data','after_data','changes'], fn($c) => Schema::hasColumn('audit_logs', $c));
        if ($cols) Schema::table('audit_logs', fn(Blueprint $table) => $table->dropColumn($cols));
    }
};
