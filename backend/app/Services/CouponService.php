<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class CouponService
{
    public function normalizeCode(?string $code): ?string
    {
        $code = trim((string) $code);
        return $code === '' ? null : mb_strtoupper($code);
    }

    public function preview(?string $code, ?string $userId, ?string $phone, float $subtotal, ?string $orderId = null): array
    {
        $code = $this->normalizeCode($code);
        if (!$code || $subtotal <= 0) {
            return ['valid' => true, 'discount_amount' => 0.0, 'source' => null, 'code' => null];
        }

        $coupon = DB::table('coupons')->where('code', $code)->first();
        if ($coupon) {
            $this->assertCouponUsable($coupon, $userId, $phone, $orderId);
            return [
                'valid' => true,
                'discount_amount' => round($subtotal * ((float) $coupon->discount_percent / 100), 2),
                'discount_percent' => (int) $coupon->discount_percent,
                'source' => 'coupon',
                'code' => $coupon->code,
                'id' => $coupon->id,
            ];
        }

        $quiz = DB::table('quiz_discounts')
            ->select(['id', 'discount_code', 'used', 'phone', 'user_id', 'discount_percent'])
            ->where('discount_code', $code)
            ->first();

        if (!$quiz || $quiz->used) {
            throw new \InvalidArgumentException('Invalid or already-used coupon.');
        }
        $this->assertQuizCouponUsable($quiz, $userId, $phone);

        $percent = (int) ($quiz->discount_percent ?? 10);
        return [
            'valid' => true,
            'discount_amount' => round($subtotal * ($percent / 100), 2),
            'discount_percent' => $percent,
            'source' => 'quiz',
            'code' => $quiz->discount_code,
            'id' => $quiz->id,
        ];
    }

    public function redeemForOrder(string $orderId, ?string $code, ?string $userId, ?string $phone, float $subtotal): array
    {
        $code = $this->normalizeCode($code);
        if (!$code) {
            return ['discount_amount' => 0.0, 'source' => null, 'redemption_id' => null];
        }

        // Serialize coupon changes per order so retries/concurrent requests cannot
        // create two active redemptions for the same order.
        DB::table('orders')->where('id', $orderId)->lockForUpdate()->firstOrFail();

        $existing = DB::table('coupon_redemptions')
            ->where('order_id', $orderId)
            ->whereNull('voided_at')
            ->first();
        if ($existing) {
            return [
                'discount_amount' => round((float) ($existing->discount_amount ?? 0), 2),
                'source' => $existing->source,
                'redemption_id' => $existing->id,
            ];
        }

        $coupon = DB::table('coupons')->where('code', $code)->lockForUpdate()->first();
        if ($coupon) {
            $this->assertCouponUsable($coupon, $userId, $phone, $orderId);
            $discount = round($subtotal * ((float) $coupon->discount_percent / 100), 2);

            $id = DB::table('coupon_redemptions')->insertGetId([
                'source' => 'coupon',
                'coupon_id' => $coupon->id,
                'code' => $coupon->code,
                'user_id' => $userId,
                'phone' => $phone,
                'order_id' => $orderId,
                'discount_percent' => (int) $coupon->discount_percent,
                'discount_amount' => $discount,
                'redeemed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('coupons')->where('id', $coupon->id)->update([
                'used_count' => DB::raw('used_count + 1'),
                'updated_at' => now(),
            ]);

            return ['discount_amount' => $discount, 'source' => 'coupon', 'redemption_id' => $id];
        }

        $quiz = DB::table('quiz_discounts')
            ->select(['id', 'discount_code', 'used', 'phone', 'user_id', 'discount_percent'])
            ->where('discount_code', $code)
            ->lockForUpdate()
            ->first();
        if (!$quiz || $quiz->used) {
            throw new \InvalidArgumentException('Invalid or already-used coupon.');
        }
        $this->assertQuizCouponUsable($quiz, $userId, $phone);

        $percent = (int) ($quiz->discount_percent ?? 10);
        $discount = round($subtotal * ($percent / 100), 2);
        $id = DB::table('coupon_redemptions')->insertGetId([
            'source' => 'quiz',
            'coupon_id' => null,
            'code' => $quiz->discount_code,
            'user_id' => $userId,
            'phone' => $phone ?: $quiz->phone,
            'order_id' => $orderId,
            'discount_percent' => $percent,
            'discount_amount' => $discount,
            'redeemed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('quiz_discounts')->where('id', $quiz->id)->update(['used' => true, 'updated_at' => now()]);

        return ['discount_amount' => $discount, 'source' => 'quiz', 'redemption_id' => $id];
    }

    public function repriceForOrder(string $orderId, ?string $code, float $discountAmount): void
    {
        $code = $this->normalizeCode($code);
        if (!$code) return;

        DB::transaction(function () use ($orderId, $code, $discountAmount): void {
            DB::table('orders')->where('id', $orderId)->lockForUpdate()->firstOrFail();
            $redemption = DB::table('coupon_redemptions')
                ->where('order_id', $orderId)
                ->whereNull('voided_at')
                ->lockForUpdate()
                ->first();
            if (!$redemption) {
                throw new \RuntimeException('The order coupon redemption record is missing.');
            }
            if ($this->normalizeCode((string) $redemption->code) !== $code) {
                throw new \RuntimeException('The order coupon code does not match its redemption record.');
            }
            DB::table('coupon_redemptions')->where('id', $redemption->id)->update([
                'discount_amount' => round(max(0, $discountAmount), 2),
                'updated_at' => now(),
            ]);
        });
    }

    public function reverseForOrder(string $orderId, string $reason = 'Order cancelled'): void
    {
        $redemption = DB::table('coupon_redemptions')
            ->where('order_id', $orderId)
            ->whereNull('voided_at')
            ->lockForUpdate()
            ->first();

        if (!$redemption) return;

        if ($redemption->source === 'coupon' && $redemption->coupon_id) {
            DB::table('coupons')->where('id', $redemption->coupon_id)->lockForUpdate()->update([
                'used_count' => DB::raw('GREATEST(0, used_count - 1)'),
                'updated_at' => now(),
            ]);
        } elseif ($redemption->source === 'quiz') {
            DB::table('quiz_discounts')->where('discount_code', $redemption->code)->lockForUpdate()->update([
                'used' => false,
                'updated_at' => now(),
            ]);
        }

        DB::table('coupon_redemptions')->where('id', $redemption->id)->update([
            'voided_at' => now(),
            'void_reason' => $reason,
            'updated_at' => now(),
        ]);
    }

    private function assertQuizCouponUsable(object $quiz, ?string $userId, ?string $phone): void
    {
        if (!$userId || !$phone || !$quiz->user_id) {
            throw new \InvalidArgumentException('Quiz coupons are available only to the signed-in customer who completed the quiz.');
        }
        $normalize = static function (?string $value): ?string {
            $clean = preg_replace('/[\s\-()]/', '', trim((string) $value));
            return $clean && preg_match('/^\+9639\d{8}$/', $clean) ? $clean : null;
        };
        $quizPhone = $normalize((string) $quiz->phone);
        $submittedPhone = $normalize($phone);
        $userPhone = $normalize((string) (DB::table('users')->where('id', $userId)->value('phone') ?? ''));
        if ((string) $quiz->user_id !== (string) $userId || !$quizPhone || $quizPhone !== $submittedPhone || $quizPhone !== $userPhone) {
            throw new \InvalidArgumentException('This quiz coupon is not assigned to this customer.');
        }
    }

    private function assertCouponUsable(object $coupon, ?string $userId, ?string $phone, ?string $orderId): void
    {
        if (!(bool) $coupon->active) throw new \InvalidArgumentException('Invalid coupon.');
        if ($coupon->expires_at && now()->greaterThan($coupon->expires_at)) throw new \InvalidArgumentException('Coupon has expired.');
        if ($coupon->assigned_to_user_id && $coupon->assigned_to_user_id !== $userId) throw new \InvalidArgumentException('This coupon is not assigned to this customer.');
        if ($coupon->assigned_to_phone && $coupon->assigned_to_phone !== $phone) throw new \InvalidArgumentException('This coupon is not assigned to this phone number.');
        if ($coupon->max_uses !== null && (int) $coupon->used_count >= (int) $coupon->max_uses) throw new \InvalidArgumentException('Coupon usage limit reached.');

        $limit = max(1, (int) $coupon->per_user_limit);
        if ($limit > 0 && ($userId || $phone)) {
            $q = DB::table('coupon_redemptions')->where('coupon_id', $coupon->id)->whereNull('voided_at');
            if ($userId) $q->where('user_id', $userId); else $q->where('phone', $phone);
            if ($orderId) $q->where('order_id', '!=', $orderId);
            if ((int) $q->count() >= $limit) throw new \InvalidArgumentException('Coupon already used for this customer.');
        }
    }
}
