<?php

namespace App\Http\Controllers\Reception;

use App\Http\Controllers\Controller;
use App\Models\CashRegisterActivity;
use App\Models\CashRegisterDisbursement;
use App\Models\CashRegisterSession;
use App\Models\RestaurantBanquetPayment;
use App\Services\CashRegisterCircuit;
use App\Support\CashClosurePolicy;
use Illuminate\Http\Request;

class CashRegisterController extends Controller
{
    public function __construct(private readonly CashRegisterCircuit $circuit)
    {
    }

    public function index()
    {
        $sessions = CashRegisterSession::query()
            ->where('module', 'reception')
            ->with([
                'user.roles',
                'handoverOut',
                'activities.user.roles',
                'disbursements.user.roles',
                'receptionSales.user.roles',
                'payments.processedBy.roles',
                'payments.booking',
                'banquetPayments.paidBy.roles',
                'banquetPayments.banquet',
            ])
            ->orderBy('opened_at', 'desc')
            ->paginate(15);

        $cashRegisterContext = $this->circuit->receptionContextePour(auth()->user());
        $tiersParSession = $sessions->getCollection()
            ->mapWithKeys(fn (CashRegisterSession $s) => [$s->id => $this->circuit->resumeInterventionsTiers($s)]);

        return view('bookings.cash_register.index', compact('sessions', 'cashRegisterContext', 'tiersParSession'));
    }

    public function showOpenForm()
    {
        $ownEnCours = $this->circuit->enCours(auth()->user(), 'reception');

        if ($ownEnCours) {
            return redirect()->route('bookings.index')->with('info', 'Vous avez déjà une caisse de réception ouverte.');
        }

        if ($shared = $this->circuit->receptionActivePour(auth()->user())) {
            $holderName = $shared->user?->name ?? 'un collègue';

            return redirect()->route('bookings.index')->with('info', "La caisse de réception est déjà ouverte par {$holderName}. Vous pouvez travailler directement dans cette caisse.");
        }

        if ($reprise = $this->passationAReprendre()) {
            return $reprise;
        }

        return view('bookings.cash_register.open');
    }

    /**
     * Un tiroir remis en passation attend : on le reprend, après avoir lu ce
     * que le quart précédent a fait, plutôt que d'ouvrir une caisse à côté.
     */
    private function passationAReprendre(): ?\Illuminate\Http\RedirectResponse
    {
        $passation = app(\App\Services\PassationService::class)->enAttente()
            ->first(fn ($p) => $p->from_user_id !== auth()->id());

        return $passation === null ? null : redirect()->route('bookings.handovers.take', $passation)
            ->with('info', 'Le tiroir de la réception vous attend : prenez connaissance de la passation de '
                . ($passation->fromUser?->name ?? 'votre collègue') . ' avant d’ouvrir votre caisse.');
    }

    public function open(Request $request)
    {
        $request->validate([
            'opening_amount' => 'required|numeric|min:0',
        ]);

        $ownEnCours = $this->circuit->enCours(auth()->user(), 'reception');

        if ($ownEnCours) {
            return redirect()->route('bookings.index');
        }

        if ($shared = $this->circuit->receptionActivePour(auth()->user())) {
            $holderName = $shared->user?->name ?? 'un collègue';

            return redirect()->route('bookings.index')->with('info', "La caisse de réception est déjà ouverte par {$holderName}. Vous travaillez directement dans sa caisse.");
        }

        if ($reprise = $this->passationAReprendre()) {
            return $reprise;
        }

        CashRegisterSession::create([
            'user_id' => auth()->id(),
            'module' => 'reception',
            'status' => 'open',
            'opening_amount' => (int) round($request->opening_amount * 100), // store in cents
            'opened_at' => now(),
        ]);

        return redirect()->route('bookings.index')->with('success', 'Caisse de réception ouverte avec succès. Bon travail !');
    }

