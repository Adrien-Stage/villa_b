<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\CashRegisterDisbursement;
use App\Models\CashRegisterSession;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\RestaurantCustomerOrder;
use App\Models\ShopOrder;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Le moteur de la comptabilité de caisse.
 *
 * Principe (conforme au document « Comptabilité de base ») : on ne compte
 * l'argent que lorsqu'il entre (recette encaissée) ou sort (dépense décaissée).
 * Les recettes ne sont PAS dupliquées : elles sont lues à la volée dans les
 * paiements et les commandes des trois activités (hébergement, restauration,
 * boutique). Seules les dépenses saisies vivent dans une table dédiée.
 *
 * Tous les montants sont en centimes FCFA.
 *
 * Anti-double-comptage : une commande resto/boutique réglée « à la chambre »
 * (room_charge) n'est PAS une recette directe — elle grossit le solde du séjour
 * et sera encaissée via le paiement du séjour. On l'exclut donc des recettes.
 */
class AccountingService
{
    /**
     * Statuts de séjour où un solde restant dû est une vraie créance.
     */
    private const OWING_BOOKING_STATUSES = [
        BookingStatus::CONFIRMED,
        BookingStatus::CHECKED_IN,
        BookingStatus::CHECKED_OUT,
        BookingStatus::COMPLETED,
    ];

    // ── Recettes ─────────────────────────────────────────────────────────────

    /**
     * Recettes encaissées sur la période, par activité et par moyen de paiement.
     *
     * @return array{hebergement:int, restaurant:int, boutique:int, total:int, by_method:array<string,int>}
     */
    public function recettes(CarbonInterface $from, CarbonInterface $to): array
    {
        // Hébergement : paiements encaissés, net des remboursements (montants < 0).
        $hebergement = (int) Payment::query()
            ->where('status', 'completed')
            ->whereBetween('paid_at', [$from, $to])
            ->sum('amount');

        // Restaurant : commandes payées directement (hors room_charge).
        $restaurant = (int) RestaurantCustomerOrder::query()
            ->where('payment_status', 'paid')
            ->where(fn ($q) => $q->where('payment_method', '!=', 'room_charge')->orWhereNull('payment_method'))
            ->whereBetween('paid_at', [$from, $to])
            ->sum('amount_paid');

        // Boutique : idem.
        $boutique = (int) ShopOrder::query()
            ->where('payment_status', 'paid')
            ->where(fn ($q) => $q->where('payment_method', '!=', 'room_charge')->orWhereNull('payment_method'))
            ->whereBetween('paid_at', [$from, $to])
            ->sum('total_amount');

        return [
            'hebergement' => $hebergement,
            'restaurant'  => $restaurant,
            'boutique'    => $boutique,
            'total'       => $hebergement + $restaurant + $boutique,
            'by_method'   => $this->recettesByMethod($from, $to),
        ];
    }

    /**
     * Ventilation des recettes par moyen de paiement (toutes activités).
     *
     * @return array<string,int>
     */
    private function recettesByMethod(CarbonInterface $from, CarbonInterface $to): array
    {
        $totals = [];

        $add = function (?string $method, int $amount) use (&$totals) {
            $method = $method ?: 'autre';
            $totals[$method] = ($totals[$method] ?? 0) + $amount;
        };

        Payment::query()
            ->where('status', 'completed')
            ->whereBetween('paid_at', [$from, $to])
            ->selectRaw('method, sum(amount) as total')
            ->groupBy('method')
            ->get()
            ->each(fn ($row) => $add($row->method, (int) $row->total));

        RestaurantCustomerOrder::query()
            ->where('payment_status', 'paid')
            ->where(fn ($q) => $q->where('payment_method', '!=', 'room_charge')->orWhereNull('payment_method'))
            ->whereBetween('paid_at', [$from, $to])
            ->selectRaw('payment_method, sum(amount_paid) as total')
            ->groupBy('payment_method')
            ->get()
            ->each(fn ($row) => $add($row->payment_method, (int) $row->total));

        ShopOrder::query()
            ->where('payment_status', 'paid')
            ->where(fn ($q) => $q->where('payment_method', '!=', 'room_charge')->orWhereNull('payment_method'))
            ->whereBetween('paid_at', [$from, $to])
            ->selectRaw('payment_method, sum(total_amount) as total')
            ->groupBy('payment_method')
            ->get()
            ->each(fn ($row) => $add($row->payment_method, (int) $row->total));

        arsort($totals);

        return $totals;
    }

    // ── Dépenses ─────────────────────────────────────────────────────────────

    /**
     * Dépenses décaissées sur la période.
     *
     * @return array{total:int, by_category:array<string,int>}
     */
    public function depenses(CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = Expense::query()
            ->inPeriod($from, $to)
            ->selectRaw('category, sum(amount) as total')
            ->groupBy('category')
            ->pluck('total', 'category');

        $byCategory = [];
        foreach (Expense::CATEGORIES as $key => $label) {
            $value = (int) ($rows[$key] ?? 0);
            if ($value !== 0) {
                $byCategory[$key] = $value;
            }
        }

        return [
            'total'       => (int) $rows->sum(),
            'by_category' => $byCategory,
        ];
    }

    // ── Compte de résultat ───────────────────────────────────────────────────

    /**
     * Produits − Charges = Résultat net, sur la période.
     */
    public function compteDeResultat(CarbonInterface $from, CarbonInterface $to): array
    {
        $recettes = $this->recettes($from, $to);
        $depenses = $this->depenses($from, $to);

        return [
            'produits'     => [
                'hebergement' => $recettes['hebergement'],
                'restaurant'  => $recettes['restaurant'],
                'boutique'    => $recettes['boutique'],
                'total'       => $recettes['total'],
            ],
            'charges'      => [
                'by_category' => $depenses['by_category'],
                'total'       => $depenses['total'],
            ],
            'resultat_net' => $recettes['total'] - $depenses['total'],
        ];
    }

