<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CouponRedemptionsController extends Controller
{
    public function __invoke(string $id): JsonResponse
    {
        $redemptions = DB::table('coupon_redemptions')
            ->where('coupon_id', $id)
            ->orderByDesc('redeemed_at')
            ->get();

        return response()->json([
            'redemptions' => $redemptions,
        ]);
    }
}
