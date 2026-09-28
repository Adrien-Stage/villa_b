<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\RestaurantStockService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * RestaurantConsumptionController : Rapprochement de la consommation théorique (recettes POS / PDJ),
 * des sorties magasin (Économat), des pertes (gaspillage) et du Food Cost %.
 */
class RestaurantConsumptionController extends Controller
{
    public function __construct(
        private readonly RestaurantStockService $stockService
    ) {}

    public function index(Request $request): View
    {
        $tenantId = Auth::user()?->tenant_id ?? Tenant::first()?->id;

        $startDate = $request->filled('start_date')
            ? Carbon::parse($request->input('start_date'))->startOfDay()
            : now()->startOfMonth();

        $endDate = $request->filled('end_date')
            ? Carbon::parse($request->input('end_date'))->endOfDay()
            : now()->endOfDay();

        $report = $this->stockService->getKitchenConsumptionReport(
            startDate: $startDate,
            endDate: $endDate,
            tenantId: $tenantId,
        );

        return view('restaurant.consumption.index', [
            'report'    => $report,
            'startDate' => $startDate->toDateString(),
            'endDate'   => $endDate->toDateString(),
        ]);
    }
}
