<?php

namespace App\Http\Controllers;

use App\Models\BookingDraft;
use App\Models\Customer;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Contrôleur de gestion des brouillons de réservation.
 *
 * Responsabilités :
 *  - Lister les brouillons actifs de l'agent connecté.
 *  - Créer/mettre à jour un brouillon à chaque étape du wizard.
 *  - Restaurer une session (reprise) avec tous les champs pré-remplis.
 *  - Supprimer ou marquer un brouillon comme abandonné.
 */
class BookingDraftController extends Controller
{
    // ── Liste des brouillons ──────────────────────────────────────────────────

    /**
     * Liste tous les brouillons actifs de l'agent connecté.
     */
    public function index()
    {
        $drafts = BookingDraft::active()
            ->where('created_by', Auth::id())
            ->with(['customer', 'room.roomType'])
            ->latest('last_activity_at')
            ->get();

        return view('bookings.drafts.index', compact('drafts'));
    }

    // ── Sauvegarde automatique (AJAX) ─────────────────────────────────────────

    /**
     * Crée ou met à jour un brouillon depuis le wizard.
     *
     * Appelé en AJAX par le wizard Alpine.js à chaque transition d'étape.
     * Retourne le token du brouillon pour les appels suivants.
     */
    public function save(Request $request)
    {
        $validated = $request->validate([
            'draft_token'    => ['nullable', 'string', 'max:64'],
            'current_step'   => ['required', 'integer', 'min:1', 'max:4'],
            // Étape 1
            'customer_id'    => ['nullable', 'exists:customers,id'],
            'booker_id'      => ['nullable', 'exists:customers,id'],
            // Étape 2
            'check_in'       => ['nullable', 'date'],
            'check_out'      => ['nullable', 'date', 'after:check_in'],
            'check_in_time'  => ['nullable', 'string', 'max:10'],
            'adults'         => ['nullable', 'integer', 'min:1'],
            'children'       => ['nullable', 'integer', 'min:0'],
            'source'         => ['nullable', 'string', 'max:30'],
            // Étape 3
            'room_id'        => ['nullable', 'exists:rooms,id'],
            // Global
            'notes'          => ['nullable', 'string'],
        ]);

        $draft = null;

        // Chercher le brouillon existant via token
        if (!empty($validated['draft_token'])) {
            $draft = BookingDraft::active()
                ->where('token', $validated['draft_token'])
                ->where('created_by', Auth::id())
                ->first();
        }

        $tenantId = Auth::user()->tenant_id;

        $payload = [
            'tenant_id'     => $tenantId,
            'current_step'  => $validated['current_step'],
            'customer_id'   => $validated['customer_id'] ?? null,
            'booker_id'     => $validated['booker_id'] ?? null,
            'check_in'      => $validated['check_in'] ?? null,
            'check_out'     => $validated['check_out'] ?? null,
            'check_in_time' => $validated['check_in_time'] ?? null,
            'adults'        => $validated['adults'] ?? null,
            'children'      => $validated['children'] ?? 0,
            'source'        => $validated['source'] ?? 'direct',
            'room_id'       => $validated['room_id'] ?? null,
            'notes'         => $validated['notes'] ?? null,
        ];

        if ($draft) {
            $draft->update($payload);
        } else {
            $draft = BookingDraft::create(array_merge($payload, [
                'created_by' => Auth::id(),
            ]));
        }

        return response()->json([
            'success'    => true,
            'token'      => $draft->token,
            'draft_id'   => $draft->id,
            'step_label' => $draft->stepLabel(),
            'expires_at' => $draft->expires_at?->toIso8601String(),
        ]);
    }

    // ── Reprise de session ────────────────────────────────────────────────────

    /**
     * Reprend une session de réservation depuis son token.
     *
     * Restaure l'état du wizard à l'étape où l'agent s'est arrêté,
     * avec tous les champs pré-remplis.
     */
    public function resume(string $token)
    {
        $draft = BookingDraft::active()
            ->where('token', $token)
            ->where('created_by', Auth::id())
            ->with(['customer', 'booker', 'room.roomType'])
            ->firstOrFail();

        // Vérifier que la caisse est ouverte
        $activeSession = \App\Models\CashRegisterSession::where('user_id', Auth::id())
            ->where('module', 'reception')
            ->whereNull('closed_at')
            ->first();

        if (!$activeSession) {
            return redirect()->route('bookings.cash_register.open')
                ->with('warning', 'Vous devez ouvrir votre caisse avant de reprendre un brouillon.');
        }

        // Préparer les données nécessaires à chaque étape
        $customers = Customer::orderBy('last_name')->orderBy('first_name')->get();
        $partnerOrganizations = \App\Models\PartnerOrganization::validOn()->orderBy('name')->get();

        return view('bookings.drafts.resume', compact('draft', 'customers', 'partnerOrganizations'));
    }

    // ── Suppression / Abandon ─────────────────────────────────────────────────

    /**
     * Marque un brouillon comme abandonné (soft-delete logique).
     */
    public function destroy(string $token)
    {
        $draft = BookingDraft::where('token', $token)
            ->where('created_by', Auth::id())
            ->firstOrFail();

        $draft->markAbandoned();

        return redirect()->route('bookings.drafts.index')
            ->with('success', 'Brouillon supprimé.');
    }

    // ── Nettoyage (utilisé par scheduled command) ─────────────────────────────

    /**
     * Abandonne tous les brouillons expirés. Appelé par un scheduled command.
     */
    public static function pruneExpired(): int
    {
        return BookingDraft::where('status', 'active')
            ->where('expires_at', '<', now())
            ->update(['status' => 'abandoned']);
    }
}
