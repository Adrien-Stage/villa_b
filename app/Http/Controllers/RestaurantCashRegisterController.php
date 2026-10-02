<?php

namespace App\Http\Controllers;

use App\Models\CashRegisterSession;
use App\Models\PointOfSale;
use App\Services\CashRegisterCircuit;
use App\Support\CashClosurePolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Caisse du restaurant : une par restaurant, une session par personne.
 *
 * Le caissier — ou, s'il le faut, le responsable de restaurant — ouvre sa
 * session sur la caisse de son restaurant avec un fond, encaisse les notes,
 * compte sa caisse ; la comptabilité contresigne et la clôt. C'est le même
 * circuit qu'à la réception et à la boutique (CashRegisterCircuit).
 */
class RestaurantCashRegisterController extends Controller
{
    private const MODULE = 'restaurant';

    public function __construct(private readonly CashRegisterCircuit $circuit) {}

    public function index(): View
    {
        return view('restaurant.cash_register.index', [
            'enCours' => $this->circuit->enCours(Auth::user(), self::MODULE),
            'sessions' => CashRegisterSession::query()
                ->where('module', self::MODULE)
                ->with(['user', 'pointOfSale', 'witness'])
                ->latest('opened_at')
                ->paginate(15),
        ]);
    }

    public function showOpenForm(): View|RedirectResponse
    {
        if ($enCours = $this->circuit->enCours(Auth::user(), self::MODULE)) {
            return redirect()->route('restaurant.cash_register.index')->with('info', $enCours->isPendingReview()
                ? 'Votre dernier comptage attend le contrôle de '.CashClosurePolicy::witnessLabel().'.'
                : 'Vous avez déjà une caisse ouverte.');
        }

        $caisses = $this->caisses();

        return view('restaurant.cash_register.open', [
            'caisses' => $caisses,
            'tenues' => $caisses->mapWithKeys(fn (PointOfSale $p) => [$p->id => $this->circuit->tiroirTenu($p, self::MODULE)]),
        ]);
    }

    public function open(Request $request): RedirectResponse
    {
        $caisses = $this->caisses();

        $valide = $request->validate([
            'opening_amount' => ['required', 'numeric', 'min:0'],
            'point_of_sale_id' => [$caisses->count() > 1 ? 'required' : 'nullable', 'integer'],
        ], [
            'point_of_sale_id.required' => 'Choisissez la caisse du restaurant où vous encaissez.',
        ]);

        $pointDeVente = $caisses->count() === 1
            ? $caisses->first()
            : $caisses->firstWhere('id', (int) ($valide['point_of_sale_id'] ?? 0));

        if ($caisses->isNotEmpty() && ! $pointDeVente) {
            return back()->withInput()->withErrors(['point_of_sale_id' => "Cette caisse n'existe pas ou n'est plus active."]);
        }

        try {
            $this->circuit->ouvrir(Auth::user(), self::MODULE, (int) round($valide['opening_amount'] * 100), $pointDeVente);
        } catch (\DomainException $refus) {
            return back()->withInput()->withErrors(['cash_register' => $refus->getMessage()]);
        }

        return redirect()->route('restaurant.billing.index')->with('success',
            'Caisse '.($pointDeVente?->name ?? 'du restaurant').' ouverte. Bon service !');
    }

    public function showCloseForm(): View|RedirectResponse
    {
        $session = $this->circuit->ouverte(Auth::user(), self::MODULE);

        if (! $session) {
            return redirect()->route('restaurant.cash_register.index')->with('info', "Vous n'avez pas de caisse ouverte à compter.");
        }

        return view('restaurant.cash_register.close', [
            'session' => $session->load('pointOfSale'),
            'theorique' => $session->theoreticalBalance(),
            'especes' => (int) $session->restaurantOrders()->where('payment_method', 'cash')->where('payment_status', 'paid')->sum('amount_paid'),
            'autresModes' => $session->restaurantOrders()->where('payment_status', 'paid')->where('payment_method', '!=', 'cash')
                ->selectRaw('payment_method, count(*) as nombre, sum(amount_paid) as montant')->groupBy('payment_method')->get(),
            'decaissements' => $session->disbursements()->get(),
        ]);
    }

    public function close(Request $request): RedirectResponse
    {
        $session = $this->circuit->ouverte(Auth::user(), self::MODULE);
        abort_unless($session, 404);

        $valide = $request->validate([
            'actual_closing_amount' => ['required', 'numeric', 'min:0'],
            'closing_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->circuit->compter($session, (int) round($valide['actual_closing_amount'] * 100), $valide['closing_notes'] ?? null);

        return redirect()->route('restaurant.cash_register.index')->with('success',
            'Comptage enregistré. La caisse sera close après contrôle par '.CashClosurePolicy::witnessLabel().'.');
    }

    public function storeDisbursement(Request $request): RedirectResponse
    {
        $session = $this->circuit->ouverte(Auth::user(), self::MODULE);
        abort_unless($session, 404);

        $valide = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $this->circuit->decaisser($session, Auth::user(), (int) round($valide['amount'] * 100), $valide['reason']);

        return back()->with('success', 'Sortie de caisse enregistrée.');
    }

    /** Les caisses des restaurants : un point de vente de restauration chacun. */
    private function caisses()
    {
        return PointOfSale::active()->ofKind(PointOfSale::KIND_RESTAURATION)->orderBy('sort_order')->orderBy('name')->get();
    }
}
