<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TrackOrdersController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => ['required', 'string', 'max:64'],
            'tracking_token' => ['required', 'string', 'min:32', 'max:255'],
        ]);

        $order = DB::table('orders')
            ->where('id', $validated['order_id'])
            ->where('public_tracking_token', $validated['tracking_token'])
            ->select([
                'id', 'customer_name', 'city', 'status', 'order_status',
                'total', 'created_at', 'updated_at',
            ])
            ->first();

        if (!$order) {
            return response()->json([
                'ok' => false,
                'message' => 'The order ID or tracking token is invalid.',
            ], 404);
        }

        return response()->json(['ok' => true, 'orders' => [$order]]);
    }
}
