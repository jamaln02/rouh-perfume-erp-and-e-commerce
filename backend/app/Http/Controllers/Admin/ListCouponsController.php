<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ListCouponsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $coupons = DB::table('coupons')
            ->orderByDesc('created_at')
            ->get();

        $quizDiscounts = DB::table('quiz_discounts as q')
            ->leftJoin('users as u', 'u.id', '=', 'q.user_id')
            ->select([
                'q.id', 'q.discount_code as code', 'q.discount_percent', 'q.phone',
                'q.used', 'q.recommended_product', 'q.recommended_product_ar', 'q.created_at', 'q.updated_at',
                DB::raw('COALESCE(q.customer_name, u.name) as customer_name'),
                DB::raw("'quiz' as source"),
            ])
            ->orderByDesc('q.created_at')
            ->get();

        return response()->json([
            'coupons' => $coupons,
            'quiz_discounts' => $quizDiscounts,
        ]);
    }
}
