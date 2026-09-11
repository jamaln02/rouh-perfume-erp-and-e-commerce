<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ToggleCouponController extends Controller
{
    public function __invoke(string $id): JsonResponse
    {
        $coupon = DB::table('coupons')->where('id', $id)->first();
        if (!$coupon) {
            return response()->json([
                'ok' => false,
                'message' => 'Coupon not found',
            ], 404);
        }

        $newState = !$coupon->active;
        DB::table('coupons')->where('id', $id)->update([
            'active' => $newState,
            'updated_at' => now(),
        ]);

        return response()->json([
            'ok' => true,
            'active' => $newState,
        ]);
    }
}
