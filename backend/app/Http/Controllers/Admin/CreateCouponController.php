<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CreateCouponController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:32'],
            'discount_percent' => ['required', 'integer', 'min:1', 'max:100'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'expires_at' => ['nullable', 'date'],
            'active' => ['nullable', 'boolean'],
            'assigned_to_user_id' => ['nullable', 'string', 'max:64'],
            'assigned_to_phone' => ['nullable', 'string', 'min:6', 'max:20'],
            'per_user_limit' => ['nullable', 'integer', 'min:1'],
        ]);

        $id = DB::table('coupons')->insertGetId([
            'code' => strtoupper(trim($validated['code'])),
            'discount_percent' => (int) $validated['discount_percent'],
            'max_uses' => $validated['max_uses'] ?? null,
            'used_count' => 0,
            'expires_at' => $validated['expires_at'] ?? null,
            'active' => $validated['active'] ?? true,
            'assigned_to_user_id' => $validated['assigned_to_user_id'] ?? null,
            'assigned_to_phone' => isset($validated['assigned_to_phone']) ? trim($validated['assigned_to_phone']) : null,
            'per_user_limit' => max(1, (int) ($validated['per_user_limit'] ?? 1)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'ok' => true,
            'id' => $id,
        ]);
    }
}
