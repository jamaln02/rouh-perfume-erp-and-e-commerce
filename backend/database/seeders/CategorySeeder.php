<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Men', 'name_ar' => 'رجالي', 'slug' => 'men'],
            ['name' => 'Women', 'name_ar' => 'نسائي', 'slug' => 'women'],
            ['name' => 'Unisex', 'name_ar' => 'للجنسين', 'slug' => 'unisex'],
        ];

        foreach ($categories as $category) {
            $existing = DB::table('categories')->where('slug', $category['slug'])->first();
            if ($existing) {
                DB::table('categories')->where('id', $existing->id)->update([
                    'name' => $category['name'],
                    'name_ar' => $category['name_ar'],
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('categories')->insert([
                    'id' => (string) Str::uuid(),
                    'name' => $category['name'],
                    'name_ar' => $category['name_ar'],
                    'slug' => $category['slug'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
