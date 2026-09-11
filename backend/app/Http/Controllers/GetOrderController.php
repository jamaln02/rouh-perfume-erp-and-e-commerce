<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class GetOrderController extends Controller
{
    public function __invoke(string $id): JsonResponse
    {
        $token = (string) request()->query('token', '');
        $query = DB::table('orders')->where('id', $id);
        if ($token === '' || strlen($token) < 32 || strlen($token) > 255 || !preg_match('/^[A-Za-z0-9]+$/', $token)) {
            return response()->json(['order' => null, 'items' => [], 'message' => 'A private tracking token is required.'], 403);
        }
        $order = $query->where('public_tracking_token', (string)$token)->first([
            'id', 'customer_name', 'customer_phone', 'customer_address', 'city',
            'total', 'shipping_cost', 'shipping_fee', 'status', 'order_status', 'payment_status', 'created_at',
        ]);

        if (!$order) {
            return response()->json([
                'order' => null,
                'items' => [],
            ]);
        }

        $items = DB::table('order_items')
            ->select(['id', 'product_name', 'size', 'quantity', 'price'])
            ->where('order_id', $id)
            ->orderBy('id')
            ->get();

        return response()->json([
            'order' => $order,
            'items' => $items,
        ]);
    }
}
