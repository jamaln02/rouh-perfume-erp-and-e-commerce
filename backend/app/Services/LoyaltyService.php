<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\DB;

class LoyaltyService
{
    public function __construct(private readonly StoreSettingsService $settings) {}

    public function enabled(): bool
    {
        return $this->settings->getBool('loyalty_enabled', true);
    }

    public function earnAmount(): float
    {
        return max(1, $this->settings->getFloat('loyalty_earn_amount', 1000));
    }

    public function earnPoints(): int
    {
        return max(1, $this->settings->getInt('loyalty_earn_points', 1));
    }

    public function redeemPointsUnit(): int
    {
        return max(1, $this->settings->getInt('loyalty_redeem_points', 100));
    }

    public function redeemDiscountUnit(): float
    {
        return max(0, $this->settings->getFloat('loyalty_redeem_discount', 500));
    }

    public function calculatePoints(float $orderTotal): int
    {
        if (!$this->enabled()) return 0;
        return max(0, (int) floor($orderTotal / $this->earnAmount()) * $this->earnPoints());
    }

    public function calculatePointsDiscount(int $points): float
    {
        if (!$this->enabled()) throw new \InvalidArgumentException('Loyalty program is disabled.');
        $unit = $this->redeemPointsUnit();
        if ($points <= 0 || $points % $unit !== 0) {
            throw new \InvalidArgumentException('Loyalty points must be redeemed in the configured multiples.');
        }
        return round(($points / $unit) * $this->redeemDiscountUnit(), 2);
    }

    public function getUserPoints(string $userId): int
    {
        return (int) (DB::table('profiles')->where('id', $userId)->value('loyalty_points') ?? 0);
    }

