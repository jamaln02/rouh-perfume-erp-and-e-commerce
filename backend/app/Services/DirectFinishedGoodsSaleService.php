<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\FinishedProductInventory;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Carbon\Carbon;

class DirectFinishedGoodsSaleService
{
    public function create(array $payload, ?int $actorId = null): Sale
    {
        return DB::transaction(function () use ($payload, $actorId): Sale {
            $inventory = FinishedProductInventory::query()
                ->where('product_id', $payload['product_id'])
                ->where('size_type', $payload['size_type'])
                ->lockForUpdate()->first();
            if (!$inventory) {
                throw new RuntimeException('No finished-goods inventory record exists for the selected product and size.');
            }

            $quantity = (int) $payload['quantity'];
            $stock = (int) $inventory->current_stock;
            if ($quantity <= 0) throw new RuntimeException('Sale quantity must be positive.');
            if ($stock < $quantity) {
                throw new RuntimeException("Insufficient finished-goods stock. Available: {$stock}, requested: {$quantity}.");
            }

            $unitPrice = round((float) $payload['unit_price'], 2);
            if ($unitPrice <= 0) throw new RuntimeException('Sale unit price must be greater than zero.');
            $saleDate = Carbon::parse((string) ($payload['sale_date'] ?? now()->toDateString()))->toDateString();
            $latestMovementDate = DB::table('finished_goods_sale_movements')
                ->where('finished_product_inventory_id', $inventory->id)
                ->max('created_at');
            if ($latestMovementDate && $saleDate < Carbon::parse($latestMovementDate)->toDateString()) {
                throw new RuntimeException('Backdated finished-goods sales are blocked when later inventory activity already exists.');
            }
            $total = round($unitPrice * $quantity, 2);
            $paid = min($total, max(0.0, round((float) ($payload['paid_amount'] ?? 0), 2)));
            if (($payload['payment_method'] ?? '') === 'credit' && $paid > 0.005) {
                throw new RuntimeException('Credit sales cannot contain an immediate receipt. Register the payment separately.');
            }
            $remaining = round(max(0.0, $total - $paid), 2);
            $paymentStatus = $remaining <= 0.005 ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid');

            $product = $inventory->product()->firstOrFail();
            $costPerUnit = max(0.0, round((float) $inventory->cost_per_unit, 6));
            $cost = round($costPerUnit * $quantity, 2);

            if (!empty($payload['customer_id'])) {
                Customer::query()->findOrFail((int) $payload['customer_id']);
            }

            $sale = Sale::create([
                'product_id' => $product->id,
                'product_name' => $product->name,
                'size_type' => $inventory->size_type,
                'size_ml' => $payload['size_ml'] ?? null,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total_price' => $total,
                'cost' => $cost,
                'sale_source' => (string) $payload['sale_source'],
                'customer_id' => $payload['customer_id'] ?? null,
                'customer_name' => $payload['customer_name'] ?? null,
                'customer_phone' => $payload['customer_phone'] ?? null,
                'sale_date' => $saleDate,
                'payment_method' => $payload['payment_method'],
                'payment_status' => $paymentStatus,
                'paid_amount' => $paid,
                'remaining_amount' => $remaining,
                'notes' => $payload['notes'] ?? null,
                'invoice_number' => $payload['invoice_number'] ?? null,
                'sale_status' => 'active',
            ]);
            if (!$sale->invoice_number) {
                $sale->invoice_number = 'INV-' . now()->format('Y') . '-' . str_pad((string) $sale->id, 6, '0', STR_PAD_LEFT);
                $sale->save();
            }

            $previous = $stock;
            $newStock = $stock - $quantity;
            $inventory->current_stock = $newStock;
            $inventory->save();

            DB::table('finished_goods_sale_movements')->insert([
                'sale_id' => $sale->id,
                'finished_product_inventory_id' => $inventory->id,
                'movement_type' => 'out',
                'quantity' => $quantity,
                'previous_stock' => $previous,
                'new_stock' => $newStock,
                'unit_cost' => $costPerUnit,
                'total_cost' => $cost,
                'created_by' => $actorId,
                'meta' => json_encode(['product_id' => $product->id], JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            app(FinancePostingService::class)->postSale($sale->fresh());
            app(CustomerStatsService::class)->recalculate($sale->customer_id ? (int) $sale->customer_id : null);
            return $sale->fresh();
        });
    }

    public function void(Sale $sale, ?int $actorId = null): void
    {
        DB::transaction(function () use ($sale, $actorId): void {
            $lockedSale = Sale::query()->lockForUpdate()->findOrFail($sale->id);
            if ($lockedSale->order_id) {
                throw new RuntimeException('Made-to-order sales must be cancelled from the source order workflow.');
            }
            if (($lockedSale->sale_status ?? 'active') === 'voided') return;

            $movement = DB::table('finished_goods_sale_movements')
                ->where('sale_id', $lockedSale->id)
                ->where('movement_type', 'out')
                ->lockForUpdate()->first();
            if (!$movement) throw new RuntimeException('The finished-goods issue record for this sale is missing. Refusing to void automatically.');

            $inventoryId = (int) $movement->finished_product_inventory_id;
            $inventory = FinishedProductInventory::query()->lockForUpdate()->find($inventoryId);
            if (!$inventory) throw new RuntimeException('The finished-goods inventory record for this sale is missing.');

            $previous = (int) $inventory->current_stock;
            $qty = (int) $movement->quantity;
            $inventory->current_stock = $previous + $qty;
            $inventory->save();

            $alreadyReversed = DB::table('finished_goods_sale_movements')
                ->where('sale_id', $lockedSale->id)
                ->where('movement_type', 'in')
                ->exists();
            if (!$alreadyReversed) {
                DB::table('finished_goods_sale_movements')->insert([
                    'sale_id' => $lockedSale->id,
                    'finished_product_inventory_id' => $inventory->id,
                    'movement_type' => 'in',
                    'quantity' => $qty,
                    'previous_stock' => $previous,
                    'new_stock' => $previous + $qty,
                    'unit_cost' => (float) $movement->unit_cost,
                    'total_cost' => (float) $movement->total_cost,
                    'created_by' => $actorId,
                    'meta' => json_encode(['reason' => 'direct_sale_void'], JSON_UNESCAPED_UNICODE),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            app(FinancePostingService::class)->voidSale($lockedSale);
            $lockedSale->sale_status = 'voided';
            $lockedSale->payment_status = 'refunded';
            $lockedSale->notes = trim(($lockedSale->notes ?? '') . "\nDirect sale voided.");
            $lockedSale->saveQuietly();
            app(CustomerStatsService::class)->recalculate($lockedSale->customer_id ? (int) $lockedSale->customer_id : null);
        });
    }

}
