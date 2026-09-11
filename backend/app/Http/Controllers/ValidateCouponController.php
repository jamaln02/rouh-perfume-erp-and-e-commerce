<?php

namespace App\Http\Controllers;

use App\Support\ApiTokenAuth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ValidateCouponController extends Controller
{
    public function __construct(private readonly ApiTokenAuth $auth) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:32'],
            'user_id' => ['nullable', 'string', 'max:64'],
            'phone' => ['nullable', 'string', 'min:6', 'max:20'],
        ]);

        $user = $this->auth->resolveUser($request);
        if ($request->bearerToken() && !$user) {
            return response()->json(['valid' => false, 'reason' => 'unauthorized'], 401);
        }
        $userId = $user ? (string) $user->id : null;
        if (!$user && !empty($validated['user_id'])) {
            return response()->json(['valid' => false, 'reason' => 'authentication_required'], 401);
        }
        if ($user && !empty($validated['user_id']) && (string) $validated['user_id'] !== $userId) {
            return response()->json(['valid' => false, 'reason' => 'user_mismatch'], 403);
        }

        $code = strtoupper(trim($validated['code']));
        $phone = $this->normalizePhone((string) ($validated['phone'] ?? ''));

        $coupon = DB::table('coupons')->where('code', $code)->first();
        if ($coupon) {
            if (!(bool) $coupon->active || ($coupon->expires_at && now()->greaterThan($coupon->expires_at))) return response()->json(['valid' => false]);
            if ($coupon->max_uses !== null && (int) $coupon->used_count >= (int) $coupon->max_uses) return response()->json(['valid' => false]);
            if ($coupon->assigned_to_user_id && $coupon->assigned_to_user_id !== $userId) return response()->json(['valid' => false, 'reason' => 'not_assigned']);
            if ($coupon->assigned_to_phone && $this->normalizePhone((string) $coupon->assigned_to_phone) !== $phone) return response()->json(['valid' => false, 'reason' => 'not_assigned']);

            $limit = max(1, (int) $coupon->per_user_limit);
            if ($limit > 0 && ($userId || $phone)) {
                $usageQuery = DB::table('coupon_redemptions')->where('coupon_id', $coupon->id)->whereNull('voided_at');
                if ($userId) $usageQuery->where('user_id', $userId); else $usageQuery->where('phone', $phone);
                if ($usageQuery->count() >= $limit) return response()->json(['valid' => false, 'reason' => 'already_used']);
            }

            return response()->json([
                'valid' => true,
                'id' => $coupon->id,
                'code' => $coupon->code,
                'discount_percent' => (int) $coupon->discount_percent,
                'source' => 'coupon',
            ]);
        }

        $quiz = DB::table('quiz_discounts')
            ->select(['id', 'discount_code', 'used', 'phone', 'user_id', 'discount_percent'])
            ->where('discount_code', $code)
            ->first();
        if (!$quiz || $quiz->used) return response()->json(['valid' => false, 'reason' => $quiz ? 'already_used' : null]);

        // Quiz coupons are personal: an authenticated user must enter the exact
        // phone number stored on the quiz record and on their own account.
        if (!$userId || !$phone || !$quiz->user_id || (string) $quiz->user_id !== $userId || $this->normalizePhone((string) $quiz->phone) !== $phone || $this->normalizePhone((string) ($user->phone ?? '')) !== $phone) {
            return response()->json(['valid' => false, 'reason' => 'not_assigned']);
        }

        return response()->json([
            'valid' => true,
            'id' => $quiz->id,
            'code' => $quiz->discount_code,
            'discount_percent' => (int) ($quiz->discount_percent ?? 10),
            'source' => 'quiz',
        ]);
    }

    private function normalizePhone(string $value): ?string
    {
        $phone = preg_replace('/[\s\-()]/', '', trim($value));
        return $phone && preg_match('/^\+9639\d{8}$/', $phone) ? $phone : null;
    }
}
