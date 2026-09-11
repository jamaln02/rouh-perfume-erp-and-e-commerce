<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Align order_items.order_id with orders.id on MySQL/MariaDB.
     *
     * The original order_items migration created order_id as an UNSIGNED BIGINT
     * and pointed it at the legacy customer_orders table. The current orders.id
     * is a VARCHAR(64) UUID-style key. SQLite had dedicated rebuild migrations,
     * but MySQL was incorrectly skipped, leaving the production/staging schema
     * with an integer order_id. This migration fixes the MySQL schema explicitly.
     */
    public function up(): void
    {
        if (Schema::hasTable('order_items') && Schema::hasTable('orders')) {
            $orphans = DB::table('order_items as oi')
                ->leftJoin('orders as o', 'o.id', '=', 'oi.order_id')
                ->whereNotNull('oi.order_id')
                ->whereNull('o.id')
                ->count();
            if ($orphans > 0) {
                throw new \RuntimeException("Cannot migrate order_items: {$orphans} orphaned order_id values exist. Repair the data before running migrations.");
            }
        }
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        // Drop any existing FK(s) attached to order_items.order_id before changing its type.
        $constraints = DB::select(
            <<<'SQL'
            SELECT CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'order_items'
              AND COLUMN_NAME = 'order_id'
              AND REFERENCED_TABLE_NAME IS NOT NULL
            SQL
        );

        foreach ($constraints as $constraint) {
            $name = (string) $constraint->CONSTRAINT_NAME;
            DB::statement('ALTER TABLE `order_items` DROP FOREIGN KEY `' . str_replace('`', '``', $name) . '`');
        }

        // orders.id is VARCHAR(64), so order_items.order_id must use the same compatible type.
        DB::statement('ALTER TABLE `order_items` MODIFY `order_id` VARCHAR(64) NOT NULL');

        // Ensure the index exists for the FK/query path without creating a duplicate.
        $indexes = DB::select(
            <<<'SQL'
            SELECT INDEX_NAME
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'order_items'
              AND COLUMN_NAME = 'order_id'
            SQL
        );

        $hasOrderIdIndex = false;
        foreach ($indexes as $index) {
            if ((string) $index->INDEX_NAME !== 'PRIMARY') {
                $hasOrderIdIndex = true;
                break;
            }
        }

        if (! $hasOrderIdIndex) {
            DB::statement('CREATE INDEX `order_items_order_id_index` ON `order_items` (`order_id`)');
        }

        // Correct FK target: current UUID/string orders table.
        DB::statement(
            'ALTER TABLE `order_items` ADD CONSTRAINT `order_items_order_id_foreign` '
            . 'FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE'
        );
    }

    public function down(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        $constraints = DB::select(
            <<<'SQL'
            SELECT CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'order_items'
              AND COLUMN_NAME = 'order_id'
              AND REFERENCED_TABLE_NAME = 'orders'
            SQL
        );

        foreach ($constraints as $constraint) {
            $name = (string) $constraint->CONSTRAINT_NAME;
            DB::statement('ALTER TABLE `order_items` DROP FOREIGN KEY `' . str_replace('`', '``', $name) . '`');
        }

        DB::statement('ALTER TABLE `order_items` MODIFY `order_id` BIGINT UNSIGNED NOT NULL');

        DB::statement(
            'ALTER TABLE `order_items` ADD CONSTRAINT `order_items_order_id_foreign` '
            . 'FOREIGN KEY (`order_id`) REFERENCES `customer_orders` (`id`) ON DELETE CASCADE'
        );
    }
};