    public function redeemForOrder(Order|string $order, string $userId, int $points): float
    {
        if ($points <= 0) return 0.0;
        $discount = $this->calculatePointsDiscount($points);
        $orderId = $order instanceof Order ? (string) $order->id : (string) $order;
        $existing = DB::table('loyalty_transactions')->where('order_id', $orderId)->where('type', 'redeem')->where('status', 'active')->first();
        if ($existing) return round((float) $existing->discount_amount, 2);

        $profile = DB::table('profiles')->where('id', $userId)->lockForUpdate()->first();
        if (!$profile) throw new \InvalidArgumentException('Loyalty profile not found.');
        if ((int) $profile->loyalty_points < $points) throw new \InvalidArgumentException('Not enough loyalty points.');

        DB::table('profiles')->where('id', $userId)->decrement('loyalty_points', $points);
        DB::table('loyalty_transactions')->insert([
            'user_id' => $userId,
            'customer_id' => null,
            'order_id' => $orderId,
            'type' => 'redeem',
            'points_delta' => -$points,
            'discount_amount' => $discount,
            'status' => 'active',
            'reference' => 'ORDER:' . $orderId,
            'metadata' => json_encode([
                'policy' => sprintf('%d points = %s SYP', $this->redeemPointsUnit(), $this->redeemDiscountUnit()),
            ], JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return $discount;
    }

    public function awardForOrder(Order|string $order): int
    {
        if (!$this->enabled()) return 0;
        $order = $order instanceof Order ? $order->fresh() : Order::findOrFail((string) $order);
        $existing = DB::table('loyalty_transactions')->where('order_id', $order->id)->where('type', 'earn')->orderByDesc('id')->first();
        if ($existing && $existing->status === 'active') return max(0, (int) $existing->points_delta);

        $points = $this->calculatePoints((float) $order->total);
        if ($points <= 0) return 0;

        if ($order->user_id) {
            $userId = (string) $order->user_id;
            DB::table('profiles')->updateOrInsert(
                ['id' => $userId],
                ['full_name' => $order->customer_name, 'phone' => $order->customer_phone, 'created_at' => now(), 'updated_at' => now()]
            );
            DB::table('profiles')->where('id', $userId)->lockForUpdate()->increment('loyalty_points', $points);
            $this->activateEarnTransaction($existing, $order, $userId, null, $points);
        } elseif ($order->customer_id) {
            $customer = DB::table('customers')->where('id', $order->customer_id)->lockForUpdate()->first();
            if ($customer) {
                DB::table('customers')->where('id', $order->customer_id)->increment('loyalty_points', $points);
                $this->activateEarnTransaction($existing, $order, null, (int) $order->customer_id, $points);
            }
        }

        $order->forceFill(['loyalty_awarded_at' => now(), 'loyalty_points_earned' => $points])->save();
        return $points;
    }

    private function activateEarnTransaction(?object $existing, Order $order, ?string $userId, ?int $customerId, int $points): void
    {
        $payload = [
            'user_id' => $userId,
            'customer_id' => $customerId,
            'order_id' => $order->id,
            'type' => 'earn',
            'points_delta' => $points,
            'discount_amount' => 0,
            'status' => 'active',
            'reference' => 'ORDER:' . $order->id,
            'metadata' => json_encode([
                'policy' => sprintf('%s SYP = %d point(s)', $this->earnAmount(), $this->earnPoints()),
                'reissued' => (bool) $existing,
            ], JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ];
        if ($existing) {
            DB::table('loyalty_transactions')->where('id', $existing->id)->update($payload);
            return;
        }
        $payload['created_at'] = now();
        DB::table('loyalty_transactions')->insert($payload);
    }

    public function reverseEarnForReissue(Order|string $order, string $reason = 'Order reissued'): void
    {
        $order = $order instanceof Order ? $order->fresh() : Order::lockForUpdate()->findOrFail((string) $order);
        $earn = DB::table('loyalty_transactions')->where('order_id', (string) $order->id)->where('type', 'earn')->where('status', 'active')->lockForUpdate()->first();
        if (!$earn) return;
        $points = max(0, (int) $earn->points_delta);
        if ($points > 0 && $order->user_id) {
            $profile = DB::table('profiles')->where('id', $order->user_id)->lockForUpdate()->first();
            if (!$profile || (int) $profile->loyalty_points < $points) throw new \RuntimeException('Order loyalty points were already spent; reconcile the loyalty ledger before reissuing this order.');
            DB::table('profiles')->where('id', $order->user_id)->decrement('loyalty_points', $points);
        } elseif ($points > 0 && $order->customer_id) {
            $customer = DB::table('customers')->where('id', $order->customer_id)->lockForUpdate()->first();
            if (!$customer || (int) $customer->loyalty_points < $points) throw new \RuntimeException('Order loyalty points were already spent; reconcile the loyalty ledger before reissuing this order.');
            DB::table('customers')->where('id', $order->customer_id)->decrement('loyalty_points', $points);
        }
        DB::table('loyalty_transactions')->where('id', $earn->id)->update(['status' => 'reversed', 'metadata' => json_encode(['reason' => $reason, 'reissue' => true], JSON_UNESCAPED_UNICODE), 'updated_at' => now()]);
        $order->forceFill(['loyalty_awarded_at' => null, 'loyalty_points_earned' => 0])->save();
    }

    public function reverseOrder(Order|string $order, string $reason): void
    {
        $order = $order instanceof Order ? $order->fresh() : Order::lockForUpdate()->findOrFail((string) $order);
        $orderId = (string) $order->id;
        $redeem = DB::table('loyalty_transactions')->where('order_id', $orderId)->where('type', 'redeem')->where('status', 'active')->lockForUpdate()->first();
        if ($redeem) {
            $points = abs((int) $redeem->points_delta);
            if ($points > 0 && $order->user_id) DB::table('profiles')->where('id', $order->user_id)->lockForUpdate()->increment('loyalty_points', $points);
            DB::table('loyalty_transactions')->where('id', $redeem->id)->update(['status' => 'reversed', 'metadata' => json_encode(['reason' => $reason], JSON_UNESCAPED_UNICODE), 'updated_at' => now()]);
        }
        $earn = DB::table('loyalty_transactions')->where('order_id', $orderId)->where('type', 'earn')->where('status', 'active')->lockForUpdate()->first();
        if ($earn) {
            $points = max(0, (int) $earn->points_delta);
            if ($points > 0) {
                if ($order->user_id) {
                    $profile = DB::table('profiles')->where('id', $order->user_id)->lockForUpdate()->first();
                    if (!$profile || (int) $profile->loyalty_points < $points) throw new \RuntimeException('Order loyalty points were already spent; reconcile the loyalty ledger before cancelling this order.');
                    DB::table('profiles')->where('id', $order->user_id)->decrement('loyalty_points', $points);
                } elseif ($order->customer_id) {
                    $customer = DB::table('customers')->where('id', $order->customer_id)->lockForUpdate()->first();
                    if (!$customer || (int) $customer->loyalty_points < $points) throw new \RuntimeException('Order loyalty points were already spent; reconcile the loyalty ledger before cancelling this order.');
                    DB::table('customers')->where('id', $order->customer_id)->decrement('loyalty_points', $points);
                }
            }
            DB::table('loyalty_transactions')->where('id', $earn->id)->update(['status' => 'reversed', 'metadata' => json_encode(['reason' => $reason], JSON_UNESCAPED_UNICODE), 'updated_at' => now()]);
            $order->forceFill(['loyalty_awarded_at' => null, 'loyalty_points_earned' => 0])->save();
        }
    }

    public function addPoints(string $userId, int $points): void
    {
        if ($points <= 0) return;
        DB::table('profiles')->updateOrInsert(['id' => $userId], ['created_at' => now(), 'updated_at' => now()]);
        DB::table('profiles')->where('id', $userId)->increment('loyalty_points', $points);
    }

    public function deductPoints(string $userId, int $points): bool
    {
        if ($points <= 0) return false;
        return DB::transaction(function () use ($userId, $points): bool {
            $profile = DB::table('profiles')->where('id', $userId)->lockForUpdate()->first();
            if (!$profile || (int) $profile->loyalty_points < $points) return false;
            DB::table('profiles')->where('id', $userId)->decrement('loyalty_points', $points);
            return true;
        });
    }
}
