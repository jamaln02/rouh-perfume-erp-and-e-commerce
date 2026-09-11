<?php

namespace App\Http\Controllers;

use App\Services\StoreSettingsService;
use App\Support\ApiTokenAuth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubmitQuizController extends Controller
{
    public function __construct(
        private readonly ApiTokenAuth $auth,
        private readonly StoreSettingsService $settings,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'min:9', 'max:20'],
            'recommended_product' => ['required', 'string', 'max:200'],
            'recommended_product_ar' => ['required', 'string', 'max:200'],
        ]);

        $user = $this->auth->resolveUser($request);
        if (!$user) {
            return response()->json(['ok' => false, 'message' => 'Please sign in before taking the perfume quiz.'], 401);
        }

        $enteredPhone = $this->normalizePhone((string) $validated['phone']);
        $registeredPhone = $this->normalizePhone((string) ($user->phone ?? ''));
        if (!$enteredPhone || !$registeredPhone || $enteredPhone !== $registeredPhone) {
            return response()->json(['ok' => false, 'message' => 'The phone number must match the phone number registered on your account.'], 403);
        }

        $discountPercent = max(1, min(100, $this->settings->getInt('quiz_discount_percent', 10)));

        $existing = DB::table('quiz_discounts')
            ->select(['discount_code', 'recommended_product', 'recommended_product_ar', 'discount_percent', 'used'])
            ->where('phone', $enteredPhone)
            ->first();

        if ($existing) {
            if ((bool) $existing->used) {
                return response()->json([
                    'ok' => false,
                    'reason' => 'already_used',
                    'message' => 'This quiz discount has already been used.',
                ], 409);
            }

            return response()->json([
                'ok' => true,
                'existing' => true,
                'discount_code' => $existing->discount_code,
                'recommended_product' => $existing->recommended_product,
                'recommended_product_ar' => $existing->recommended_product_ar,
                'discount_percent' => (int) ($existing->discount_percent ?? $discountPercent),
            ]);
        }

        $code = $this->makeCode();
        DB::table('quiz_discounts')->insert([
            'phone' => $enteredPhone,
            'user_id' => (string) $user->id,
            'customer_name' => $user->name,
            'discount_code' => $code,
            'discount_percent' => $discountPercent,
            'recommended_product' => $validated['recommended_product'],
            'recommended_product_ar' => $validated['recommended_product_ar'],
            'used' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'existing' => false,
            'discount_code' => $code,
            'recommended_product' => $validated['recommended_product'],
            'recommended_product_ar' => $validated['recommended_product_ar'],
            'discount_percent' => $discountPercent,
        ]);
    }

    private function normalizePhone(string $value): ?string
    {
        $phone = preg_replace('/[\s\-()]/', '', trim($value));
        return $phone && preg_match('/^\+9639\d{8}$/', $phone) ? $phone : null;
    }

    private function makeCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $suffix = '';
            for ($i = 0; $i < 4; $i++) $suffix .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            $code = 'ROUH' . $suffix;
        } while (DB::table('quiz_discounts')->where('discount_code', $code)->exists() || DB::table('coupons')->where('code', $code)->exists());
        return $code;
    }
}
