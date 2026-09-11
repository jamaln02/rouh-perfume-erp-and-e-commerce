<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\StandardRecipeService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StandardProductSizesSeeder extends Seeder
{
    /**
     * Default catalog choices. They are deliberately editable after seeding.
     */
    public const SIZES = [
        '3ml tester',
        '3ml crystal',
        '5ml tester',
        '5ml pen',
        '10ml tester',
        '15ml laser',
        '30ml zara',
        '50ml',
        '60ml crystal',
        '100ml',
        '100ml yum yum blue',
        '100ml yum yum icecream',
    ];

    public const PRICES = [
        '3ml tester' => 100,
        '3ml crystal' => 500,
        '5ml tester' => 150,
        '5ml pen' => 200,
        '10ml tester' => 350,
        '15ml laser' => 500,
        '30ml zara' => 850,
        '50ml' => 1250,
        '60ml crystal' => 1750,
        '100ml' => 2000,
        '100ml yum yum blue' => 4500,
        '100ml yum yum icecream' => 4500,
    ];

    public function run(): void
    {
        foreach (Product::query()->get() as $product) {
            $this->applyToProduct($product);
        }

        $this->command?->info('  ✓ Standard product sizes/prices applied. All values remain editable.');
    }

    private function applyToProduct(Product $product): void
    {
        DB::transaction(function () use ($product): void {
            // Compatibility with the previous label used before the catalog was made flexible.
            ProductVariant::query()
                ->where('product_id', $product->id)
                ->where('size_label', '60ml gold crystal')
                ->whereNotExists(function ($q) use ($product) {
                    $q->select(DB::raw(1))
                        ->from('product_variants as v2')
                        ->whereColumn('v2.product_id', 'product_variants.product_id')
                        ->where('v2.size_label', '60ml crystal');
                })
                ->update(['size_label' => '60ml crystal', 'updated_at' => now()]);

            foreach (self::SIZES as $size) {
                $price = self::PRICES[$size];
                $variant = ProductVariant::query()
                    ->where('product_id', $product->id)
                    ->where('size_label', $size)
                    ->first();

                $payload = [
                    'volume_ml' => $this->volume($size),
                    'bottle_shape' => $this->shapeForSize($size),
                    'selling_price_default' => $price,
                    'is_active' => true,
                ];

                if ($variant) {
                    $variant->update($payload);
                } else {
                    $variant = ProductVariant::create([
                        'id' => (string) Str::uuid(),
                        'product_id' => $product->id,
                        'sku' => Str::slug($product->name) . '-' . Str::slug($size),
                        'name' => $product->name . ' ' . $size,
                        'size_label' => $size,
                        ...$payload,
                        'notes' => null,
                    ]);
                }

                app(StandardRecipeService::class)->ensureForVariant($variant);
            }

            // Preserve any additional custom sizes the manager has created.
            $activeVariants = $product->variants()
                ->where('is_active', true)
                ->orderBy('created_at')
                ->orderBy('id')
                ->get(['size_label', 'selling_price_default']);

            $sizes = [];
            $prices = [];
            foreach ($activeVariants as $variant) {
                $label = trim((string) $variant->size_label);
                if ($label === '') continue;
                $sizes[] = $label;
                $prices[$label] = (float) ($variant->selling_price_default ?? 0);
            }

            $product->sizes = $sizes;
            $product->size_prices = $prices;
            $reference = $activeVariants->firstWhere('size_label', '50ml') ?? $activeVariants->first();
            if ($reference) {
                $product->price = (float) ($reference->selling_price_default ?? $product->price ?? 0);
            }
            $product->save();
        });
    }

    private function volume(string $size): ?float
    {
        return preg_match('/(\d+(?:\.\d+)?)\s*ml/i', $size, $m) ? (float) $m[1] : null;
    }

    private function shapeForSize(string $size): ?string
    {
        $key = mb_strtolower($size);
        return match (true) {
            str_contains($key, 'tester') => 'tester',
            str_contains($key, 'crystal') => 'crystal',
            str_contains($key, 'pen') => 'pen',
            str_contains($key, 'laser') => 'laser',
            str_contains($key, 'zara') => 'zara',
            str_contains($key, 'yum yum blue') => 'yum_yum_blue',
            str_contains($key, 'yum yum icecream') => 'yum_yum_ice_cream',
            default => null,
        };
    }
}
