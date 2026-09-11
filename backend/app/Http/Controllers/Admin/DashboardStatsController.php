<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardStatsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $user = request()->attributes->get('authUser');
        $role = $user ? (new \App\Support\ApiTokenAuth())->role((string)$user->id) : null;
        $since = now()->subDays(30)->startOfDay();

        $productsCount = DB::table('products')->count();
        $categoriesCount = DB::table('categories')->count();
        $ordersCount = DB::table('orders')->count();
        $pendingOrdersCount = DB::table('orders')->where(function ($q) { $q->where('order_status', 'pending')->orWhereNull('order_status')->where('status', 'pending'); })->count();

        $recentOrders = DB::table('orders')
            ->select(['id', 'total', 'status', 'created_at'])
            ->where('created_at', '>=', $since)
            ->where('status', '!=', 'cancelled')
            ->get();

        $totalRevenue = $recentOrders->sum(fn ($o) => (float) $o->total);
        $ordersInPeriod = $recentOrders->count();
        $avgOrder = $ordersInPeriod > 0 ? $totalRevenue / $ordersInPeriod : 0;

        $dayMap = [];
        for ($i = 29; $i >= 0; $i--) {
            $dayKey = now()->subDays($i)->toDateString();
            $dayMap[$dayKey] = 0;
        }
        foreach ($recentOrders as $order) {
            $dayKey = substr((string) $order->created_at, 0, 10);
            if (array_key_exists($dayKey, $dayMap)) {
                $dayMap[$dayKey] += (float) $order->total;
            }
        }
        $chartData = [];
        foreach ($dayMap as $date => $sales) {
            $chartData[] = [
                'date' => substr($date, 5),
                'sales' => (int) round($sales),
            ];
        }

        $items = DB::table('order_items')->select(['product_name', 'quantity', 'price'])->get();
        $topMap = [];
        foreach ($items as $item) {
            $key = (string) $item->product_name;
            if (!isset($topMap[$key])) {
                $topMap[$key] = ['name' => $key, 'qty' => 0, 'revenue' => 0];
            }
            $topMap[$key]['qty'] += (int) $item->quantity;
            $topMap[$key]['revenue'] += ((float) $item->price) * ((int) $item->quantity);
        }
        $topProducts = array_values($topMap);
        usort($topProducts, fn ($a, $b) => $b['qty'] <=> $a['qty']);
        $topProducts = array_slice($topProducts, 0, 5);

        $lowStock = DB::table('products')
            ->select(['id', 'name', 'name_ar', 'stock'])
            ->where('stock', '<=', 5)
            ->orderBy('stock')
            ->limit(8)
            ->get();

        $categoryData = DB::table('products')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->selectRaw("COALESCE(categories.name, 'Uncategorized') as name")
            ->selectRaw('COUNT(products.id) as value')
            ->groupBy('categories.name')
            ->orderByDesc('value')
            ->get();

        $statusData = DB::table('orders')
            ->selectRaw('status as name')
            ->selectRaw('COUNT(*) as value')
            ->groupBy('status')
            ->orderByDesc('value')
            ->get();

        if ($role === 'employee') {
            return response()->json([
                'counts' => ['products'=>$productsCount,'categories'=>$categoriesCount,'orders'=>$ordersCount,'pendingOrders'=>$pendingOrdersCount],
                'revenue' => ['total'=>0,'avg'=>0,'count'=>$ordersInPeriod],
                'chartData' => array_map(fn($row)=>['date'=>$row['date'],'sales'=>0], $chartData),
                'topProducts' => [],
                'lowStock' => $lowStock,
                'categoryData' => $categoryData,
                'orderStatusData' => $statusData,
                'restricted' => ['financial'=>true],
            ]);
        }

return response()->json([
            'counts' => [
                'products' => $productsCount,
                'categories' => $categoriesCount,
                'orders' => $ordersCount,
                'pendingOrders' => $pendingOrdersCount,
            ],
            'revenue' => [
                'total' => $totalRevenue,
                'avg' => $avgOrder,
                'count' => $ordersInPeriod,
            ],
            'chartData' => $chartData,
            'topProducts' => $topProducts,
            'lowStock' => $lowStock,
            'categoryData' => $categoryData,
            'orderStatusData' => $statusData,
        ]);
    }
}
