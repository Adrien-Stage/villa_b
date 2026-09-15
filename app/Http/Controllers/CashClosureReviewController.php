<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CashRegisterSession;
use App\Support\CashClosurePolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Contrôle contradictoire des comptages de caisse.
 *
 * Un seul écran pour les deux caisses — réception et boutique : le tiers qui
 * contrôle est le même personne, et lui demander de chercher à deux endroits
 * n'aurait fait qu'encourager le contrôle bâclé.
 */
class CashClosureReviewController extends Controller
{
    /** File des caisses comptées, en attente de contresignature. */
    public function index()
    {
        $this->assertHabilite();

        $enAttente = CashRegisterSession::query()
            ->where('status', CashClosurePolicy::STATUS_PENDING_REVIEW)
            ->whereNull('closed_at')
            ->with('user')
            ->orderBy('updated_at')
            ->get();

        return view('accounting.cash_reviews', [
            'sessions'     => $enAttente,
            'witnessLabel' => CashClosurePolicy::witnessLabel(),
        ]);
    }

    /**
     * Contresigne un comptage : la caisse est alors close et l'écart constaté.
     *
     * Le contrôleur ne peut pas modifier les montants. S'il compte autre chose
     * que le déclarant, l'anomalie se règle hors de l'écran — corriger ici
     * effacerait justement ce que le contrôle est censé faire apparaître.
     */
    public function store(Request $request, CashRegisterSession $session)
    {
        $this->assertHabilite($session->user_id);

        if (!$session->isPendingReview()) {
            return back()->withErrors([
                'session' => "Cette caisse n'attend pas de contrôle.",
            ]);
        }

        $request->validate([
            'witness_notes' => 'nullable|string|max:1000',
        ]);

        $session->update([
            'status'        => 'closed',
            'closed_at'     => now(),
            'witness_id'    => Auth::id(),
            'witnessed_at'  => now(),
            'witness_notes' => $request->witness_notes,
        ]);

        AuditLog::record(
            Auth::id(),
            'cash_closure_review',
            sprintf(
                'Comptage de la caisse %s de %s contresigné — théorique %s, compté %s, écart %s FCFA',
                $session->module,
                $session->user?->name ?? 'agent inconnu',
                number_format($session->theoretical_closing_amount / 100, 0, ',', ' '),
                number_format($session->actual_closing_amount / 100, 0, ',', ' '),
                number_format($session->discrepancy_amount / 100, 0, ',', ' ')
            ),
            'caisse',
            [
                'session_id'  => $session->id,
                'module'      => $session->module,
                'counted_by'  => $session->user_id,
                'theoretical' => (int) $session->theoretical_closing_amount,
                'actual'      => (int) $session->actual_closing_amount,
                'discrepancy' => (int) $session->discrepancy_amount,
            ]
        );

        return back()->with('success', 'Comptage contresigné : la caisse est close.');
    }

    /**
     * @param int|null $declarantId Écarte le déclarant de son propre contrôle.
     */
    private function assertHabilite(?int $declarantId = null): void
    {
        $user = Auth::user();

        if (!$user || !CashClosurePolicy::canWitness($user, $declarantId)) {
            abort(403, 'Vous ne pouvez pas contresigner ce comptage de caisse.');
        }
    }
}
