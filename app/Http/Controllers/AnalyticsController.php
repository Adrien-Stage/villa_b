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

        $annees = $this->anneesDisponibles();
        $year = $this->anneeChoisie($request, $annees);

        [$startDate, $endDate] = $this->intervalle($period, $year);
        $periodLabel = $this->libellePeriode($period, $year);

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
            $mois = $this->extractionMois('paid_at');

            $monthlyHotel = Payment::where('status', 'completed')
                ->whereBetween('paid_at', [$startDate, $endDate])
                ->selectRaw("{$mois} as month, SUM(amount) as total")
                ->groupByRaw($mois)
                ->get()->keyBy('month');
                
            $monthlyRestaurant = RestaurantCustomerOrder::where('payment_status', 'paid')
                ->whereBetween('paid_at', [$startDate, $endDate])
                ->selectRaw("{$mois} as month, SUM(amount_paid) as total")
                ->groupByRaw($mois)
                ->get()->keyBy('month');
                
            $monthlyShop = ShopOrder::where('payment_status', 'paid')
                ->whereBetween('paid_at', [$startDate, $endDate])
                ->selectRaw("{$mois} as month, SUM(total_amount) as total")
                ->groupByRaw($mois)
                ->get()->keyBy('month');

            // Une année révolue se lit jusqu'en décembre ; l'année en cours
            // s'arrête au mois courant, les mois à venir n'ayant rien à dire.
            $dernierMois = $year < Carbon::now()->year ? 12 : Carbon::now()->month;

            for ($i = 1; $i <= $dernierMois; $i++) {
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
            'periodLabel',
            'year',
            'annees',
            'startDate',
            'endDate',
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

    /**
     * Extraction du numéro de mois, dans le dialecte de la connexion.
     *
     * La production tourne sous PostgreSQL, les tests sous SQLite : EXTRACT
     * n'existe pas côté SQLite, où la même lecture passe par strftime.
     */
    private function extractionMois(string $colonne): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "CAST(strftime('%m', {$colonne}) AS INTEGER)"
            : "EXTRACT(MONTH FROM {$colonne})";
    }

    /**
     * Bornes de la période demandée.
     *
     * Une année révolue se lit en entier — c'est tout l'intérêt de pouvoir
     * choisir 2023 depuis 2026. L'année en cours, elle, s'arrête aujourd'hui :
     * la prolonger jusqu'au 31 décembre écraserait les moyennes avec des
     * journées qui n'ont pas encore eu lieu.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function intervalle(string $period, int $year): array
    {
        if ($period === 'year') {
            $debut = Carbon::create($year, 1, 1)->startOfYear();

            return [
                $debut,
                $year < Carbon::now()->year ? $debut->copy()->endOfYear() : Carbon::now()->endOfDay(),
            ];
        }

        $debut = match ($period) {
            'today' => Carbon::today(),
            'week'  => Carbon::now()->startOfWeek(),
            'month' => Carbon::now()->startOfMonth(),
            default => Carbon::now()->startOfMonth(),
        };

        return [$debut, Carbon::now()->endOfDay()];
    }

    /**
     * Millésimes proposés au filtre : de la première écriture encaissée à
     * l'année en cours. Aucune donnée encore : seule l'année en cours.
     *
     * @return array<int,int>  du plus récent au plus ancien
     */
    private function anneesDisponibles(): array
    {
        $premiers = array_filter([
            Payment::where('status', 'completed')->min('paid_at'),
            RestaurantCustomerOrder::where('payment_status', 'paid')->min('paid_at'),
            ShopOrder::where('payment_status', 'paid')->min('paid_at'),
            Booking::min('created_at'),
        ]);

        $depart = $premiers
            ? min(array_map(fn($date) => Carbon::parse($date)->year, $premiers))
            : Carbon::now()->year;

        return range(Carbon::now()->year, min($depart, Carbon::now()->year));
    }

    /**
     * Année demandée, ramenée à une année réellement proposée : une valeur
     * bricolée dans l'URL ne doit pas sortir un rapport vide sans le dire.
     *
     * @param  array<int,int>  $annees
     */
    private function anneeChoisie(Request $request, array $annees): int
    {
        $year = (int) $request->query('year', Carbon::now()->year);

        return in_array($year, $annees, true) ? $year : Carbon::now()->year;
    }

    private function libellePeriode(string $period, int $year): string
    {
        if ($period === 'year') {
            return $year === Carbon::now()->year ? 'cette année' : "l'année {$year}";
        }

        return match ($period) {
            'today' => "aujourd'hui",
            'week'  => 'cette semaine',
            default => 'ce mois',
        };
    }

    public function print(Request $request)
    {
        $period = $request->query('period', 'month');
        $department = $request->query('department', 'all');

        $year = $this->anneeChoisie($request, $this->anneesDisponibles());

        [$startDate, $endDate] = $this->intervalle($period, $year);
        $periodLabel = $this->libellePeriode($period, $year);

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
            'periodLabel',
            'year',
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
