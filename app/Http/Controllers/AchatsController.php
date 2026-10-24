<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\StockItem;
use App\Models\Supplier;
use App\Services\PermissionResolver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Le tableau de bord des achats : ce que chacun doit faire dans le circuit.
 *
 *   demande (service, économat, alerte de stock)
 *     → approbation (contrôleur de gestion, responsable financier)
 *     → bon de commande tiré de la demande (responsable des achats)
 *     → envoi au fournisseur, la demande part aux archives
 *     → réception par l'économat.
 *
 * Chaque bloc ne paraît qu'à qui détient le droit de l'écran qu'il résume.
 */
class AchatsController extends Controller
{
    private const LIGNES = 8;

    public function index(PermissionResolver $droits): View
    {
        $user = Auth::user();
        $voitDemandes = $droits->allows($user, 'economat.purchase_requests.voir');
        $voitBons = $droits->allows($user, 'economat.orders.voir');
        $voitAlertes = $droits->allows($user, 'economat.control.suggestions.voir');
        $aujourdhui = CarbonImmutable::today();

        $aApprouver = $voitDemandes
            ? PurchaseRequest::query()->pending()->with('requestedBy:id,name')->withCount('lines')
                ->orderByRaw("case when priority = 'urgent' then 0 else 1 end")->oldest()->get()
            : collect();

        $aCommander = $voitDemandes
            ? PurchaseRequest::query()->approved()->with(['requestedBy:id,name', 'reviewedBy:id,name'])->withCount('lines')
                ->orderByRaw("case when priority = 'urgent' then 0 else 1 end")->oldest('reviewed_at')->get()
            : collect();

        // Les bons tirés des demandes et pas encore partis ; une régularisation
        // de réception directe ne s'envoie pas.
        $aEnvoyer = $voitBons
            ? PurchaseOrder::query()->where('status', PurchaseOrder::STATUS_DRAFT)
                ->where(fn ($q) => $q->whereNull('transmission')->orWhere('transmission', '!=', PurchaseOrder::TRANSMISSION_REGULARISATION))
                ->with('supplier:id,name')->withCount('lines')->oldest()->get()
            : collect();

        $attendues = $voitBons
            ? PurchaseOrder::query()->open()->with('supplier:id,name')
                ->orderByRaw('expected_at is null')->orderBy('expected_at')->oldest('sent_at')->get()
            : collect();
        $enRetard = $attendues->filter(fn (PurchaseOrder $o) => $o->expected_at !== null && $o->expected_at->lt($aujourdhui));

        $alertes = $voitAlertes
            ? StockItem::query()->active()->belowThreshold()->with('supplier:id,name')->orderBy('current_stock')->get()
            : collect();

        $engage = $voitBons
            ? (int) PurchaseOrder::query()
                ->whereNotIn('status', [PurchaseOrder::STATUS_DRAFT, PurchaseOrder::STATUS_CANCELLED])
                ->where(fn ($q) => $q->whereNull('transmission')->orWhere('transmission', '!=', PurchaseOrder::TRANSMISSION_REGULARISATION))
                ->whereBetween('sent_at', [$aujourdhui->startOfMonth(), $aujourdhui->endOfDay()])
                ->sum('total_amount')
            : 0;

        return view('achats.index', [
            'voitDemandes'  => $voitDemandes,
            'voitBons'      => $voitBons,
            'voitAlertes'   => $voitAlertes,
            'peutApprouver' => $droits->allows($user, 'economat.purchase_requests.approve'),
            'peutCommander' => $droits->allows($user, 'economat.purchase_requests.convert'),
            'peutEnvoyer'   => $droits->allows($user, 'economat.orders.send') || $droits->allows($user, 'economat.orders.transmit'),
            'peutProposer'  => $droits->allows($user, 'economat.control.suggestions.creer'),
            'aApprouver'    => $aApprouver,
            'aCommander'    => $aCommander,
            'aEnvoyer'      => $aEnvoyer,
            'attendues'     => $attendues,
            'enRetard'      => $enRetard,
            'alertes'       => $alertes,
            'engage'        => $engage,
            'fournisseurs'  => $droits->allows($user, 'economat.suppliers.voir') ? Supplier::query()->active()->count() : null,
            'lignes'        => self::LIGNES,
            'aujourdhui'    => $aujourdhui,
        ]);
    }
}
