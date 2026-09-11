<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ListReviewsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $rows = DB::table('reviews')
            ->leftJoin('products', 'products.id', '=', 'reviews.product_id')
            ->select([
                'reviews.id',
                'reviews.product_id',
                'reviews.rating',
                'reviews.comment',
                'reviews.approved',
                'reviews.created_at',
                'products.name as product_name',
                'products.name_ar as product_name_ar',
            ])
            ->orderByDesc('reviews.created_at')
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'product_id' => $r->product_id,
                'rating' => (int) $r->rating,
                'comment' => $r->comment,
                'approved' => (bool) $r->approved,
                'created_at' => $r->created_at,
                'product' => [
                    'name' => $r->product_name,
                    'name_ar' => $r->product_name_ar,
                ],
            ]);

        return response()->json(['reviews' => $rows]);
    }
}
