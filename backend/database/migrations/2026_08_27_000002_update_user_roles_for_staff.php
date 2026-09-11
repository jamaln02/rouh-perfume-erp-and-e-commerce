<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
    public function up(): void {
        DB::table('user_roles')->where('role', 'user')->update(['role' => 'customer']);
        Schema::table('user_roles', function (Blueprint $table) { $table->index(['role', 'user_id']); });
    }
    public function down(): void {
        DB::table('user_roles')->where('role', 'customer')->update(['role' => 'user']);
        Schema::table('user_roles', function (Blueprint $table) { $table->dropIndex(['role', 'user_id']); });
    }
};
