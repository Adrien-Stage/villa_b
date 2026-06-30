<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Enums\RoomStatus;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\HousekeepingAssignment;
use App\Models\RestaurantCustomerOrder;
use App\Models\RestaurantPantryItem;
use App\Models\Room;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();

        $isManager = $user->hasAnyRole(['manager']);
        $isReception = $user->hasAnyRole(['reception']);
        $isHousekeeping = $user->hasAnyRole(['housekeeping_leader', 'housekeeping_staff', 'housekeeping']);
        $isRestaurant = $user->hasAnyRole(['restaurant_chief', 'restaurant_staff']);
        $isFinance = $user->hasAnyRole(['cashier', 'accountant']);
        $isShop = $user->hasAnyRole(['shop_manager', 'shop_cashier']);

        $cards = [];
        $panels = [];

        // ===== HOTEL =====
        if ($isManager || $isReception || $isHousekeeping) {
            $statsHotel = [
                'rooms_total'       => Room::count(),
                'rooms_available'   => Room::where('status', RoomStatus::AVAILABLE)->count(),
                'rooms_occupied'    => Room::where('status', RoomStatus::OCCUPIED)->count(),
                'rooms_cleaning'    => Room::where('status', RoomStatus::CLEANING)->count(),
                'rooms_maintenance' => Room::where('status', RoomStatus::MAINTENANCE)->count(),
                'arrivals_today'    => Booking::arrivingToday()->count(),
                'departures_today'  => Booking::departingToday()->count(),
                'in_house'          => Booking::inHouse()->count(),
                'customers_total'   => Customer::count(),
            ];

            if ($isManager || $isReception) {
                $cards[] = [
                    'label' => 'Arrivees',
                    'value' => $statsHotel['arrivals_today'],
                    'subtitle' => "aujourd'hui",
                    'icon' => 'calendar-arrow-down',
                    'href' => route('bookings.index'),
                ];
                $cards[] = [
                    'label' => 'Departs',
                    'value' => $statsHotel['departures_today'],
                    'subtitle' => "aujourd'hui",
                    'icon' => 'calendar-arrow-up',
                    'href' => route('bookings.index'),
                ];
                $cards[] = [
                    'label' => 'En sejour',
                    'value' => $statsHotel['in_house'],
                    'subtitle' => 'clients in-house',
                    'icon' => 'hotel',
                    'href' => route('bookings.index'),
                ];

                $occupancyRate = $statsHotel['rooms_total'] > 0
                    ? round(($statsHotel['rooms_occupied'] / $statsHotel['rooms_total']) * 100)
                    : 0;

                $cards[] = [
                    'label' => 'Occupation',
                    'value' => $occupancyRate . '%',
                    'subtitle' => "{$statsHotel['rooms_occupied']} / {$statsHotel['rooms_total']} chambres",
                    'icon' => 'pie-chart',
                    'href' => route('rooms.index'),
                ];

                $panels['reservations'] = [
                    'arrivalsToday' => Booking::arrivingToday()
                        ->with(['customer', 'room.roomType'])
                        ->orderBy('check_in')
                        ->get(),
                    'departuresToday' => Booking::departingToday()
                        ->with(['customer', 'room.roomType'])
                        ->orderBy('check_out')
                        ->get(),
                ];

                $panels['rooms_status'] = $statsHotel;
            }

            if ($isHousekeeping) {
                $activeAssignmentsCount = 0;
                $completedTodayCount = 0;

                if (Schema::hasTable('housekeeping_assignments')) {
                    $activeAssignmentsCount = HousekeepingAssignment::query()
                        ->whereIn('status', ['pending', 'in_progress', 'blocked'])
                        ->count();

                    $completedTodayCount = HousekeepingAssignment::query()
                        ->where('status', 'completed')
                        ->whereDate('completed_at', today())
                        ->count();
                }

                $cards[] = [
                    'label' => 'A nettoyer',
                    'value' => $statsHotel['rooms_cleaning'],
                    'subtitle' => 'chambres',
                    'icon' => 'sparkles',
                    'href' => route('housekeeping.index'),
                ];
                $cards[] = [
                    'label' => 'Assignments',
                    'value' => $activeAssignmentsCount,
                    'subtitle' => 'a faire / en cours',
                    'icon' => 'clipboard-list',
                    'href' => route('housekeeping.index'),
                ];
                $cards[] = [
                    'label' => 'Terminees',
                    'value' => $completedTodayCount,
                    'subtitle' => "aujourd'hui",
                    'icon' => 'check-circle',
                    'href' => route('housekeeping.index'),
                ];

                $panels['rooms_attention'] = Room::whereIn('status', [
                    RoomStatus::CLEANING,
                    RoomStatus::MAINTENANCE,
                    RoomStatus::OUT_OF_ORDER,
                ])->with('roomType')->get();
            }
        }

        // ===== RESTAURANT =====
        if ($isRestaurant || $isManager) {
            if (Schema::hasTable('restaurant_customer_orders')) {
                $pendingOrders = RestaurantCustomerOrder::query()
                    ->whereIn('status', ['pending', 'confirmed', 'preparing'])
                    ->count();

                $readyOrders = RestaurantCustomerOrder::query()
                    ->where('status', 'ready')
                    ->count();

                $unpaidOrders = RestaurantCustomerOrder::query()
                    ->where('payment_status', 'unpaid')
                    ->count();

                $cards[] = [
                    'label' => 'Cmd en attente',
                    'value' => $pendingOrders,
                    'subtitle' => 'restaurant',
                    'icon' => 'receipt',
                    'href' => route('restaurant.orders.index'),
                ];
                $cards[] = [
                    'label' => 'A servir',
                    'value' => $readyOrders,
                    'subtitle' => 'pretes',
                    'icon' => 'bell',
                    'href' => route('restaurant.orders.index', ['status' => 'ready']),
                ];

                if ($user->hasAnyRole(['manager', 'restaurant_chief', 'cashier'])) {
                    $cards[] = [
                        'label' => 'Impayees',
                        'value' => $unpaidOrders,
                        'subtitle' => 'facturation',
                        'icon' => 'credit-card',
                        'href' => route('restaurant.billing.index', ['payment_status' => 'unpaid']),
                    ];
                }

                $panels['restaurant_latest_orders'] = RestaurantCustomerOrder::query()
                    ->latest('id')
                    ->take(10)
                    ->get();
            }

            if (Schema::hasTable('restaurant_pantry_items')) {
                $lowStock = RestaurantPantryItem::query()
                    ->whereColumn('current_stock', '<=', 'min_stock')
                    ->count();

                $cards[] = [
                    'label' => 'Stocks bas',
                    'value' => $lowStock,
                    'subtitle' => 'garde-manger',
                    'icon' => 'warehouse',
                    'href' => route('restaurant.pantry.index', ['low' => 1]),
                ];
            }
        }

        // ===== FINANCE =====
        if ($isFinance || $isManager) {
            if (Schema::hasTable('restaurant_customer_orders')) {
                $restaurantRevenueToday = RestaurantCustomerOrder::query()
                    ->where('payment_status', 'paid')
                    ->whereDate('paid_at', Carbon::today())
                    ->sum('amount_paid');

                $cards[] = [
                    'label' => 'CA resto',
                    'value' => number_format($restaurantRevenueToday / 100, 0, ',', ' ') . ' FCFA',
                    'subtitle' => "aujourd'hui",
                    'icon' => 'trending-up',
                    'href' => route('restaurant.billing.index', ['payment_status' => 'paid']),
                ];
            }

            $balanceInHouse = Booking::query()
                ->where('status', BookingStatus::CHECKED_IN)
                ->sum('balance_due');

            $cards[] = [
                'label' => 'Solde hotel',
                'value' => number_format($balanceInHouse / 100, 0, ',', ' ') . ' FCFA',
                'subtitle' => 'clients en sejour',
                'icon' => 'wallet',
                'href' => route('bookings.index'),
            ];
        }

        // ===== SHOP =====
        if ($isShop || $isManager) {
            if (Schema::hasTable('shop_orders')) {
                $tenantId = $user->tenant->id ?? 1; // Fallback
                $today = Carbon::today();
                $yesterday = Carbon::yesterday();

                // Revenue Today
                $shopRevenueToday = \App\Models\ShopOrder::query()
                    ->where('payment_status', 'paid')
                    ->whereDate('created_at', $today)
                    ->sum('total_amount');
                    
                // Revenue yesterday
                $shopRevenueYesterday = \App\Models\ShopOrder::query()
                    ->where('payment_status', 'paid')
                    ->whereDate('created_at', $yesterday)
                    ->sum('total_amount');
                    
                $diff = $shopRevenueToday - $shopRevenueYesterday;
                $percent = $shopRevenueYesterday > 0 ? ($diff / $shopRevenueYesterday) * 100 : 0;
                $trendIcon = $diff >= 0 ? '<i data-lucide="arrow-up-right" class="w-3 h-3 inline text-green-500"></i>' : '<i data-lucide="arrow-down-right" class="w-3 h-3 inline text-red-500"></i>';

                $cards[] = [
                    'label' => 'Revenus Boutique',
                    'value' => number_format($shopRevenueToday / 100, 0, ',', ' ') . ' FCFA',
                    'subtitle' => "Evolution: " . number_format($percent, 1) . "%",
                    'subtitle_raw' => $trendIcon . " " . number_format($percent, 1) . "% par rapport à hier",
                    'icon' => 'shopping-bag',
                    'href' => route('shop.orders.index'),
                ];

                // Orders Today
                $shopOrdersToday = \App\Models\ShopOrder::query()
                    ->whereDate('created_at', $today)
                    ->count();

                $cards[] = [
                    'label' => 'Cmd Boutique',
                    'value' => $shopOrdersToday,
                    'subtitle' => 'Aujourd\'hui',
                    'icon' => 'receipt',
                    'href' => route('shop.orders.index'),
                ];

                // Items sold Today
                $itemsSoldToday = \App\Models\ShopOrder::query()
                    ->whereDate('created_at', $today)
                    ->sum('total_items');
                    
                $cards[] = [
                    'label' => 'Articles vendus',
                    'value' => $itemsSoldToday,
                    'subtitle' => 'Aujourd\'hui',
                    'icon' => 'package',
                    'href' => route('shop.orders.index'),
                ];

                // Panels
                $panels['shop_top_products'] = \App\Models\ShopOrderItem::selectRaw('shop_product_id, SUM(quantity) as total_quantity')
                    ->whereHas('order', function ($query) use ($tenantId) {
                        $query->where('payment_status', 'paid');
                    })
                    ->whereMonth('created_at', Carbon::now()->month)
                    ->groupBy('shop_product_id')
                    ->orderByDesc('total_quantity')
                    ->take(3)
                    ->with('product') // Product model relation
                    ->get();
                    
                $panels['shop_low_stock'] = \App\Models\ShopProduct::query()
                    ->where('stock_quantity', '<=', 5)
                    ->orderBy('stock_quantity', 'asc')
                    ->take(5)
                    ->get();
                    
                $panels['shop_active_session'] = \App\Models\CashRegisterSession::query()
                    ->where('user_id', auth()->id())
                    ->whereNull('closed_at')
                    ->exists();
            }
        }

        if (empty($cards)) {
            $cards[] = [
                'label' => 'Bienvenue',
                'value' => $user->name,
                'subtitle' => 'Tableau de bord',
                'icon' => 'sparkles',
                'href' => route('dashboard'),
            ];
        }

        return view('dashboard', [
            'cards' => $cards,
            'panels' => $panels,
        ]);
    }
}

