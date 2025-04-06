<?php

namespace App\Http\Controllers;

use App\Models\OrderItems;
use App\Models\Orders;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $validOrders = Orders::whereIn('status', ['delivered', 'received'])->get();

        $totalOrdrers = $validOrders->count();
        $totalSales = $validOrders->sum('total');
        $productSold = OrderItems::whereIn('order_id', $validOrders->pluck('id'))
            ->sum('quantity');

        $topProducts = OrderItems::select('product_id', DB::raw('SUM(quantity) as sold'))
            ->whereIn('order_id', $validOrders->pluck('id'))
            ->groupBy('product_id')
            ->orderBy('sold', 'desc')
            ->limit(5)
            ->get();

        $weeklyProductSold = OrderItems::selectRaw('DATE(orders.created_at) as date, order_items.product_id, products.name as product_name, SUM(order_items.quantity) as total')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->whereIn('orders.id', $validOrders->pluck('id'))
            ->where('orders.created_at', '>=', now()->subDays(7))
            ->groupBy('date', 'order_items.product_id', 'products.name')
            ->orderBy('date', 'desc')
            ->orderBy('total', 'desc')
            ->get();

        $currentYear = now()->year;
        $lastYear = $currentYear - 1;

        $monthlySales = Orders::selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, SUM(total) as total')
            ->whereIn('status', ['delivered', 'received'])
            ->whereYear('created_at', '>=', $lastYear)
            ->groupBy('year', 'month')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'asc')
            ->get();

        return response()->json([
            'total_orders' => $totalOrdrers,
            'total_sales' => $totalSales,
            'product_sold' => $productSold,
            'top_products' => $topProducts,
            'weekly_product_sold' => $weeklyProductSold,
            'monthly_sales' => $monthlySales,
        ]);
    }
}
