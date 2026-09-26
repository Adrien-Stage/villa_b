<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Enums\RoomStatus;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\HousekeepingAssignment;
use App\Models\RestaurantCustomerOrder;
use App\Models\RestaurantPantryItem;
use App\Models\StockItem;
use App\Models\StockRequisition;
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

        $isManager = $user->hasAnyRole(['manager']) || $user->role === 'admin';
        $isReception = $user->hasAnyRole(['reception']);
        $isHousekeeping = $user->hasAnyRole(['housekeeping_leader', 'housekeeping_staff', 'housekeeping']);
        $isRestaurant = $user->hasAnyRole(['restaurant_chief', 'restaurant_staff']);
        $isFinance = $user->hasAnyRole(['cashier', 'accountant']);
        $isShop = $user->hasAnyRole(['shop_manager', 'shop_cashier']);
        $isEconome = $user->hasAnyRole(['econome']);

        $cards = [];
        $panels = [];

        // ===== HOTEL STATS =====
        $statsHotel = [
            'rooms_total'       => Room::count(),
            'rooms_available'   => Room::where('status', RoomStatus::AVAILABLE)->count(),
            'rooms_occupied'    => Room::where('status', RoomStatus::OCCUPIED)->count(),
            'rooms_cleaning'    => Room::where('status', RoomStatus::CLEANING)->count(),
            'rooms_maintenance' => Room::whereIn('status', [RoomStatus::MAINTENANCE, RoomStatus::OUT_OF_ORDER])->count(),
            'arrivals_today'    => Booking::arrivingToday()->count(),
            'departures_today'  => Booking::departingToday()->count(),
            'in_house'          => Booking::inHouse()->count(),
            'customers_total'   => Customer::count(),
        ];

        $occupancyRate = $statsHotel['rooms_total'] > 0
            ? round(($statsHotel['rooms_occupied'] / $statsHotel['rooms_total']) * 100)
            : 0;

        $panels['rooms_status'] = $statsHotel;
        $panels['occupancy_rate'] = $occupancyRate;

        // Reservations Panels (Arrivals & Departures today)
        if ($isManager || $isReception) {
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
        }

        // Restaurant & Finance metrics
        $restaurantRevenueToday = 0;
        if (Schema::hasTable('restaurant_customer_orders')) {
            $restaurantRevenueToday = (int) RestaurantCustomerOrder::query()
                ->where('payment_status', 'paid')
                ->whereDate('paid_at', Carbon::today())
                ->sum('amount_paid');

            $panels['restaurant_latest_orders'] = RestaurantCustomerOrder::query()
                ->with(['items.menuItem', 'customer'])
                ->latest('id')
                ->take(6)
                ->get();
        }
        $panels['restaurant_revenue_today'] = $restaurantRevenueToday;

        $balanceInHouse = (int) Booking::query()
            ->where('status', BookingStatus::CHECKED_IN)
            ->sum('balance_due');
        $panels['balance_in_house'] = $balanceInHouse;

        // Economat & Shop Stats
        $articles = Schema::hasTable('stock_items') ? StockItem::active()->get() : collect();
        $rupture = $articles->filter->isOutOfStock()->count();
        $sousSeuil = $articles->filter->isBelowThreshold()->count();
        $stockValue = $articles->sum(fn (StockItem $i) => $i->stockValue());
        $stockArticlesCount = $articles->count();

        $panels['sous_seuil'] = $sousSeuil;
        $panels['rupture'] = $rupture;
        $panels['stock_value'] = $stockValue;
        $panels['stock_articles_count'] = $stockArticlesCount;

        $itemsSoldToday = 0;
        if (Schema::hasTable('shop_orders')) {
            $itemsSoldToday = (int) \App\Models\ShopOrder::query()
                ->whereDate('created_at', Carbon::today())
                ->sum('total_items');
        }
        $panels['items_sold_today'] = $itemsSoldToday;

        // Hourly Revenue intervals (00h, 03h, 06h, 09h, 12h, 15h, 18h, 21h)
        $today = Carbon::today();
        $hourlyRevenue = [
            '00h' => 0, '03h' => 0, '06h' => 0, '09h' => 0,
            '12h' => 0, '15h' => 0, '18h' => 0, '21h' => 0,
        ];
        $intervals = ['00h' => 0, '03h' => 3, '06h' => 6, '09h' => 9, '12h' => 12, '15h' => 15, '18h' => 18, '21h' => 21];
        foreach ($intervals as $label => $h) {
            $s = $today->copy()->setHour($h)->startOfHour();
            $e = $today->copy()->setHour($h + 2)->endOfHour();

            $pSum = Schema::hasTable('payments')
                ? (int) (\App\Models\Payment::whereBetween('created_at', [$s, $e])->sum('amount') / 100)
                : 0;
            $rSum = Schema::hasTable('restaurant_customer_orders')
                ? (int) (RestaurantCustomerOrder::where('payment_status', 'paid')->whereBetween('created_at', [$s, $e])->sum('total_amount') / 100)
                : 0;
            $sSum = Schema::hasTable('shop_orders')
                ? (int) (\App\Models\ShopOrder::where('payment_status', 'paid')->whereBetween('created_at', [$s, $e])->sum('total_amount') / 100)
                : 0;

            $hourlyRevenue[$label] = $pSum + $rSum + $sSum;
        }
        $panels['hourly_revenue'] = $hourlyRevenue;
        $panels['max_hourly_revenue'] = max(array_values($hourlyRevenue));
        $panels['peak_hourly_label'] = array_search(max(array_values($hourlyRevenue)), $hourlyRevenue) ?: '12h';
        $panels['peak_hourly_value'] = max(array_values($hourlyRevenue));

        // When user is MANAGER: populate exactly the 6 KPI cards for the top row
        if ($isManager) {
            $cards = [
                [
                    'label' => 'Arrivées',
                    'value' => $statsHotel['arrivals_today'],
                    'subtitle' => "aujourd'hui",
                    'icon' => 'calendar-arrow-down',
                    'icon_bg' => 'bg-emerald-50 text-emerald-600',
                    'link_text' => 'Voir la liste →',
                    'link_color' => 'text-emerald-700 hover:text-emerald-800',
                    'href' => route('bookings.index'),
                ],
                [
                    'label' => 'Départs',
                    'value' => $statsHotel['departures_today'],
                    'subtitle' => "aujourd'hui",
                    'icon' => 'calendar-arrow-up',
                    'icon_bg' => 'bg-rose-50 text-rose-500',
                    'link_text' => 'Voir la liste →',
                    'link_color' => 'text-rose-600 hover:text-rose-700',
                    'href' => route('bookings.index'),
                ],
                [
                    'label' => 'En séjour',
                    'value' => $statsHotel['in_house'],
                    'subtitle' => 'clients in-house',
                    'icon' => 'hotel',
                    'icon_bg' => 'bg-blue-50 text-blue-600',
                    'link_text' => 'Voir les clients →',
                    'link_color' => 'text-blue-600 hover:text-blue-700',
                    'href' => route('bookings.index'),
                ],
                [
                    'label' => 'Occupation',
                    'value' => $occupancyRate . '%',
                    'subtitle' => "{$statsHotel['rooms_occupied']} / {$statsHotel['rooms_total']} chambres",
                    'icon' => 'pie-chart',
                    'icon_bg' => 'bg-amber-50 text-amber-600',
                    'link_text' => 'Voir le détail →',
                    'link_color' => 'text-amber-700 hover:text-amber-800',
                    'href' => route('rooms.index'),
                ],
                [
                    'label' => 'CA Resto',
                    'value' => number_format($restaurantRevenueToday / 100, 0, ',', ' ') . ' FCFA',
                    'subtitle' => "aujourd'hui",
                    'icon' => 'trending-up',
                    'icon_bg' => 'bg-rose-50 text-rose-500',
                    'link_text' => 'Voir les ventes →',
                    'link_color' => 'text-rose-600 hover:text-rose-700',
                    'href' => route('restaurant.billing.index', ['payment_status' => 'paid']),
                ],
                [
                    'label' => 'Solde Hôtel',
                    'value' => number_format($balanceInHouse / 100, 0, ',', ' ') . ' FCFA',
                    'subtitle' => 'clients en séjour',
                    'icon' => 'wallet',
                    'icon_bg' => 'bg-purple-50 text-purple-600',
                    'link_text' => 'Voir le rapport →',
                    'link_color' => 'text-purple-600 hover:text-purple-700',
                    'href' => route('bookings.index'),
                ],
            ];
        } else {
            // Non-manager role cards (preserved for reception, housekeeping, restaurant, finance, shop, econome)
            if ($isReception) {
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
                $cards[] = [
                    'label' => 'Occupation',
                    'value' => $occupancyRate . '%',
                    'subtitle' => "{$statsHotel['rooms_occupied']} / {$statsHotel['rooms_total']} chambres",
                    'icon' => 'pie-chart',
                    'href' => route('rooms.index'),
                ];
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

            if ($isRestaurant) {
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

            if ($isFinance) {
                $cards[] = [
                    'label' => 'CA resto',
                    'value' => number_format($restaurantRevenueToday / 100, 0, ',', ' ') . ' FCFA',
                    'subtitle' => "aujourd'hui",
                    'icon' => 'trending-up',
                    'href' => route('restaurant.billing.index', ['payment_status' => 'paid']),
                ];
                $cards[] = [
                    'label' => 'Solde hotel',
                    'value' => number_format($balanceInHouse / 100, 0, ',', ' ') . ' FCFA',
                    'subtitle' => 'clients en sejour',
                    'icon' => 'wallet',
                    'href' => route('bookings.index'),
                ];
            }

            if ($isShop && Schema::hasTable('shop_orders')) {
                $today = Carbon::today();
                $yesterday = Carbon::yesterday();
                $shopRevenueToday = \App\Models\ShopOrder::query()
                    ->where('payment_status', 'paid')
                    ->whereDate('created_at', $today)
                    ->sum('total_amount');
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
                $cards[] = [
                    'label' => 'Cmd Boutique',
                    'value' => \App\Models\ShopOrder::query()->whereDate('created_at', $today)->count(),
                    'subtitle' => 'Aujourd\'hui',
                    'icon' => 'receipt',
                    'href' => route('shop.orders.index'),
                ];
                $cards[] = [
                    'label' => 'Articles vendus',
                    'value' => $itemsSoldToday,
                    'subtitle' => 'Aujourd\'hui',
                    'icon' => 'package',
                    'href' => route('shop.orders.index'),
                ];
                $panels['shop_top_products'] = \App\Models\ShopOrderItem::selectRaw('shop_product_id, SUM(quantity) as total_quantity')
                    ->whereHas('order', fn($q) => $q->where('payment_status', 'paid'))
                    ->whereMonth('created_at', Carbon::now()->month)
                    ->groupBy('shop_product_id')
                    ->orderByDesc('total_quantity')
                    ->take(3)
                    ->with('product')
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

            if ($isEconome && Schema::hasTable('stock_items')) {
                $cards[] = [
                    'label'    => 'Articles sous seuil',
                    'value'    => $sousSeuil,
                    'subtitle' => $rupture > 0 ? "dont {$rupture} en rupture" : 'aucune rupture',
                    'icon'     => 'package-minus',
                    'href'     => route('economat.items.index'),
                ];
                $cards[] = [
                    'label'    => 'Valeur du stock',
                    'value'    => number_format($stockValue / 100, 0, ',', ' ') . ' FCFA',
                    'subtitle' => $stockArticlesCount . ' article(s) actifs',
                    'icon'     => 'warehouse',
                    'href'     => route('economat.index'),
                ];
                if (Schema::hasTable('stock_requisitions')) {
                    $cards[] = [
                        'label'    => 'Demandes en attente',
                        'value'    => StockRequisition::pending()->count(),
                        'subtitle' => 'à servir',
                        'icon'     => 'inbox',
                        'href'     => route('economat.requisitions.index'),
                    ];
                }
                $panels['economat_alerts'] = StockItem::active()->belowThreshold()
                    ->orderBy('current_stock')
                    ->take(5)
                    ->get();
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
        }

        return view('dashboard', [
            'cards' => $cards,
            'panels' => $panels,
            'isManager' => $isManager,
            'statsHotel' => $statsHotel,
            'occupancyRate' => $occupancyRate,
        ]);
    }
}
