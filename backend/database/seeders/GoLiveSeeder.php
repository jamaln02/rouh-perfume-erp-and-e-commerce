<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Clean go-live bootstrap.
 *
 * This seeder creates the master/catalog data only. The accounting opening
 * date, opening inventory and opening fixed assets are intentionally NOT
 * created here. They must be entered/reviewed from Financial Management →
 * Opening Balance so the opening balance is the single source of truth.
 */
class GoLiveSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CategorySeeder::class,
            AdminUserSeeder::class,
            FinanceCoreSeeder::class,
            InventoryImportSeeder::class,
            FragranceNotesSeeder::class,
            ProductCatalogContentSeeder::class,
            StandardProductSizesSeeder::class,
        ]);

        $this->command?->info('✓ Clean go-live bootstrap completed.');
        $this->command?->info('  Opening balance was intentionally NOT created.');
        $this->command?->info('  Set the accounting start date and import the physical count from Financial Management → Opening Balance.');
    }
}
