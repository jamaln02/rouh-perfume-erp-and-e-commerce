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
     * Default catalog choices.
     * These values remain editable after seeding.
     */
    public const SIZES = [
        '100ml',
        '50ml',
        '5ml tester',
        '10ml tester',
        '3ml crystal',
        '30ml gold laser',
        '50ml gold laser',
        '15ml laser',
        '12ml oil vial',
        '6ml oil vial',
        '5ml red pen',
        '100ml yum yum blue',
        '100ml yum yum flower',
        '30ml zara',
    ];

    public const PRICES = [
        '100ml' => 2000,
        '50ml' => 1250,
        '5ml tester' => 150,
        '10ml tester' => 350,
        '3ml crystal' => 500,
        '30ml gold laser' => 850,
        '50ml gold laser' => 1250,
        '15ml laser' => 500,
        '12ml oil vial' => 550,
        '6ml oil vial' => 300,
        '5ml red pen' => 200,
        '100ml yum yum blue' => 4500,
        '100ml yum yum flower' => 4500,
        '30ml zara' => 850,
    ];

    public function run(): void
    {
        foreach (Product::query()->get() as $product) {
            $this->applyToProduct($product);
        }

        $this->command?->info(
            '✓ Standard product sizes/prices applied. All values remain editable.'
        );
    }

    private function applyToProduct(Product $product): void
    {
        DB::transaction(function () use ($product): void {

            /*
             * Remove old standard variants that are no longer part
             * of the current catalog.
             *
             * We intentionally do NOT convert 60ml crystal into
             * another size. The old 60ml sizes are obsolete.
             */
            ProductVariant::query()
                ->where('product_id', $product->id)
                ->whereIn('size_label', [
                    '60ml crystal',
                    '60ml gold crystal',
                    '60ml',
                    '60ml gold',
                ])
                ->update([
                    'is_active' => false,
                    'updated_at' => now(),
                ]);

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

                app(StandardRecipeService::class)
                    ->ensureForVariant($variant);
            }

            /*
             * Preserve additional custom sizes created by the manager.
             */
            $activeVariants = $product->variants()
                ->where('is_active', true)
                ->orderBy('created_at')
                ->orderBy('id')
                ->get([
                    'size_label',
                    'selling_price_default',
                ]);

            $sizes = [];
            $prices = [];

            foreach ($activeVariants as $variant) {
                $label = trim((string) $variant->size_label);

                if ($label === '') {
                    continue;
                }

                $sizes[] = $label;
                $prices[$label] = (float) (
                    $variant->selling_price_default ?? 0
                );
            }

            $product->sizes = $sizes;
            $product->size_prices = $prices;

            $reference = $activeVariants->firstWhere(
                'size_label',
                '50ml'
            ) ?? $activeVariants->first();

            if ($reference) {
                $product->price = (float) (
                    $reference->selling_price_default
                    ?? $product->price
                    ?? 0
                );
            }

            $product->save();
        });
    }

    /**
     * Extract the numeric volume from the size label.
     *
     * Examples:
     * 100ml             => 100
     * 50ml              => 50
     * 10ml tester       => 10
     * 12ml oil vial     => 12
     * 6ml oil vial      => 6
     */
    private function volume(string $size): ?float
    {
        if (preg_match('/(\d+(?:\.\d+)?)\s*ml/i', $size, $matches)) {
            return (float) $matches[1];
        }

        return null;
    }

    private function shapeForSize(string $size): ?string
    {
        $key = mb_strtolower(trim($size));

        return match (true) {
            str_contains($key, '10ml tester') => 'tester',
            str_contains($key, '5ml tester') => 'tester',
            str_contains($key, 'crystal') => 'crystal',
            str_contains($key, 'red pen') => 'pen',
            str_contains($key, 'gold laser') => 'gold_laser',
            str_contains($key, 'laser') => 'laser',
            str_contains($key, 'zara') => 'zara',
            str_contains($key, 'yum yum blue') => 'yum_yum_blue',
            str_contains($key, 'yum yum flower') => 'yum_yum_flower',
            str_contains($key, 'oil vial') => 'oil_vial',
            default => null,
        };
    }
}
