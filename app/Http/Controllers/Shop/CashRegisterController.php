<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\CashRegisterSession;
use App\Models\CashRegisterDisbursement;
use App\Support\CashClosurePolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CashRegisterController extends Controller
{
    public function index()
    {
        $sessions = CashRegisterSession::query()
            ->where('module', 'shop')
            ->with('user')
            ->orderBy('opened_at', 'desc')
            ->paginate(15);
            
        return view('shop.cash_register.index', compact('sessions'));
    }

    public function showOpenForm()
    {
        $activeSession = CashRegisterSession::where('user_id', auth()->id())
            
            ->where('module', 'shop')
            ->whereNull('closed_at')
            ->first();

        if ($activeSession) {
            return redirect()->route('shop.orders.index')->with('info', 'Vous avez déjà une caisse ouverte.');
        }

        return view('shop.cash_register.open');
    }

    public function open(Request $request)
    {
        $request->validate([
            'opening_amount' => 'required|numeric|min:0',
        ]);

        $activeSession = CashRegisterSession::where('user_id', auth()->id())
            
            ->where('module', 'shop')
            ->whereNull('closed_at')
            ->first();

        if ($activeSession) {
            return redirect()->route('shop.orders.index');
        }

        CashRegisterSession::create([
            'user_id' => auth()->id(),
            'module' => 'shop',
            'opening_amount' => $request->opening_amount * 100, // store in cents
            'opened_at' => now(),
        ]);

        return redirect()->route('shop.orders.index')->with('success', 'Caisse ouverte avec succès. Bon travail !');
    }

    public function showCloseForm()
    {
        $session = CashRegisterSession::where('user_id', auth()->id())
            
            ->where('module', 'shop')
            ->whereNull('closed_at')
            ->where('status', 'open')
            ->firstOrFail();

        // Le détail nourrit l'écran ; le total, lui, vient de la même méthode
        // que celle utilisée à l'enregistrement de la clôture.
        $cashOrdersTotal = $session->shopOrders()
            ->where('payment_method', 'cash')
            ->where('payment_status', 'paid')
            ->sum('total_amount');
        $disbursementsTotal = $session->disbursements()->sum('amount');

        return view('shop.cash_register.close', [
            'session' => $session,
            'theoretical_amount' => $session->theoreticalBalance(),
            'cash_orders_total' => $cashOrdersTotal,
            'disbursements_total' => $disbursementsTotal,
            'disbursements' => $session->disbursements
        ]);
    }

    public function close(Request $request)
    {
        $session = CashRegisterSession::where('user_id', auth()->id())
            
            ->where('module', 'shop')
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
        app(\App\Services\CashRegisterCircuit::class)->compter(
            $session,
            (int) round($request->actual_closing_amount * 100),
            $request->closing_notes
        );
        $aContresigner = CashClosurePolicy::requiresWitness('shop');

        if ($aContresigner) {
            return redirect()->route('shop.orders.index')->with(
                'success',
                'Comptage enregistré. La caisse sera close après contrôle par '
                . CashClosurePolicy::witnessLabel() . '.'
            );
        }

        return redirect()->route('shop.orders.index')->with('success', 'Caisse fermée avec succès.');
    }

    public function storeDisbursement(Request $request)
    {
        $session = CashRegisterSession::where('user_id', auth()->id())
            
            ->where('module', 'shop')
            ->whereNull('closed_at')
            ->where('status', 'open')
            ->firstOrFail();

        $request->validate([
            'amount' => 'required|numeric|min:1',
            'reason' => 'required|string|max:255',
        ]);

        CashRegisterDisbursement::create([
            'cash_register_session_id' => $session->id,
            'user_id' => auth()->id(),
            'amount' => $request->amount * 100, // cents
            'reason' => $request->reason,
        ]);

        return back()->with('success', 'Sortie de caisse (décaissement) enregistrée.');
    }
}
