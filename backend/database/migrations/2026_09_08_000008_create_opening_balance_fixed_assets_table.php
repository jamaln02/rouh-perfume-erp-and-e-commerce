<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up(): void {
  if (Schema::hasTable('opening_balance_fixed_assets')) return;
  Schema::create('opening_balance_fixed_assets', function(Blueprint $t){
   $t->id();
   $t->foreignId('opening_balance_id')->constrained('opening_balances')->onDelete('cascade');
   $t->foreignId('fixed_asset_id')->nullable()->constrained('fixed_assets')->onDelete('set null');
   $t->string('name'); $t->string('name_ar')->nullable();
   $t->enum('category',['machinery','equipment','vehicle','furniture','electronics','building','land','other'])->default('equipment');
   $t->decimal('quantity',18,6)->default(1); $t->decimal('unit_cost',18,6)->default(0); $t->decimal('total_value',18,2)->default(0);
   $t->unsignedInteger('useful_life_months')->default(12); $t->decimal('salvage_value',18,2)->default(0);
   $t->text('notes')->nullable(); $t->timestamps();
   $t->index(['opening_balance_id','category']);
  });
 }
 public function down(): void { Schema::dropIfExists('opening_balance_fixed_assets'); }
};
