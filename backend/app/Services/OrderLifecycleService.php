<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;

class OrderLifecycleService
{
    public function transition(Order|string $order, string $target, ?string $reason = null): Order
    {
        return DB::transaction(function () use ($order, $target, $reason): Order {
            $id = $order instanceof Order ? (string) $order->id : (string) $order;
            /** @var Order $locked */
            $locked = Order::query()->lockForUpdate()->findOrFail($id);
            $from = $locked->order_status ?: $locked->status ?: 'pending';
            $target = strtolower(trim($target));

            if ($from === $target) return $locked->fresh();
            if ($target === 'cancelled') {
                return $this->cancelLocked($locked, $reason ?: 'Order cancelled');
            }

            $allowed = [
                'pending' => ['accepted', 'confirmed'],
                'accepted' => ['confirmed'],
                'confirmed' => ['shipped'],
                'shipped' => ['delivered'],
                'delivered' => [],
                'cancelled' => [],
            ];
            if (!in_array($target, $allowed[$from] ?? [], true)) {
                throw new \DomainException("Illegal order status transition: {$from} → {$target}.");
            }

            if ($target === 'confirmed' && !$this->hasActiveConsumption($id)) {
                throw new \DomainException('Order cannot be confirmed before preparation is completed.');
            }
            if (in_array($target, ['shipped', 'delivered'], true) && !$this->hasActiveSale($id)) {
                throw new \DomainException('Order cannot advance before its sale invoice exists.');
            }

            $updates = ['status' => $target, 'order_status' => $target, 'updated_at' => now()];
            if ($target === 'accepted') $updates['accepted_at'] = now();
            if ($target === 'delivered') $updates['completed_at'] = now();
            $locked->update($updates);

            $recognitionStatus = strtolower((string) config('rouh.accounting.revenue_recognition_status', 'delivered'));
            if ($target === $recognitionStatus) {
                $sale = Sale::query()->where('order_id', $id)
                    ->where(function ($q) { $q->whereNull('sale_status')->orWhere('sale_status', 'active'); })
                    ->lockForUpdate()->first();
                if (!$sale) {
                    throw new \DomainException('Revenue cannot be recognized because the order sale/invoice does not exist.');
                }
                app(FinancePostingService::class)->recognizeSale($sale, now()->toDateString());
            }

            return $locked->fresh();
        });
    }

    public function cancel(Order|string $order, string $reason): Order
    {
        return DB::transaction(function () use ($order, $reason): Order {
            $id = $order instanceof Order ? (string) $order->id : (string) $order;
            /** @var Order $locked */
            $locked = Order::query()->lockForUpdate()->findOrFail($id);
            return $this->cancelLocked($locked, $reason);
        });
    }

    private function cancelLocked(Order $order, string $reason): Order
    {
        $currentStatus = $order->order_status ?: $order->status ?: 'pending';
        if ($currentStatus === 'cancelled') {
            return $order->fresh();
        }
        if (!in_array($currentStatus, ['pending', 'accepted', 'confirmed'], true)) {
            throw new \DomainException('This order can no longer be cancelled. Use the dedicated refund/return workflow for shipped or delivered orders.');
        }
        if (trim($reason) === '') {
            throw new \DomainException('A cancellation reason is required.');
        }

        $id = (string) $order->id;
        $movementService = app(InventoryMovementService::class);
        $activeConsumptions = DB::table('order_consumptions')
            ->where('order_id', $id)
            ->where('status', '!=', 'cancelled')
            ->lockForUpdate()
            ->pluck('id');
        foreach ($activeConsumptions as $consumptionId) {
            $movementService->reverseCompletedConsumption((string) $consumptionId, $reason);
        }

        $finance = app(FinancePostingService::class);
        $sales = Sale::query()->where('order_id', $id)
            ->where(function ($q) { $q->whereNull('sale_status')->orWhere('sale_status', 'active'); })
            ->lockForUpdate()->get();
        foreach ($sales as $sale) {
            $finance->reclassifySalePaymentsToCustomerDeposits($sale);
            $finance->reverseExistingSalePostings((int) $sale->id);
            $sale->sale_status = 'voided';
            $sale->notes = trim(($sale->notes ?? '') . "\n{$reason}");
            $sale->save();
        }

        foreach ($activeConsumptions as $consumptionId) {
            DB::table('order_cost_snapshots')->where('order_consumption_id', $consumptionId)->update([
                'is_final' => false,
                'updated_at' => now(),
            ]);
        }

        app(CouponService::class)->reverseForOrder($id, $reason);
        app(LoyaltyService::class)->reverseOrder($order, $reason);

        $order->update([
            'status' => 'cancelled',
            'order_status' => 'cancelled',
            'cancelled_at' => now(),
            'consumption_status' => 'not_consumed',
            'prepared_at' => null,
            'completed_at' => null,
            'updated_at' => now(),
            'notes' => trim(($order->notes ?? '') . "\n{$reason}"),
        ]);

        app(CustomerStatsService::class)->recalculate($order->customer_id ? (int) $order->customer_id : null);
        return $order->fresh();
    }

    private function hasActiveConsumption(string $id): bool
    {
        return DB::table('order_consumptions')->where('order_id', $id)->where('status', 'completed')->exists();
    }

    private function hasActiveSale(string $id): bool
    {
        return Sale::query()->where('order_id', $id)
            ->where(function ($q) { $q->whereNull('sale_status')->orWhere('sale_status', 'active'); })
            ->exists();
    }
}
