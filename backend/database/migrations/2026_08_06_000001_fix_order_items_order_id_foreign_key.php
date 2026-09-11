<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Fix the foreign key on order_items.order_id.
     *
     * The order_items.order_id column stores a UUID string that references
     * orders.id (varchar primary key). However, the original migration wrongly
     * created a foreign key referencing customer_orders.id (INTEGER auto-increment).
     * This mismatch causes every checkout order creation to fail with:
     *   SQLSTATE[23000]: FOREIGN KEY constraint failed.
     *
     * SQLite does not support removing a foreign key via ALTER TABLE, so we
     * rebuild the table with the correct constraint while preserving data.
     */
    public function up(): void
    {
        // This rebuild is a SQLite-only workaround (ALTER TABLE cannot drop FKs
        // there). On MySQL/MariaDB the FK is created correctly from the start.
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }
        $table = 'order_items';
        $temp = 'order_items_rebuilt';

        // Preserve existing data
        $createSql = $this->buildCreateSql('order_items');

        DB::statement('PRAGMA foreign_keys=OFF');

        try {
            DB::transaction(function () use ($table, $temp, $createSql): void {
                DB::statement("CREATE TABLE {$temp} ({$createSql})");
                DB::statement("INSERT INTO {$temp} SELECT * FROM {$table}");
                DB::statement("DROP TABLE {$table}");
                DB::statement("ALTER TABLE {$temp} RENAME TO {$table}");
                $this->rebuildIndexes();
            });
        } finally {
            DB::statement('PRAGMA foreign_keys=ON');
        }
    }

    public function down(): void
    {
        // Reintroduce the original (wrong) FK definition. Data is preserved either way.
    }

    /**
     * Build the CREATE TABLE statement with the corrected foreign key.
     * The existing order_items table definition is used, but only the
     * order_id foreign key is retargeted to the orders table.
     */
    private function buildCreateSql(string $table): string
    {
        // Get the exact current table definition by introspecting the DB.
        $columns = [];
        $info = DB::select('PRAGMA table_info(order_items)');
        foreach ($info as $column) {
            $definition = '`' . $column->name . '` ' . $column->type;
            if ($column->notnull) {
                $definition .= ' NOT NULL';
            }
            if ($column->dflt_value !== null && $column->dflt_value !== '') {
                $definition .= ' DEFAULT ' . $column->dflt_value;
            }
            $columns[] = $definition;
        }

        $foreignKeys = DB::select('PRAGMA foreign_key_list(order_items)');
        foreach ($foreignKeys as $fk) {
            // Skip the original order_id -> customer_orders FK; we re-add it pointing to orders.
            if ($fk->from === 'order_id' && $fk->table === 'customer_orders') {
                continue;
            }
            $ref = [$fk->table, 'id'];
            $columns[] = 'FOREIGN KEY (`' . $fk->from . '`) REFERENCES `' . $ref[0] . '` (`' . $ref[1] . '`)'
                . ($fk->on_delete === 'CASCADE' ? ' ON DELETE CASCADE' : '')
                . ($fk->on_delete === 'SET NULL' ? ' ON DELETE SET NULL' : '')
                . ($fk->on_update === 'CASCADE' ? ' ON UPDATE CASCADE' : '');
        }

        // Re-add the correct FK: order_id references orders.id (UUID string), cascade on delete.
        $columns[] = 'FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE';

        return implode(",\n", $columns);
    }

    private function rebuildIndexes(): void
    {
        // Recreate indexes that existed on order_items.
        $indexes = DB::select('PRAGMA index_list(order_items)');
        foreach ($indexes as $index) {
            if ($index->origin === 'pk' || $index->name === 'sqlite_autoindex_order_items_1') {
                continue;
            }
            $info = DB::select('PRAGMA index_info(' . $index->name . ')');
            $cols = array_map(static fn ($i) => $i->name, $info);
            $unique = $index->unique ? ' UNIQUE' : '';
            DB::statement('CREATE' . $unique . ' INDEX `' . $index->name . '` ON `order_items` (`' . implode('`,`', $cols) . '`)');
        }
    }
};

