<?php

namespace App\Http\Controllers;

use App\Models\BreakfastEntitlement;
use App\Models\Tenant;
use App\Services\BreakfastPricingService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * RestaurantBreakfastController : Gestion du service petit-déjeuner au restaurant.
 *
 * Affiche la Breakfast List du jour, permet le pointage rapide (inclus vs extras),
 * et oriente le surplus soit en paiement direct au restaurant, soit en Room Charge sur la chambre.
 */
class RestaurantBreakfastController extends Controller
{
    public function __construct(
        private BreakfastPricingService $breakfastService
    ) {}

    /**
     * Affiche la liste des petits-déjeuners du jour avec compteurs temps réel.
     */
    public function index(Request $request): View
    {
        $tenantId = Auth::user()->tenant_id ?? Tenant::first()?->id;
        $date = $request->query('date', now()->toDateString());

        $serviceHours = $this->breakfastService->getServiceHours($tenantId);
        $adultPrice = $this->breakfastService->getAdultPrice($tenantId);
        $ageBrackets = $this->breakfastService->getAgeBrackets($tenantId);

        $stats = $this->breakfastService->getDailyStats($date, $tenantId);
        $entitlements = $this->breakfastService->getDailyBreakfastList($date, $tenantId);

        // Filtre de statut
        $statusFilter = $request->query('status', 'all');
        if ($statusFilter === 'pending') {
            $entitlements = $entitlements->filter(fn ($e) => $e->isAvailable());
        } elseif ($statusFilter === 'consumed') {
            $entitlements = $entitlements->filter(fn ($e) => $e->isConsumed());
        }

        // Filtre de recherche par numéro de chambre ou nom client
        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $searchLower = mb_strtolower($search);
            $entitlements = $entitlements->filter(function ($e) use ($searchLower) {
                $roomMatch = str_contains(mb_strtolower($e->room?->number ?? ''), $searchLower);
                $nameMatch = str_contains(mb_strtolower($e->booking?->customer?->full_name ?? ''), $searchLower);
                return $roomMatch || $nameMatch;
            });
        }

        return view('restaurant.breakfast.index', [
            'date'         => $date,
            'serviceHours' => $serviceHours,
            'adultPrice'   => $adultPrice,
            'ageBrackets'  => $ageBrackets,
            'stats'        => $stats,
            'entitlements' => $entitlements,
            'statusFilter' => $statusFilter,
            'search'       => $search,
        ]);
    }

    /**
     * Valide le pointage des petits-déjeuners servis pour une chambre.
     */
    public function serve(Request $request, BreakfastEntitlement $entitlement): RedirectResponse
    {
        $validated = $request->validate([
            'adults_served'     => ['required', 'integer', 'min:0'],
            'children_served'   => ['required', 'integer', 'min:0'],
            'settlement_method' => ['required', 'in:room_charge,cash,mobile_money,card,other'],
            'notes'             => ['nullable', 'string', 'max:255'],
        ]);

        $adultsServed = (int) $validated['adults_served'];
        $childrenServed = (int) $validated['children_served'];

        if ($adultsServed === 0 && $childrenServed === 0) {
            return back()->withErrors(['serve' => 'Veuillez renseigner au moins un adulte ou un enfant servi.']);
        }

        $result = $this->breakfastService->recordPointage(
            $entitlement,
            $adultsServed,
            $childrenServed,
            $validated['settlement_method'],
            Auth::id(),
            $validated['notes'] ?? null
        );

        $roomNumber = $entitlement->room?->number ?? '';
        $message = "Pointage validé pour la Chambre {$roomNumber} ({$adultsServed} adulte(s), {$childrenServed} enfant(s)).";

        if ($result['extra_amount_fcfa'] > 0) {
            $formattedExtra = number_format($result['extra_amount_fcfa'], 0, ',', ' ');
            if ($result['settlement'] === 'room_charge') {
                $message .= " Surplus de {$formattedExtra} FCFA débité sur la chambre (Folio).";
            } else {
                $message .= " Surplus de {$formattedExtra} FCFA réglé en direct au restaurant.";
            }
        }

        return back()->with('success', $message);
    }
}
