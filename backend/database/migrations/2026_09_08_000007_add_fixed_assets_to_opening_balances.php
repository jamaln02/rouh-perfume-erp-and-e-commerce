<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up(): void {
  if (!Schema::hasColumn('opening_balances','total_fixed_assets_value')) Schema::table('opening_balances', fn(Blueprint $t)=>$t->decimal('total_fixed_assets_value',15,2)->default(0)->after('total_inventory_value'));
 }
 public function down(): void { if (Schema::hasColumn('opening_balances','total_fixed_assets_value')) Schema::table('opening_balances', fn(Blueprint $t)=>$t->dropColumn('total_fixed_assets_value')); }
};