    // ── Créances (instantané, pas lié à une période) ─────────────────────────

    /**
     * Ce qu'on nous doit aujourd'hui : soldes de séjours + commandes impayées.
     */
    public function creances(): array
    {
        $sejours = Booking::query()
            ->whereIn('status', self::OWING_BOOKING_STATUSES)
            ->where('balance_due', '>', 0)
            ->with('customer')
            ->orderByDesc('balance_due')
            ->get();

        // Commandes autonomes impayées (celles rattachées à un séjour sont déjà
        // dans le solde du séjour).
        $restaurant = RestaurantCustomerOrder::query()
            ->where('payment_status', 'unpaid')
            ->where('status', '!=', 'canceled')
            ->whereNull('booking_id')
            ->orderByDesc('total_amount')
            ->get();

        $boutique = ShopOrder::query()
            ->where('payment_status', 'unpaid')
            ->whereNull('booking_id')
            ->orderByDesc('total_amount')
            ->get();

        $sejoursTotal    = (int) $sejours->sum('balance_due');
        $restaurantTotal = (int) $restaurant->sum('total_amount');
        $boutiqueTotal   = (int) $boutique->sum('total_amount');

        return [
            'sejours'    => ['total' => $sejoursTotal, 'items' => $sejours],
            'restaurant' => ['total' => $restaurantTotal, 'items' => $restaurant],
            'boutique'   => ['total' => $boutiqueTotal, 'items' => $boutique],
            'total'      => $sejoursTotal + $restaurantTotal + $boutiqueTotal,
        ];
    }

    // ── Caisse ───────────────────────────────────────────────────────────────

    /**
     * Sessions de caisse et sorties sur la période (rapprochement).
     */
    public function caisse(CarbonInterface $from, CarbonInterface $to): array
    {
        $sessions = CashRegisterSession::query()
            ->whereBetween('opened_at', [$from, $to])
            ->with('user')
            ->orderByDesc('opened_at')
            ->get();

        $disbursements = CashRegisterDisbursement::query()
            ->whereHas('session', fn ($q) => $q->whereBetween('opened_at', [$from, $to]))
            ->with(['session', 'user'])
            ->latest()
            ->get();

        return [
            'sessions'            => $sessions,
            'disbursements'       => $disbursements,
            'total_disbursements' => (int) $disbursements->sum('amount'),
            // Écart net cumulé (réel − théorique) sur les sessions clôturées.
            'total_discrepancy'   => (int) $sessions->whereNotNull('closed_at')->sum('discrepancy_amount'),
        ];
    }

    // ── Cahier des recettes et des dépenses (journal consolidé) ──────────────

    /**
     * Le journal chronologique : chaque encaissement et chaque décaissement de la
     * période, fusionnés et triés. Le solde courant est calculé à l'affichage.
     *
     * @return Collection<int, array{date:CarbonInterface, libelle:string, source:string, recette:int, depense:int}>
     */
    public function journal(CarbonInterface $from, CarbonInterface $to): Collection
    {
        $entries = collect();

        // Hébergement (paiements) — un remboursement (montant < 0) est une sortie.
        Payment::query()
            ->where('status', 'completed')
            ->whereBetween('paid_at', [$from, $to])
            ->with('booking:id,booking_number')
            ->get()
            ->each(function ($p) use ($entries) {
                $ref = $p->booking?->booking_number ? "Séjour {$p->booking->booking_number}" : 'Paiement';
                $amount = (int) $p->amount;
                $entries->push([
                    'date'    => $p->paid_at,
                    'libelle' => $amount < 0 ? "Remboursement — {$ref}" : "Hébergement — {$ref}",
                    'source'  => 'hebergement',
                    'recette' => $amount >= 0 ? $amount : 0,
                    'depense' => $amount < 0 ? abs($amount) : 0,
                ]);
            });

        RestaurantCustomerOrder::query()
            ->where('payment_status', 'paid')
            ->where(fn ($q) => $q->where('payment_method', '!=', 'room_charge')->orWhereNull('payment_method'))
            ->whereBetween('paid_at', [$from, $to])
            ->get()
            ->each(fn ($o) => $entries->push([
                'date'    => $o->paid_at,
                'libelle' => 'Restaurant — commande #' . $o->id . ($o->table_number ? " (Table {$o->table_number})" : ''),
                'source'  => 'restaurant',
                'recette' => (int) $o->amount_paid,
                'depense' => 0,
            ]));

        ShopOrder::query()
            ->where('payment_status', 'paid')
            ->where(fn ($q) => $q->where('payment_method', '!=', 'room_charge')->orWhereNull('payment_method'))
            ->whereBetween('paid_at', [$from, $to])
            ->get()
            ->each(fn ($o) => $entries->push([
                'date'    => $o->paid_at,
                'libelle' => 'Boutique — ' . ($o->order_number ?: ('commande #' . $o->id)),
                'source'  => 'boutique',
                'recette' => (int) $o->total_amount,
                'depense' => 0,
            ]));

        Expense::query()
            ->inPeriod($from, $to)
            ->get()
            ->each(fn ($e) => $entries->push([
                'date'    => $e->occurred_at,
                'libelle' => $e->categoryLabel() . ' — ' . $e->label,
                'source'  => 'depense',
                'recette' => 0,
                'depense' => (int) $e->amount,
            ]));

        return $entries
            ->filter(fn ($e) => $e['date'] !== null)
            ->sortBy(fn ($e) => $e['date']->timestamp)
            ->values();
    }
}
