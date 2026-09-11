<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
 public function up(): void { if (Schema::hasTable('store_settings')) DB::table('store_settings')->where('key','accounting_start_date')->delete(); }
 public function down(): void { /* Intentionally non-restoring: accounting start belongs to opening_balances. */ }
};
