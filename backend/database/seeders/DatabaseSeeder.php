<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the database with a full working dataset based on the real
     * inventory provided by the owner.
     *
     * Order matters: each seeder depends on the tables populated by the
     * ones that run before it.
     */
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException('DatabaseSeeder is disabled in production. Run the explicit ROUH production bootstrap instead.');
        }

        $this->call([
            // Core: categories + admin/demo users + financial accounts
            CategorySeeder::class,
            AdminUserSeeder::class,
            FinanceCoreSeeder::class,

            // Real inventory: perfume oils, musks, alcohol, packaging,
            // equipment, and finished products with variants.
            ...(app()->environment('production') ? [] : [InventoryImportSeeder::class]),

            // Fragrance notes (top/heart/base) for the seeded perfumes.
            FragranceNotesSeeder::class,
            ProductCatalogContentSeeder::class,
            StandardProductSizesSeeder::class,
        ]);
    }
}
