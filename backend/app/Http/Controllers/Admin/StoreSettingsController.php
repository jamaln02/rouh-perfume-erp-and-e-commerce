<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\StorefrontConfigController;
use App\Services\StoreSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreSettingsController extends Controller
{
    public function __construct(private readonly StoreSettingsService $settings) {}

    public function index(): JsonResponse
    {
        $storefront = app(StorefrontConfigController::class)();
        return response()->json([
            'ok' => true,
            'config' => json_decode($storefront->getContent(), true),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'shipping_free_threshold' => ['required', 'numeric', 'min:0'],
            'shipping_city_rates' => ['required', 'array'],
            'shipping_city_rates.*' => ['required', 'numeric', 'min:0'],
            'loyalty_enabled' => ['required', 'boolean'],
            'loyalty_earn_amount' => ['required', 'numeric', 'gt:0'],
            'loyalty_earn_points' => ['required', 'integer', 'min:1'],
            'loyalty_redeem_points' => ['required', 'integer', 'min:1'],
            'loyalty_redeem_discount' => ['required', 'numeric', 'min:0'],
            'quiz_discount_percent' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $this->settings->setMany([
            'shipping_free_threshold' => $validated['shipping_free_threshold'],
            'shipping_city_rates' => $validated['shipping_city_rates'],
            'loyalty_enabled' => $validated['loyalty_enabled'],
            'loyalty_earn_amount' => $validated['loyalty_earn_amount'],
            'loyalty_earn_points' => $validated['loyalty_earn_points'],
            'loyalty_redeem_points' => $validated['loyalty_redeem_points'],
            'loyalty_redeem_discount' => $validated['loyalty_redeem_discount'],
            'quiz_discount_percent' => $validated['quiz_discount_percent'],
        ]);

        return response()->json(['ok' => true]);
    }
}
