<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\RestaurantCustomerOrder;
use App\Models\ShopOrder;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $period = $request->query('period', 'month');

        $startDate = match ($period) {
            'today' => Carbon::today(),
            'week' => Carbon::now()->startOfWeek(),
            'month' => Carbon::now()->startOfMonth(),
            'year' => Carbon::now()->startOfYear(),
            default => Carbon::now()->startOfMonth(),
        };

        $endDate = Carbon::now()->endOfDay();

        // Hotel Revenue (Completed Payments)
        $hotelRevenue = Payment::where('status', 'completed')
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->sum('amount');

        // Restaurant Revenue (Paid orders)
        $restaurantRevenue = RestaurantCustomerOrder::where('payment_status', 'paid')
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->sum('amount_paid');

        // Shop Revenue (Paid orders)
        $shopRevenue = ShopOrder::where('payment_status', 'paid')
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->sum('total_amount');

        $totalRevenue = $hotelRevenue + $restaurantRevenue + $shopRevenue;

        // Bookings count
        $bookingsCount = Booking::whereBetween('created_at', [$startDate, $endDate])->count();

        // Daily revenue data for charts
        $dailyHotel = Payment::where('status', 'completed')
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->selectRaw('DATE(paid_at) as date, SUM(amount) as total')
            ->groupByRaw('DATE(paid_at)')
            ->get()->keyBy('date');

        $dailyRestaurant = RestaurantCustomerOrder::where('payment_status', 'paid')
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->selectRaw('DATE(paid_at) as date, SUM(amount_paid) as total')
            ->groupByRaw('DATE(paid_at)')
            ->get()->keyBy('date');

        $dailyShop = ShopOrder::where('payment_status', 'paid')
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->selectRaw('DATE(paid_at) as date, SUM(total_amount) as total')
            ->groupByRaw('DATE(paid_at)')
            ->get()->keyBy('date');

        $chartLabels = [];
        $chartHotel = [];
        $chartRestaurant = [];
        $chartShop = [];

        $currentDate = $startDate->copy();
        
        // Prevent too many labels if year is selected
        if ($period === 'year') {
            // Group by month
            $monthlyHotel = Payment::where('status', 'completed')
                ->whereBetween('paid_at', [$startDate, $endDate])
                ->selectRaw('EXTRACT(MONTH FROM paid_at) as month, SUM(amount) as total')
                ->groupByRaw('EXTRACT(MONTH FROM paid_at)')
                ->get()->keyBy('month');
                
            $monthlyRestaurant = RestaurantCustomerOrder::where('payment_status', 'paid')
                ->whereBetween('paid_at', [$startDate, $endDate])
                ->selectRaw('EXTRACT(MONTH FROM paid_at) as month, SUM(amount_paid) as total')
                ->groupByRaw('EXTRACT(MONTH FROM paid_at)')
                ->get()->keyBy('month');
                
            $monthlyShop = ShopOrder::where('payment_status', 'paid')
                ->whereBetween('paid_at', [$startDate, $endDate])
                ->selectRaw('EXTRACT(MONTH FROM paid_at) as month, SUM(total_amount) as total')
                ->groupByRaw('EXTRACT(MONTH FROM paid_at)')
                ->get()->keyBy('month');

            for ($i = 1; $i <= Carbon::now()->month; $i++) {
                $chartLabels[] = Carbon::create()->month($i)->locale('fr')->shortMonthName;
                // PostgreSQL EXTRACT returns float, so keys might be "1" or 1.0 depending on the driver. Casting to float handles both.
                $monthKey = (string)$i;
                $chartHotel[] = ($monthlyHotel->has($monthKey) ? $monthlyHotel[$monthKey]->total : ($monthlyHotel->has($i) ? $monthlyHotel[$i]->total : 0)) / 100;
                $chartRestaurant[] = ($monthlyRestaurant->has($monthKey) ? $monthlyRestaurant[$monthKey]->total : ($monthlyRestaurant->has($i) ? $monthlyRestaurant[$i]->total : 0)) / 100;
                $chartShop[] = ($monthlyShop->has($monthKey) ? $monthlyShop[$monthKey]->total : ($monthlyShop->has($i) ? $monthlyShop[$i]->total : 0)) / 100;
            }
        } else {
            // Group by day
            while ($currentDate <= $endDate) {
                $dateStr = $currentDate->format('Y-m-d');
                $chartLabels[] = $currentDate->format('d/m');
                $chartHotel[] = ($dailyHotel->has($dateStr) ? $dailyHotel[$dateStr]->total : 0) / 100;
                $chartRestaurant[] = ($dailyRestaurant->has($dateStr) ? $dailyRestaurant[$dateStr]->total : 0) / 100;
                $chartShop[] = ($dailyShop->has($dateStr) ? $dailyShop[$dateStr]->total : 0) / 100;
                $currentDate->addDay();
            }
        }

        return view('analytics.index', compact(
            'period',
            'hotelRevenue',
            'restaurantRevenue',
            'shopRevenue',
            'totalRevenue',
            'bookingsCount',
            'chartLabels',
            'chartHotel',
            'chartRestaurant',
            'chartShop'
        ));
    }

    public function print(Request $request)
    {
        $period = $request->query('period', 'month');
        $department = $request->query('department', 'all');

        $startDate = match ($period) {
            'today' => Carbon::today(),
            'week' => Carbon::now()->startOfWeek(),
            'month' => Carbon::now()->startOfMonth(),
            'year' => Carbon::now()->startOfYear(),
            default => Carbon::now()->startOfMonth(),
        };

        $endDate = Carbon::now()->endOfDay();

        // Hotel
        $hotelRevenue = Payment::where('status', 'completed')
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->sum('amount');
        $bookingsCount = Booking::whereBetween('created_at', [$startDate, $endDate])->count();

        // Restaurant
        $restaurantRevenue = RestaurantCustomerOrder::where('payment_status', 'paid')
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->sum('amount_paid');
        $restaurantOrdersCount = RestaurantCustomerOrder::where('payment_status', 'paid')
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->count();

        // Shop
        $shopRevenue = ShopOrder::where('payment_status', 'paid')
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->sum('total_amount');
        $shopOrdersCount = ShopOrder::where('payment_status', 'paid')
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->count();

        $totalRevenue = $hotelRevenue + $restaurantRevenue + $shopRevenue;

        return view('analytics.print', compact(
            'period',
            'department',
            'startDate',
            'endDate',
            'hotelRevenue',
            'bookingsCount',
            'restaurantRevenue',
            'restaurantOrdersCount',
            'shopRevenue',
            'shopOrdersCount',
            'totalRevenue'
        ));
    }
}
