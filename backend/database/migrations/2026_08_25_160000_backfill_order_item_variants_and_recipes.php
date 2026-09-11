<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
 public function up(): void { DB::transaction(function(){ foreach(DB::table('order_items')->whereNull('product_variant_id')->whereNotNull('product_id')->get() as $item){ if(!$item->size)continue; $variant=DB::table('product_variants')->where('product_id',$item->product_id)->where('size_label',$item->size)->where('is_active',true)->first(); if(!$variant)continue; $recipe=DB::table('recipes')->where('product_variant_id',$variant->id)->where('is_active',true)->orderByDesc('version')->value('id'); DB::table('order_items')->where('id',$item->id)->update(['product_variant_id'=>$variant->id,'recipe_id'=>$recipe,'updated_at'=>now()]); } }); }
 public function down(): void { }
};
