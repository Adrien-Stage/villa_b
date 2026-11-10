<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\CashRegisterActivity;
use App\Models\CashRegisterDisbursement;
use App\Models\CashRegisterSession;
use App\Models\Payment;
use App\Models\PointOfSale;
use App\Models\ReceptionSale;
use App\Models\RestaurantBanquetPayment;
use App\Models\User;
use App\Notifications\CashRegisterThirdPartyActivity;
use App\Support\CashClosurePolicy;
use App\Support\RoleCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

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

    /**
     * Session de caisse de réception active utilisable par cet utilisateur :
     *  - sa propre session ouverte en priorité ;
     *  - sinon, pour le chef de réception uniquement, la caisse ouverte par
     *    un membre de son équipe afin qu'il puisse intervenir à l'intérieur
     *    sans ouvrir un second tiroir ni se voir bloqué en « caisse fermée ».
     *
     * Les autres rôles (manager, autre réceptionniste) ne récupèrent pas la
     * session d'un tiers : chacun garde sa propre responsabilité de tiroir.
     */
    public function receptionActivePour(?User $user): ?CashRegisterSession
    {
        if ($user === null) {
            return null;
        }

        $propre = $this->ouverte($user, 'reception');
        if ($propre !== null) {
            return $propre->loadMissing('user.roles');
        }

        if (!$user->hasRole('reception_chief')) {
            return null;
        }

        $candidates = CashRegisterSession::query()
            ->where('module', 'reception')
            ->whereNull('closed_at')
            ->where('status', 'open')
            ->where('user_id', '!=', $user->id)
            ->with('user.roles')
            ->orderByDesc('opened_at')
            ->orderByDesc('id')
            ->get();

        return $candidates->first(fn (CashRegisterSession $s) => $s->user?->exerce(['reception']) ?? false)
            ?? $candidates->first();
    }

    /** Seul le titulaire ayant ouvert le tiroir peut le compter, le remettre ou le clore. */
    public function estTitulaire(?CashRegisterSession $session, ?User $user): bool
    {
        return $session !== null
            && $user !== null
            && (int) $session->user_id === (int) $user->id;
    }

    /** Contexte complet de la caisse réception pour alimenter contrôleurs et vues. */
    public function receptionContextePour(?User $user): array
    {
        $session = $this->receptionActivePour($user);
        $estTitulaire = $this->estTitulaire($session, $user);

        return [
            'session' => $session,
            'isOpen' => $session !== null,
            'estTitulaire' => $estTitulaire,
            'titulaire' => $session?->user,
            'titulaireRoleLabel' => $session?->user ? $this->libelleRole($session->user) : null,
            'tiers' => ($session !== null && $estTitulaire)
                ? $this->resumeInterventionsTiers($session)
                : $this->emptyTiersSummary(),
        ];
    }

    public function libelleRole(?User $user): ?string
    {
        if ($user === null) {
            return null;
        }

        $user->loadMissing('roles');
        $firstRole = $user->roles->first();
        if ($firstRole && $firstRole->name) {
            return $firstRole->name;
        }

        $slug = $user->rolesDetenus()[0] ?? null;
        if ($slug === null) {
            return null;
        }

        return RoleCatalog::find($slug)['name'] ?? ucfirst(str_replace('_', ' ', $slug));
    }

    /**
     * Journalise et notifie au titulaire toute opération faite dans sa caisse
     * par un autre collaborateur (en pratique le chef de réception).
     */
    public function enregistrerInterventionTiers(
        CashRegisterSession $session,
        User $actor,
        string $action,
        string $description,
        ?int $amountCentimes = null,
        ?string $paymentMethod = null,
        ?Model $subject = null,
        array $metadata = []
    ): ?CashRegisterActivity {
        if ((int) $session->user_id === (int) $actor->id) {
            return null;
        }

        $activity = CashRegisterActivity::create([
            'tenant_id' => $session->tenant_id,
            'cash_register_session_id' => $session->id,
            'user_id' => $actor->id,
            'action' => $action,
            'description' => Str::limit(trim($description), 250),
            'amount' => $amountCentimes,
            'payment_method' => $paymentMethod,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'metadata' => $metadata !== [] ? $metadata : null,
            'occurred_at' => now(),
        ]);

        $activity->load(['user.roles', 'session.user']);

        $sessionHolder = $session->user ?? User::find($session->user_id);
        $holderName = $sessionHolder?->name ?? "utilisateur #{$session->user_id}";

        AuditLog::record(
            $actor->id,
            'cash_register_third_party_activity',
            "{$actor->name} est intervenu(e) dans la caisse Réception #{$session->id} de {$holderName} : {$activity->description}",
            'bookings',
            [
                'cash_register_session_id' => $session->id,
                'session_holder_id' => $session->user_id,
                'activity_id' => $activity->id,
                'action' => $action,
                'amount' => $amountCentimes,
                'payment_method' => $paymentMethod,
            ]
        );

        if ($sessionHolder !== null) {
            app(Notifier::class)->send($sessionHolder, new CashRegisterThirdPartyActivity($activity));
        }

        return $activity;
    }

    /**
     * Synthèse des interventions d'un tiers sur cette session : opérations
     * journalisées + rapprochement des éventuels flux financiers directs non
     * encore tracés, sans doublon.
     */
    public function resumeInterventionsTiers(?CashRegisterSession $session): array
    {
        if ($session === null) {
            return $this->emptyTiersSummary();
        }

        $session->loadMissing([
            'user',
            'activities.user.roles',
            'disbursements.user.roles',
            'receptionSales.user.roles',
            'payments.processedBy.roles',
            'payments.booking',
            'banquetPayments.paidBy.roles',
            'banquetPayments.banquet',
        ]);

        $holderId = (int) $session->user_id;

        $activities = $session->activities
            ->filter(fn (CashRegisterActivity $a) => (int) $a->user_id !== $holderId);

        $coveredPaymentIds = [];
        $coveredBanquetPaymentIds = [];
        $coveredDisbursementIds = [];
        $coveredSaleIds = [];

        $items = collect();

        foreach ($activities as $activity) {
            $meta = is_array($activity->metadata) ? $activity->metadata : [];

            if ($activity->subject_type === Payment::class && $activity->subject_id) {
                $coveredPaymentIds[(int) $activity->subject_id] = true;
            }
            if (!empty($meta['payment_id'])) {
                $coveredPaymentIds[(int) $meta['payment_id']] = true;
            }
            foreach ((array) ($meta['payment_ids'] ?? []) as $pid) {
                if ($pid) {
                    $coveredPaymentIds[(int) $pid] = true;
                }
            }

            if ($activity->subject_type === CashRegisterDisbursement::class && $activity->subject_id) {
                $coveredDisbursementIds[(int) $activity->subject_id] = true;
            }
            if (!empty($meta['disbursement_id'])) {
                $coveredDisbursementIds[(int) $meta['disbursement_id']] = true;
            }

            if ($activity->subject_type === ReceptionSale::class && $activity->subject_id) {
                $coveredSaleIds[(int) $activity->subject_id] = true;
            }
            if (!empty($meta['reception_sale_id'])) {
                $coveredSaleIds[(int) $meta['reception_sale_id']] = true;
            }

            if ($activity->subject_type === RestaurantBanquetPayment::class && $activity->subject_id) {
                $coveredBanquetPaymentIds[(int) $activity->subject_id] = true;
            }
            if (!empty($meta['banquet_payment_id'])) {
                $coveredBanquetPaymentIds[(int) $meta['banquet_payment_id']] = true;
            }

            $items->push([
                'key' => 'activity-' . $activity->id,
                'occurred_at' => $activity->occurred_at ?? $activity->created_at,
                'user' => $activity->user,
                'actor_id' => (int) $activity->user_id,
                'actor_name' => $activity->user?->name ?? 'Collaborateur',
                'role_label' => $this->libelleRole($activity->user),
                'action' => $activity->action,
                'action_label' => $activity->actionLabel(),
                'description' => $activity->description,
                'amount' => $activity->amount,
                'payment_method' => $activity->payment_method,
                'method_label' => CashRegisterActivity::methodLabel($activity->payment_method),
                'cash_impact' => $activity->cashImpactCentimes(),
                'is_financial' => $activity->isFinancialCashFlow(),
            ]);
        }

        // Rapprochement : si une sortie, vente POS ou paiement a été rattaché
        // directement à la session par un tiers hors activité journalisée.
        foreach ($session->disbursements as $disbursement) {
            if ((int) $disbursement->user_id === $holderId || isset($coveredDisbursementIds[$disbursement->id])) {
                continue;
            }

            $items->push([
                'key' => 'disbursement-' . $disbursement->id,
                'occurred_at' => $disbursement->created_at,
                'user' => $disbursement->user,
                'actor_id' => (int) $disbursement->user_id,
                'actor_name' => $disbursement->user?->name ?? 'Collaborateur',
                'role_label' => $this->libelleRole($disbursement->user),
                'action' => CashRegisterActivity::ACTION_DISBURSEMENT,
                'action_label' => 'Sortie de caisse',
                'description' => $disbursement->reason,
                'amount' => (int) $disbursement->amount,
                'payment_method' => 'cash',
                'method_label' => 'Espèces',
                'cash_impact' => -abs((int) $disbursement->amount),
                'is_financial' => true,
            ]);
        }

        foreach ($session->receptionSales as $sale) {
            if ((int) $sale->user_id === $holderId || isset($coveredSaleIds[$sale->id])) {
                continue;
            }

            if ($sale->payment_id) {
                $coveredPaymentIds[(int) $sale->payment_id] = true;
            }

            $isRoomCharge = $sale->payment_method === 'room_charge';
            $amount = $isRoomCharge ? null : (int) $sale->total_amount;

            $items->push([
                'key' => 'sale-' . $sale->id,
                'occurred_at' => $sale->paid_at ?? $sale->created_at,
                'user' => $sale->user,
                'actor_id' => (int) $sale->user_id,
                'actor_name' => $sale->user?->name ?? 'Collaborateur',
                'role_label' => $this->libelleRole($sale->user),
                'action' => CashRegisterActivity::ACTION_POS_SALE,
                'action_label' => 'Vente POS Réception',
                'description' => "Vente POS {$sale->sale_number} — {$sale->customer_name}",
                'amount' => $amount,
                'payment_method' => $sale->payment_method,
                'method_label' => CashRegisterActivity::methodLabel($sale->payment_method),
                'cash_impact' => (!$isRoomCharge && $sale->payment_method === 'cash') ? (int) $sale->total_amount : 0,
                'is_financial' => !$isRoomCharge && (int) $sale->total_amount > 0,
            ]);
        }

        foreach ($session->payments as $payment) {
            if ($payment->processed_by === null
                || (int) $payment->processed_by === $holderId
                || $payment->status !== 'completed'
                || isset($coveredPaymentIds[$payment->id])) {
                continue;
            }

            $isRefund = (int) $payment->amount < 0;
            $absAmount = abs((int) $payment->amount);
            $actor = $payment->processedBy;

            $items->push([
                'key' => 'payment-' . $payment->id,
                'occurred_at' => $payment->paid_at ?? $payment->created_at,
                'user' => $actor,
                'actor_id' => (int) $payment->processed_by,
                'actor_name' => $actor?->name ?? 'Collaborateur',
                'role_label' => $this->libelleRole($actor),
                'action' => $isRefund
                    ? CashRegisterActivity::ACTION_BOOKING_CANCEL
                    : ($payment->invoice_id ? CashRegisterActivity::ACTION_INVOICE_PAYMENT : CashRegisterActivity::ACTION_PAYMENT),
                'action_label' => $isRefund ? 'Remboursement' : 'Encaissement',
                'description' => $payment->refund_reason
                    ?: ($payment->booking ? "Paiement {$payment->reference} — {$payment->booking->booking_number}" : "Paiement {$payment->reference}"),
                'amount' => $absAmount,
                'payment_method' => $payment->method,
                'method_label' => $payment->methodLabel(),
                'cash_impact' => $payment->method === 'cash' ? ($isRefund ? -$absAmount : $absAmount) : 0,
                'is_financial' => true,
            ]);
        }

        foreach ($session->banquetPayments as $banquetPayment) {
            if ($banquetPayment->paid_by === null
                || (int) $banquetPayment->paid_by === $holderId
                || isset($coveredBanquetPaymentIds[$banquetPayment->id])) {
                continue;
            }

            $actor = $banquetPayment->paidBy;
            $banquet = $banquetPayment->banquet;

            $items->push([
                'key' => 'banquet-payment-' . $banquetPayment->id,
                'occurred_at' => $banquetPayment->paid_at ?? $banquetPayment->created_at,
                'user' => $actor,
                'actor_id' => (int) $banquetPayment->paid_by,
                'actor_name' => $actor?->name ?? 'Collaborateur',
                'role_label' => $this->libelleRole($actor),
                'action' => CashRegisterActivity::ACTION_BANQUET_PAYMENT,
                'action_label' => 'Encaissement banquet',
                'description' => trim(sprintf(
                    'Banquet (%s) %s%s',
                    $banquetPayment->libelleKind(),
                    $banquet?->reference ?? '#' . $banquetPayment->restaurant_banquet_id,
                    $banquet?->title ? ' — ' . $banquet->title : ''
                )),
                'amount' => (int) $banquetPayment->amount,
                'payment_method' => $banquetPayment->payment_method,
                'method_label' => $banquetPayment->methodLabel(),
                'cash_impact' => $banquetPayment->payment_method === 'cash' ? (int) $banquetPayment->amount : 0,
                'is_financial' => true,
            ]);
        }

        $items = $items
            ->sortByDesc(fn (array $item) => $item['occurred_at']?->timestamp ?? 0)
            ->values();

        if ($items->isEmpty()) {
            return $this->emptyTiersSummary();
        }

        $cashIn = 0;
        $cashOut = 0;
        $nonCash = 0;

        foreach ($items as $item) {
            if ($item['cash_impact'] > 0) {
                $cashIn += $item['cash_impact'];
            } elseif ($item['cash_impact'] < 0) {
                $cashOut += abs($item['cash_impact']);
            } elseif ($item['is_financial'] && $item['amount']) {
                $nonCash += abs((int) $item['amount']);
            }
        }

        $actors = $items
            ->groupBy('actor_id')
            ->map(function (Collection $group): array {
                $first = $group->first();
                $actorCashIn = 0;
                $actorCashOut = 0;
                $actorNonCash = 0;

                foreach ($group as $row) {
                    if ($row['cash_impact'] > 0) {
                        $actorCashIn += $row['cash_impact'];
                    } elseif ($row['cash_impact'] < 0) {
                        $actorCashOut += abs($row['cash_impact']);
                    } elseif ($row['is_financial'] && $row['amount']) {
                        $actorNonCash += abs((int) $row['amount']);
                    }
                }

                return [
                    'user' => $first['user'],
                    'actor_id' => $first['actor_id'],
                    'name' => $first['actor_name'],
                    'role_label' => $first['role_label'],
                    'count' => $group->count(),
                    'cash_in_centimes' => $actorCashIn,
                    'cash_out_centimes' => $actorCashOut,
                    'net_cash_centimes' => $actorCashIn - $actorCashOut,
                    'non_cash_centimes' => $actorNonCash,
                    'last_at' => $first['occurred_at'],
                ];
            })
            ->values();

        return [
            'has_activity' => true,
            'total_count' => $items->count(),
            'cash_in_centimes' => $cashIn,
            'cash_out_centimes' => $cashOut,
            'net_cash_centimes' => $cashIn - $cashOut,
            'non_cash_centimes' => $nonCash,
            'total_collected_centimes' => $cashIn + $nonCash,
            'actors' => $actors,
            'items' => $items,
        ];
    }

    private function emptyTiersSummary(): array
    {
        return [
            'has_activity' => false,
            'total_count' => 0,
            'cash_in_centimes' => 0,
            'cash_out_centimes' => 0,
            'net_cash_centimes' => 0,
            'non_cash_centimes' => 0,
            'total_collected_centimes' => 0,
            'actors' => collect(),
            'items' => collect(),
        ];
    }
}
