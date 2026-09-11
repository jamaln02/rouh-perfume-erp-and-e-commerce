<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Correct the order_items table after the FK-rebuild migration.
     *
     * The previous rebuild (2026_08_06_000001) successfully retargeted the
     * order_id foreign key to the orders table, but because SQLite's table
     * rebuild through raw CREATE TABLE did not include the PRIMARY KEY
     * definition or the order_id index, the id column lost its auto-increment
     * primary key and the order_id index was dropped.
     *
     * This migration rebuilds order_items once more with:
     *   - id INTEGER PRIMARY KEY AUTOINCREMENT (preserve existing ids)
     *   - order_id as varchar(64) to match the orders.id UUID primary key
     *   - the order_id index
     *   - the corrected foreign key to orders.id
     */
    public function up(): void
    {
        // SQLite-only table rebuild; on MySQL the FK is correct from creation.
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }
        $temp = 'order_items_rebuild2';

        $columnsSql = $this->buildColumnsSql();
        $foreignKeysSql = $this->buildForeignKeysSql();

        DB::statement('PRAGMA foreign_keys=OFF');

        try {
            DB::transaction(function () use ($temp, $columnsSql, $foreignKeysSql): void {
                DB::statement("CREATE TABLE {$temp} ({$columnsSql}, {$foreignKeysSql})");
                // Copy all columns by name (safe and explicit).
                DB::statement("INSERT INTO {$temp} (id, order_id, blend_id, bottle_type, bottle_size_ml, oil_mix, quantity, unit_cost, unit_price, line_total, line_cost, packaging_items, packaging_cost, notes, created_at, updated_at, product_variant_id, product_id, product_name, size, price, line_discount_amount, recipe_id)
                    SELECT id, order_id, blend_id, bottle_type, bottle_size_ml, oil_mix, quantity, unit_cost, unit_price, line_total, line_cost, packaging_items, packaging_cost, notes, created_at, updated_at, product_variant_id, product_id, product_name, size, price, line_discount_amount, recipe_id FROM order_items");
                DB::statement("DROP TABLE order_items");
                DB::statement("ALTER TABLE {$temp} RENAME TO order_items");
            });
        } finally {
            DB::statement('PRAGMA foreign_keys=ON');
        }
    }

    public function down(): void
    {
        // Intentionally left no-op; the corrected schema is the desired state.
    }

    private function buildColumnsSql(): string
    {
        return implode(",\n", [
            "`id` INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT",
            "`order_id` varchar(64) NOT NULL",
            "`blend_id` INTEGER NULL",
            "`bottle_type` varchar NULL",
            "`bottle_size_ml` varchar NULL",
            "`oil_mix` TEXT NULL",
            "`quantity` INTEGER NOT NULL DEFAULT 1",
            "`unit_cost` numeric NOT NULL DEFAULT 0",
            "`unit_price` numeric NOT NULL DEFAULT 0",
            "`line_total` numeric NOT NULL DEFAULT 0",
            "`line_cost` numeric NOT NULL DEFAULT 0",
            "`packaging_items` TEXT NULL",
            "`packaging_cost` numeric NOT NULL DEFAULT 0",
            "`notes` TEXT NULL",
            "`created_at` datetime NULL",
            "`updated_at` datetime NULL",
            "`product_variant_id` varchar NULL",
            "`line_discount_amount` numeric NOT NULL DEFAULT 0",
            "`recipe_id` varchar NULL",
            "`price` numeric NOT NULL DEFAULT 0",
            "`product_id` varchar NULL",
            "`product_name` varchar NULL",
            "`size` varchar NULL",
        ]);
    }

    private function buildForeignKeysSql(): string
    {
        return implode(",\n", [
            "FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE",
            "FOREIGN KEY (`blend_id`) REFERENCES `custom_blends` (`id`) ON DELETE SET NULL",
            "FOREIGN KEY (`product_variant_id`) REFERENCES `product_variants` (`id`) ON DELETE SET NULL",
            "FOREIGN KEY (`recipe_id`) REFERENCES `recipes` (`id`) ON DELETE SET NULL",
        ]);
    }
};
