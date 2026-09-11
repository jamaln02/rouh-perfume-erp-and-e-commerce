<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `order_items.product_id` (and therefore the copied `sales.product_id`)
     * carries UUIDs that may not exist in the `products` table (legacy/orphaned
     * references). Enforcing a FOREIGN KEY on `sales.product_id` makes every
     * consumption-confirm insert fail with:
     *   SQLSTATE[23000]: FOREIGN KEY constraint failed
     *
     * The sale already stores `product_name`/`size_type` denormalized, so the
     * FK is not needed for correctness. We keep `product_id` as a plain varchar
     * and drop only the FK to `products`. The `customer_id` FK is preserved.
     */
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
        });
    }
};
