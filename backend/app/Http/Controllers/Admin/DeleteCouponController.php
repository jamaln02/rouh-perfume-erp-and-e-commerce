<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DeleteCouponController extends Controller
{
    public function __invoke(string $id): JsonResponse
    {
        $deleted = DB::table('coupons')->where('id', $id)->delete();

        if (!$deleted) {
            return response()->json([
                'ok' => false,
                'message' => 'Coupon not found',
            ], 404);
        }

        return response()->json(['ok' => true]);
    }
}
