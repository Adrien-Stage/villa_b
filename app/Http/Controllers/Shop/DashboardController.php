<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\ShopOrder;
use App\Models\ShopProduct;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $tenantId = auth()->user()->tenant->id;
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();

        // Chiffre d'affaires
        $revenueToday = ShopOrder::query()
            ->where('payment_status', 'paid')
            ->whereDate('created_at', $today)
            ->sum('total_amount');

        $revenueYesterday = ShopOrder::query()
            ->where('payment_status', 'paid')
            ->whereDate('created_at', $yesterday)
            ->sum('total_amount');

        // Commandes passées
        $ordersCountToday = ShopOrder::query()
            ->whereDate('created_at', $today)
            ->count();

        // Articles vendus (Aujourd'hui)
        $itemsSoldToday = ShopOrder::query()
            ->whereDate('created_at', $today)
            ->sum('total_items');
            
        // Top 3 Produits
        $topProducts = \App\Models\ShopOrderItem::selectRaw('shop_product_id, SUM(quantity) as total_quantity')
            ->whereHas('order', function ($query) use ($tenantId) {
                $query->where('payment_status', 'paid');
            })
            ->whereMonth('created_at', Carbon::now()->month)
            ->groupBy('shop_product_id')
            ->orderByDesc('total_quantity')
            ->take(3)
            ->with('product') // Relation 'product' must exist in ShopOrderItem
            ->get();
            
        // Stock Warnings
        $lowStockProducts = ShopProduct::query()
            ->where('stock_quantity', '<=', 5)
            ->orderBy('stock_quantity', 'asc')
            ->take(5)
            ->get();
            
        // Cash Register Status
        $hasActiveSession = \App\Models\CashRegisterSession::query()
            ->where('user_id', auth()->id())
            ->whereNull('closed_at')
            ->exists();

        return view('shop.dashboard.index', compact(
            'revenueToday', 'revenueYesterday', 
            'ordersCountToday', 'itemsSoldToday', 
            'topProducts', 'lowStockProducts', 'hasActiveSession'
        ));
    }
}
