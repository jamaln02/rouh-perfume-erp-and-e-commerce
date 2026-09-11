<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Performance Index Migration — ROUH-ERP
 * ========================================
 *
 * Problem 4 (Performance): DB query optimization via indexes.
 *
 * This migration ONLY adds indexes. It does NOT change any table schema,
 * does NOT add/drop columns, and does NOT delete or modify any data.
 *
 * Rationale for each index (all target hot-path queries identified in the
 * controllers / services):
 *
 *  1. order_items.order_id  — Every order-detail / order-preparation query
 *     loads items by order_id. The 2026_08_06_000002 rebuild migration
 *     DROPPED this index (its comment claims it re-adds it, but the SQL
 *     contains no CREATE INDEX). Re-adding it is critical.
 *
 *  2. order_items.product_id — Reports & stock dashboards aggregate by
 *     product across order_items. Without this index a full table scan
 *     runs on every product sales report.
 *
 *  3. order_items.size — The per-product recipe / preparation feature
 *     (Problem 2) filters items by size when matching a variant.
 *
 *  4. order_consumption_items.order_item_id — The new
 *     perProductMaterialRequirements() joins consumption items back to
 *     order_items. No index existed → nested-loop scan.
 *
 *  5. products.category_id — The catalog / shop pages filter by category.
 *     Only `featured`, `best_seller`, `created_at` were indexed.
 *
 *  6. products.is_active — (REMOVED: products table has no is_active column;
 *     only users, product_variants, materials, etc. have it. The
 *     product_variants(product_id, is_active) composite index already exists
 *     from the create migration.)
 *
 *  7. recipes.is_active (partial) — activeRecipe() lookups scan the
 *     whole recipes table for is_active=1 rows per variant. A partial
 *     index shrinks this to only active rows.
 *
 *  8. inventory_movements.movement_date — Date-range reports (daily/weekly
 *     stock movement) scanned the whole table ordered by date.
 *
 *  9. financial_transactions.transaction_date — Profit/loss & ledger
 *     reports filter by date range.
 *
 * 10. orders.created_at — Order list sorting & date-range filters.
 *
 * 11. audit_logs.created_at — Audit trail pagination by date.
 *
 * All indexes use `IF NOT EXISTS`-style guards (checked via Schema or
 * wrapped in try/catch for SQLite) so the migration is idempotent and
 * safe to re-run.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---- order_items ----
        // Re-add the order_id index that was lost in the 2026_08_06_000002
        // rebuild. (SQLite cannot add indexes via Schema::table on a column
        // that is part of a foreign key cleanly, so use raw CREATE INDEX.)
        $this->createIndexIfMissing('order_items', 'order_items_order_id_index', 'order_id');
        $this->createIndexIfMissing('order_items', 'order_items_product_id_index', 'product_id');
        $this->createIndexIfMissing('order_items', 'order_items_size_index', 'size');

        // ---- order_consumption_items ----
        $this->createIndexIfMissing(
            'order_consumption_items',
            'order_consumption_items_order_item_id_index',
            'order_item_id'
        );

        // ---- products ----
        // (NOTE: products table has no is_active column — only product_variants,
        //  materials, users do. The product_variants(product_id, is_active)
        //  composite index already exists from the create migration.)
        $this->createIndexIfMissing('products', 'products_category_id_index', 'category_id');

        // ---- recipes: partial index on is_active (SQLite only; MySQL gets a
        // regular index because it does not support partial indexes) ----
        if (DB::getDriverName() === 'sqlite') {
            $this->createPartialIndex(
                'recipes',
                'recipes_is_active_partial_index',
                'is_active',
                'is_active = 1'
            );
        } else {
            $this->createIndexIfMissing('recipes', 'recipes_is_active_partial_index', 'is_active');
        }

        // ---- inventory_movements ----
        $this->createIndexIfMissing(
            'inventory_movements',
            'inventory_movements_movement_date_index',
            'movement_date'
        );

        // ---- financial_transactions ----
        $this->createIndexIfMissing(
            'financial_transactions',
            'financial_transactions_transaction_date_index',
            'transaction_date'
        );

        // ---- orders ----
        $this->createIndexIfMissing('orders', 'orders_created_at_index', 'created_at');

        // ---- audit_logs ----
        if (Schema::hasTable('audit_logs')) {
            $this->createIndexIfMissing('audit_logs', 'audit_logs_created_at_index', 'created_at');
        }
    }

    public function down(): void
    {
        // Drop all indexes created by up(). Each drop is guarded.
        $this->dropIndexIfExists('order_items', 'order_items_order_id_index');
        $this->dropIndexIfExists('order_items', 'order_items_product_id_index');
        $this->dropIndexIfExists('order_items', 'order_items_size_index');
        $this->dropIndexIfExists('order_consumption_items', 'order_consumption_items_order_item_id_index');
        $this->dropIndexIfExists('products', 'products_category_id_index');
        $this->dropIndexIfExists('recipes', 'recipes_is_active_partial_index');
        $this->dropIndexIfExists('inventory_movements', 'inventory_movements_movement_date_index');
        $this->dropIndexIfExists('financial_transactions', 'financial_transactions_transaction_date_index');
        $this->dropIndexIfExists('orders', 'orders_created_at_index');
        $this->dropIndexIfExists('audit_logs', 'audit_logs_created_at_index');
    }

    /**
     * Create a single-column index only if it does not already exist.
     * Uses SQLite's sqlite_master to check, then raw CREATE INDEX.
     */
    private function createIndexIfMissing(string $table, string $indexName, string $column): void
    {
        if (!Schema::hasTable($table)) {
            return;
        }
        if ($this->indexExists($table, $indexName)) {
            return;
        }
        DB::statement("CREATE INDEX `{$indexName}` ON `{$table}` (`{$column}`)");
    }

    /**
     * Create a partial index (SQLite WHERE clause) if it does not exist.
     */
    private function createPartialIndex(string $table, string $indexName, string $column, string $where): void
    {
        if (!Schema::hasTable($table)) {
            return;
        }
        if ($this->indexExists($table, $indexName)) {
            return;
        }
        DB::statement("CREATE INDEX \"{$indexName}\" ON \"{$table}\" (\"{$column}\") WHERE {$where}");
    }

    /**
     * Drop an index if it exists.
     */
    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (!Schema::hasTable($table)) {
            return;
        }
        if (!$this->indexExists($table, $indexName)) {
            return;
        }
        DB::statement("DROP INDEX `{$indexName}` ON `{$table}`");
    }

    /**
     * Check whether an index exists.
     */
    private function indexExists(string $table, string $indexName): bool
    {
        if (DB::getDriverName() === 'sqlite') {
            $count = DB::table('sqlite_master')
                ->where('type', 'index')
                ->where('name', $indexName)
                ->count();
            return $count > 0;
        }
        foreach (Schema::getIndexes($table) as $definition) {
            if (($definition['name'] ?? '') === $indexName) return true;
        }
        return false;
    }
};
