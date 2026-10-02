<?php

namespace App\Services;

use App\Models\CashRegisterDisbursement;
use App\Models\CashRegisterSession;
use App\Models\PointOfSale;
use App\Models\User;
use App\Support\CashClosurePolicy;

/**
 * Le circuit d'une caisse, le même partout — réception, boutique, chaque
 * restaurant : ouverture avec un fond, encaissements rattachés à la session,
 * comptage par son titulaire, attente du contrôle, contresignature de la
 * comptabilité qui seule la clôt.
 *
 * Une session par personne : chacun compte ce qu'il a encaissé. Une caisse de
 * point de vente — un tiroir — n'a qu'une session ouverte à la fois : deux
 * personnes sur le même tiroir rendraient chaque comptage indémontrable.
 */
class CashRegisterCircuit
{
    /** Libellés des caisses, pour les écrans et les messages. */
    private const LIBELLES = ['reception' => 'Hébergement', 'shop' => 'Boutique', 'restaurant' => 'Restaurant'];

    /** Écran de comptage de chaque caisse. */
    private const CLOTURES = [
        'reception' => 'bookings.cash_register.close',
        'shop' => 'shop.cash_register.close',
        'restaurant' => 'restaurant.cash_register.close',
    ];

    public static function libelle(CashRegisterSession|string $caisse): string
    {
        if ($caisse instanceof CashRegisterSession) {
            $base = self::LIBELLES[$caisse->module] ?? $caisse->module;
            $pointDeVente = $caisse->pointOfSale?->name;

            return $pointDeVente && $pointDeVente !== $base ? "{$base} — {$pointDeVente}" : $base;
        }

        return self::LIBELLES[$caisse] ?? $caisse;
    }

    public static function routeDeComptage(string $module): string
    {
        return self::CLOTURES[$module] ?? 'dashboard';
    }

    /** Session de cette personne qui n'est pas close : ouverte, en pause ou comptée. */
    public function enCours(User $user, string $module): ?CashRegisterSession
    {
        return CashRegisterSession::where('user_id', $user->id)
            ->where('module', $module)
            ->whereNull('closed_at')
            ->latest('id')
            ->first();
    }

    /** Session ouverte de cette personne : la seule qui encaisse. */
    public function ouverte(User $user, string $module): ?CashRegisterSession
    {
        return CashRegisterSession::where('user_id', $user->id)
            ->where('module', $module)
            ->whereNull('closed_at')
            ->where('status', 'open')
            ->latest('id')
            ->first();
    }

    /** Session ouverte ou en pause sur ce tiroir, quelle que soit la personne. */
    public function tiroirTenu(PointOfSale $pointDeVente, string $module): ?CashRegisterSession
    {
        return CashRegisterSession::where('module', $module)
            ->where('point_of_sale_id', $pointDeVente->id)
            ->whereNull('closed_at')
            ->whereIn('status', ['open', 'paused'])
            ->with('user')
            ->first();
    }

    /**
     * Ouvre une session.
     *
     * @throws \DomainException motif du refus, à montrer tel quel
     */
    public function ouvrir(User $user, string $module, int $fondCentimes, ?PointOfSale $pointDeVente = null): CashRegisterSession
    {
        if ($enCours = $this->enCours($user, $module)) {
            throw new \DomainException($enCours->isPendingReview()
                ? 'Votre dernier comptage attend le contrôle de '.CashClosurePolicy::witnessLabel().' : vous ouvrirez une nouvelle caisse ensuite.'
                : 'Vous avez déjà une caisse '.self::libelle($module).' ouverte.');
        }

        if ($pointDeVente && ($tenu = $this->tiroirTenu($pointDeVente, $module))) {
            throw new \DomainException("La caisse {$pointDeVente->name} est tenue par "
                .($tenu->user?->name ?? 'une autre personne')
                .' : elle doit compter sa caisse avant que vous ouvriez la vôtre.');
        }

        return CashRegisterSession::create([
            'user_id' => $user->id,
            'module' => $module,
            'point_of_sale_id' => $pointDeVente?->id,
            'status' => 'open',
            'opening_amount' => $fondCentimes,
            'opened_at' => now(),
        ]);
    }

    /**
     * Le titulaire compte sa caisse. Le solde théorique est recalculé ici,
     * jamais reçu de la requête : c'est lui qui met l'écart en évidence. La
     * caisse cesse d'encaisser et attend la contresignature de la comptabilité.
     */
    public function compter(CashRegisterSession $session, int $compteCentimes, ?string $notes = null): CashRegisterSession
    {
        $theorique = $session->theoreticalBalance();
        $aContresigner = CashClosurePolicy::requiresWitness($session->module);

        $session->update([
            'status' => $aContresigner ? CashClosurePolicy::STATUS_PENDING_REVIEW : 'closed',
            'closed_at' => $aContresigner ? null : now(),
            'theoretical_closing_amount' => $theorique,
            'actual_closing_amount' => $compteCentimes,
            'discrepancy_amount' => $compteCentimes - $theorique,
            'closing_notes' => $notes,
        ]);

        return $session;
    }

    public function decaisser(CashRegisterSession $session, User $user, int $montantCentimes, string $motif): CashRegisterDisbursement
    {
        return CashRegisterDisbursement::create([
            'cash_register_session_id' => $session->id,
            'user_id' => $user->id,
            'amount' => $montantCentimes,
            'reason' => $motif,
        ]);
    }
}
