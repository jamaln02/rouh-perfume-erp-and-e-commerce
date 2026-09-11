<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RedeemCouponController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'message' => 'Coupon redemption is part of atomic order creation. Create the order with coupon_code instead.',
        ], 410);
    }
}
