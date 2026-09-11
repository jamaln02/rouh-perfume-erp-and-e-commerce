<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('finished_goods_sale_movements')) {
            Schema::create('finished_goods_sale_movements', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('sale_id')->constrained('sales')->restrictOnDelete();
                $table->foreignId('finished_product_inventory_id')
                    ->constrained(table: 'finished_products_inventory', indexName: 'fgsm_fpi_id_foreign')
                    ->restrictOnDelete();
                $table->string('movement_type', 30);
                $table->unsignedInteger('quantity');
                $table->unsignedInteger('previous_stock');
                $table->unsignedInteger('new_stock');
                $table->decimal('unit_cost', 15, 4)->default(0);
                $table->decimal('total_cost', 15, 4)->default(0);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->json('meta')->nullable();
                $table->timestamps();
                $table->unique(['sale_id', 'movement_type']);
                $table->index(['finished_product_inventory_id', 'created_at'], 'fgsm_fpi_id_created_at_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('finished_goods_sale_movements');
    }
};
