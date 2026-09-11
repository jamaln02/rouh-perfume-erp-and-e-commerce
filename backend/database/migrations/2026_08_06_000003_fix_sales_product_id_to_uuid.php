<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Normalize sales.product_id to the UUID/string type used by products.id
     * without ever dropping or recreating the sales table.
     */
    public function up(): void
    {
        if (!Schema::hasTable('sales') || !Schema::hasColumn('sales', 'product_id')) {
            return;
        }

        try {
            Schema::table('sales', function (Blueprint $table): void {
                $table->dropForeign(['product_id']);
            });
        } catch (\Throwable $e) {
            // The FK may already have been removed by a later idempotent migration.
        }

        try {
            if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
                DB::statement('ALTER TABLE `sales` MODIFY `product_id` VARCHAR(36) NULL');
            } else {
                Schema::table('sales', function (Blueprint $table): void {
                    $table->string('product_id', 36)->nullable()->change();
                });
            }
        } catch (\Throwable $e) {
            // Keep the migration fail-closed. A column conversion that cannot be
            // performed safely must stop deployment instead of mutating data.
            throw $e;
        }

        if (Schema::hasTable('products')) {
            $orphans = DB::table('sales as s')
                ->whereNotNull('s.product_id')
                ->where('s.product_id', '!=', '')
                ->whereNotExists(function ($query): void {
                    $query->select(DB::raw(1))
                        ->from('products as p')
                        ->whereColumn('p.id', 's.product_id');
                })
                ->count();

            if ($orphans > 0) {
                throw new \RuntimeException("Sales migration aborted: {$orphans} sales reference missing products. Resolve orphan product IDs before deployment.");
            }

            // Recreate the FK. Failure is intentional: silently leaving the sales
            // table without referential integrity is worse than failing deployment.
            Schema::table('sales', function (Blueprint $table): void {
                $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        // Intentionally a no-op: reversing this migration must never destroy the
        // sales history. A downgrade, when required, should be handled by a
        // dedicated data-preserving migration.
    }
};