    public function showCloseForm()
    {
        $session = CashRegisterSession::where('user_id', auth()->id())
            ->where('module', 'reception')
            ->whereNull('closed_at')
            ->where('status', 'open')
            ->firstOrFail();

        // Le détail nourrit l'écran ; le total, lui, vient de la même méthode
        // que celle utilisée à l'enregistrement de la clôture.
        $cashPaymentsTotal = $session->payments()
            ->where('method', 'cash')
            ->where('status', 'completed')
            ->sum('amount');
        $banquetCashPaymentsTotal = (int) RestaurantBanquetPayment::where('cash_register_session_id', $session->id)
            ->where('payment_method', 'cash')
            ->sum('amount');
        $disbursementsTotal = $session->disbursements()->sum('amount');
        $tiers = $this->circuit->resumeInterventionsTiers($session);

        return view('bookings.cash_register.close', [
            'session' => $session,
            'theoretical_amount' => $session->theoreticalBalance(),
            'cash_payments_total' => $cashPaymentsTotal,
            'banquet_cash_payments_total' => $banquetCashPaymentsTotal,
            'disbursements_total' => $disbursementsTotal,
            'disbursements' => $session->disbursements()->with('user')->get(),
            'tiers' => $tiers,
        ]);
    }

    public function close(Request $request)
    {
        $session = CashRegisterSession::where('user_id', auth()->id())
            ->where('module', 'reception')
            ->whereNull('closed_at')
            ->where('status', 'open')
            ->firstOrFail();

        $request->validate([
            'actual_closing_amount' => 'required|numeric|min:0',
            'closing_notes' => 'nullable|string',
        ]);

        // Même circuit que toutes les caisses (CashRegisterCircuit) : le
        // solde théorique est recalculé, jamais reçu de la requête, et la
        // caisse attend la contresignature de la comptabilité.
        $this->circuit->compter(
            $session,
            (int) round($request->actual_closing_amount * 100),
            $request->closing_notes
        );
        $aContresigner = CashClosurePolicy::requiresWitness('reception');

        if ($aContresigner) {
            return redirect()->route('bookings.index')->with(
                'success',
                'Comptage enregistré. La caisse sera close après contrôle par '
                . CashClosurePolicy::witnessLabel() . '.'
            );
        }

        return redirect()->route('bookings.index')->with('success', 'Caisse de réception fermée avec succès.');
    }

    public function storeDisbursement(Request $request)
    {
        $session = $this->circuit->receptionActivePour(auth()->user());
        if ($session === null) {
            abort(404);
        }

        $request->validate([
            'amount' => 'required|numeric|min:1',
            'reason' => 'required|string|max:255',
        ]);

        $amountCentimes = (int) round($request->amount * 100);

        $disbursement = CashRegisterDisbursement::create([
            'cash_register_session_id' => $session->id,
            'user_id' => auth()->id(),
            'amount' => $amountCentimes,
            'reason' => $request->reason,
        ]);

        $this->circuit->enregistrerInterventionTiers(
            $session,
            auth()->user(),
            CashRegisterActivity::ACTION_DISBURSEMENT,
            "Sortie de caisse : {$request->reason}",
            $amountCentimes,
            'cash',
            $disbursement,
            ['disbursement_id' => $disbursement->id]
        );

        return back()->with('success', 'Sortie de caisse (décaissement) enregistrée.');
    }

    public function resume(Request $request)
    {
        $request->validate([
            'session_id' => 'required|exists:cash_register_sessions,id',
        ]);

        // Une caisse comptée et en attente de contrôle n'est pas « en pause » :
        // la rouvrir permettrait de reprendre des encaissements après avoir
        // déclaré son comptage.
        $session = CashRegisterSession::where('user_id', auth()->id())
            ->whereNull('closed_at')
            ->where('status', '!=', CashClosurePolicy::STATUS_PENDING_REVIEW)
            ->findOrFail($request->session_id);

        $session->update(['status' => 'open']);

        session()->forget('paused_caisse_session');

        $moduleName = \App\Services\CashRegisterCircuit::libelle($session->module);

        if ($request->boolean('redirect_to_close')) {
            return redirect()->route(\App\Services\CashRegisterCircuit::routeDeComptage($session->module))
                ->with('success', "Caisse {$moduleName} réactivée. Veuillez procéder à la clôture.");
        }

        return redirect()->back()->with('success', "Caisse {$moduleName} réactivée avec succès. Vous pouvez continuer votre travail.");
    }
}
