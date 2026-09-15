<?php

namespace App\Http\Controllers\Reception;

use App\Http\Controllers\Controller;
use App\Models\CashRegisterSession;
use App\Models\CashRegisterDisbursement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CashRegisterController extends Controller
{
    public function index()
    {
        $sessions = CashRegisterSession::query()
            ->where('module', 'reception')
            ->with('user')
            ->orderBy('opened_at', 'desc')
            ->paginate(15);
            
        return view('bookings.cash_register.index', compact('sessions'));
    }

    public function showOpenForm()
    {
        $activeSession = CashRegisterSession::where('user_id', auth()->id())
            
            ->where('module', 'reception')
            ->whereNull('closed_at')
            ->first();

        if ($activeSession) {
            return redirect()->route('bookings.index')->with('info', 'Vous avez déjà une caisse de réception ouverte.');
        }

        return view('bookings.cash_register.open');
    }

    public function open(Request $request)
    {
        $request->validate([
            'opening_amount' => 'required|numeric|min:0',
        ]);

        $activeSession = CashRegisterSession::where('user_id', auth()->id())
            
            ->where('module', 'reception')
            ->whereNull('closed_at')
            ->first();

        if ($activeSession) {
            return redirect()->route('bookings.index');
        }

        CashRegisterSession::create([
            'user_id' => auth()->id(),
            'module' => 'reception',
            'opening_amount' => $request->opening_amount * 100, // store in cents
            'opened_at' => now(),
        ]);

        return redirect()->route('bookings.index')->with('success', 'Caisse de réception ouverte avec succès. Bon travail !');
    }

    public function showCloseForm()
    {
        $session = CashRegisterSession::where('user_id', auth()->id())
            
            ->where('module', 'reception')
            ->whereNull('closed_at')
            ->firstOrFail();

        // Le détail nourrit l'écran ; le total, lui, vient de la même méthode
        // que celle utilisée à l'enregistrement de la clôture.
        $cashPaymentsTotal = $session->payments()
            ->where('method', 'cash')
            ->where('status', 'completed')
            ->sum('amount');
        $disbursementsTotal = $session->disbursements()->sum('amount');

        return view('bookings.cash_register.close', [
            'session' => $session,
            'theoretical_amount' => $session->theoreticalBalance(),
            'cash_payments_total' => $cashPaymentsTotal,
            'disbursements_total' => $disbursementsTotal,
            'disbursements' => $session->disbursements
        ]);
    }

    public function close(Request $request)
    {
        $session = CashRegisterSession::where('user_id', auth()->id())
            
            ->where('module', 'reception')
            ->whereNull('closed_at')
            ->firstOrFail();

        $request->validate([
            'actual_closing_amount' => 'required|numeric|min:0',
            'closing_notes' => 'nullable|string',
        ]);

        // Le comptage physique est déclaré par l'agent ; le solde théorique
        // est recalculé ici et jamais accepté depuis la requête — c'est lui
        // qui met l'écart en évidence.
        $actualAmountCents = (int) round($request->actual_closing_amount * 100);
        $theoreticalAmountCents = $session->theoreticalBalance();
        $discrepancy = $actualAmountCents - $theoreticalAmountCents;

        $session->update([
            'closed_at' => now(),
            'theoretical_closing_amount' => $theoreticalAmountCents,
            'actual_closing_amount' => $actualAmountCents,
            'discrepancy_amount' => $discrepancy,
            'closing_notes' => $request->closing_notes,
        ]);

        return redirect()->route('bookings.index')->with('success', 'Caisse de réception fermée avec succès.');
    }

    public function storeDisbursement(Request $request)
    {
        $session = CashRegisterSession::where('user_id', auth()->id())
            
            ->where('module', 'reception')
            ->whereNull('closed_at')
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

    public function resume(Request $request)
    {
        $request->validate([
            'session_id' => 'required|exists:cash_register_sessions,id',
        ]);

        $session = CashRegisterSession::where('user_id', auth()->id())
            ->whereNull('closed_at')
            ->findOrFail($request->session_id);

        $session->update(['status' => 'open']);

        session()->forget('paused_caisse_session');

        $moduleName = $session->module === 'reception' ? 'Hébergement' : 'Boutique';

        if ($request->boolean('redirect_to_close')) {
            $route = $session->module === 'reception' 
                ? 'bookings.cash_register.close' 
                : 'shop.cash_register.close';
            
            return redirect()->route($route)->with('success', "Caisse {$moduleName} réactivée. Veuillez procéder à la clôture.");
        }

        return redirect()->back()->with('success', "Caisse {$moduleName} réactivée avec succès. Vous pouvez continuer votre travail.");
    }
}
