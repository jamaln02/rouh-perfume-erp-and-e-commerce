<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('bundles', 'offer_config')) {
            Schema::table('bundles', function (Blueprint $table) {
                $table->text('offer_config')->nullable()->after('free_quantity');
            });
        }

        if (!Schema::hasColumn('order_items', 'offer_selection_id')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->string('offer_selection_id', 100)->nullable()->after('offer_name');
            });
        }

        if (!Schema::hasColumn('order_items', 'offer_slot_id')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->string('offer_slot_id', 100)->nullable()->after('offer_selection_id');
            });
        }

        if (Schema::hasColumn('order_items', 'offer_id')) {
            try {
                Schema::table('order_items', function (Blueprint $table) {
                    $table->index(['offer_id', 'offer_selection_id', 'offer_slot_id']);
                });
            } catch (\Throwable $e) {}
        }

        $this->backfillLegacyOffers();
    }

    public function down(): void
    {
        if (Schema::hasColumn('order_items', 'offer_id')) {
            try { Schema::table('order_items', function (Blueprint $table) { $table->dropIndex(['offer_id', 'offer_selection_id', 'offer_slot_id']); }); } catch (\Throwable $e) {}
        }
        if (Schema::hasColumn('order_items', 'offer_slot_id')) {
            Schema::table('order_items', function (Blueprint $table) { $table->dropColumn('offer_slot_id'); });
        }
        if (Schema::hasColumn('order_items', 'offer_selection_id')) {
            Schema::table('order_items', function (Blueprint $table) { $table->dropColumn('offer_selection_id'); });
        }

        if (Schema::hasColumn('bundles', 'offer_config')) {
            Schema::table('bundles', function (Blueprint $table) {
                $table->dropColumn('offer_config');
            });
        }
    }

    private function backfillLegacyOffers(): void
    {
        if (!Schema::hasTable('bundles') || !Schema::hasColumn('bundles', 'offer_config')) return;

        DB::table('bundles')
            ->whereNull('offer_config')
            ->orderBy('created_at')
            ->chunkById(100, function ($bundles): void {
                foreach ($bundles as $bundle) {
                    $config = null;
                    $allowedSizes = $this->decodeArray($bundle->allowed_sizes ?? null);

                    if ($bundle->offer_type === 'mix_match') {
                        $slots = [];
                        $total = max(1, (int) $bundle->paid_quantity + (int) $bundle->free_quantity);
                        for ($i = 0; $i < $total; $i++) {
                            $slots[] = [
                                'id' => 'slot-' . ($i + 1),
                                'label_ar' => 'القطعة ' . ($i + 1),
                                'label_en' => 'Item ' . ($i + 1),
                                'product_mode' => 'any',
                                'product_ids' => [],
                                'size_mode' => $allowedSizes ? 'selected' : 'any',
                                'sizes' => $allowedSizes,
                                'price_mode' => $i >= (int) $bundle->paid_quantity ? 'free' : 'normal',
                                'value' => 0,
                            ];
                        }
                        $config = [
                            'version' => 2,
                            'pricing_mode' => 'fixed_total',
                            'fixed_total' => (float) $bundle->bundle_price,
                            'slots' => $slots,
                        ];
                    } elseif ($bundle->offer_type === 'specific_product' && $bundle->target_product_id) {
                        $priceMap = $this->decodeObject($bundle->price_by_size ?? null);
                        $config = [
                            'version' => 2,
                            'pricing_mode' => 'slot_rules',
                            'fixed_total' => 0,
                            'slots' => [[
                                'id' => 'slot-1',
                                'label_ar' => 'العطر',
                                'label_en' => 'Perfume',
                                'product_mode' => 'selected',
                                'product_ids' => [(string) $bundle->target_product_id],
                                'size_mode' => $allowedSizes ? 'selected' : 'any',
                                'sizes' => $allowedSizes,
                                'price_mode' => count($priceMap) > 1 ? 'fixed_price_by_size' : 'fixed_price',
                                'value' => (float) $bundle->bundle_price,
                                'values_by_size' => $priceMap,
                            ]],
                        ];
                    } elseif ($bundle->offer_type === 'fixed_bundle') {
                        $slots = [];
                        $items = DB::table('bundle_items')->where('bundle_id', $bundle->id)->orderBy('created_at')->get();
                        foreach ($items as $index => $item) {
                            $slots[] = [
                                'id' => 'slot-' . ($index + 1),
                                'label_ar' => 'القطعة ' . ($index + 1),
                                'label_en' => 'Item ' . ($index + 1),
                                'product_mode' => 'selected',
                                'product_ids' => $item->product_id ? [(string) $item->product_id] : [],
                                'size_mode' => $item->size ? 'selected' : 'any',
                                'sizes' => $item->size ? [(string) $item->size] : [],
                                'price_mode' => 'fixed_price',
                                'value' => (float) $item->price,
                                'values_by_size' => [],
                            ];
                        }
                        $config = [
                            'version' => 2,
                            'pricing_mode' => 'slot_rules',
                            'fixed_total' => 0,
                            'slots' => $slots,
                        ];
                    }

                    if ($config) {
                        DB::table('bundles')->where('id', $bundle->id)->update([
                            'offer_config' => json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }, 'id');
    }

    private function decodeArray($value): array
    {
        if (is_array($value)) return array_values($value);
        if (!is_string($value) || trim($value) === '') return [];
        $decoded = json_decode($value, true);
        return is_array($decoded) ? array_values($decoded) : [];
    }

    private function decodeObject($value): array
    {
        if (is_array($value)) return $value;
        if (!is_string($value) || trim($value) === '') return [];
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
};
