<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Keeps customer commercial statistics derived from active sales.
 * This avoids double-awarding loyalty points when an order is edited,
 * prepared again, or has its payment changed.
 */
class CustomerStatsService
{
    public function recalculate(?int $customerId): void
    {
        if (!$customerId) {
            return;
        }

        $customer = Customer::find($customerId);
        if (!$customer) {
            return;
        }

        $summary = DB::table('sales')
            ->where('customer_id', $customerId)
            ->where(function ($query) {
                $query->whereNull('sale_status')->orWhere('sale_status', 'active');
            })
            ->selectRaw('COUNT(*) as order_count, COALESCE(SUM(total_price_syp), 0) as total_spent')
            ->first();

        $totalOrders = (int) ($summary->order_count ?? 0);
        $totalSpent = round((float) ($summary->total_spent ?? 0), 2);

        $customer->total_orders = $totalOrders;
        $customer->total_spent = $totalSpent;
        $customer->loyalty_points = (int) floor($totalSpent / 1000);
        $lastPurchaseDate = DB::table('sales')
            ->where('customer_id', $customerId)
            ->where(function ($query) {
                $query->whereNull('sale_status')->orWhere('sale_status', 'active');
            })
            ->max('sale_date');

        if (Schema::hasColumn('customers', 'last_purchase_date')) {
            $customer->last_purchase_date = $lastPurchaseDate;
        }
        $customer->save();
    }
}
