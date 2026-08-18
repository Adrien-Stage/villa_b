<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Payment;
use App\Models\RestaurantCustomerOrder;
use App\Models\RestaurantPantryMovement;
use App\Models\ShopOrder;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Les schémas d'imputation : ce qui transforme une opération en écriture.
 *
 * Chaque méthode porte UN schéma et une seule règle métier. Toutes sont
 * idempotentes par construction — le couple (source, schéma) est unique en
 * base, donc rejouer une journée ne double jamais rien. C'est ce qui permet
 * de relancer la comptabilisation sans crainte, y compris sur l'historique.
 *
 * Le produit est reconnu **à sa source**, pas au moment de l'encaissement :
 * l'hébergement sur la facture, la restauration sur la commande, la boutique
 * sur la vente. Chacun porte son centre d'analyse, ce qui rendra la marge par
 * point de vente lisible sans retraitement (classe 9).
 *
 * Montants en centimes FCFA.
 */
class LedgerPostingService
{
    /** Schémas, pour la contrainte d'unicité et la traçabilité. */
    public const SCHEMA_INVOICE = 'invoice';
    public const SCHEMA_PAYMENT = 'payment';
    public const SCHEMA_RESTAURANT_SALE = 'restaurant_sale';
    public const SCHEMA_SHOP_SALE = 'shop_sale';
    public const SCHEMA_EXPENSE = 'expense';
    public const SCHEMA_FOOD_COST = 'food_cost';

    public function __construct(
        private readonly LedgerService $ledger,
        private readonly TaxationService $taxation,
    ) {
    }

    /**
     * Comptabilise tout ce qui relève d'une journée.
     *
     * C'est le point d'entrée du night audit : une passe unique, rejouable,
     * qui rattrape aussi ce qui n'aurait pas été comptabilisé la veille.
     *
     * @return array<string, int> Nombre d'écritures produites par schéma.
     */
    public function postDay(CarbonInterface $date): array
    {
        $start = $date->copy()->startOfDay();
        $end = $date->copy()->endOfDay();

        $compte = [
            self::SCHEMA_INVOICE         => 0,
            self::SCHEMA_RESTAURANT_SALE => 0,
            self::SCHEMA_SHOP_SALE       => 0,
            self::SCHEMA_PAYMENT         => 0,
            self::SCHEMA_EXPENSE         => 0,
            self::SCHEMA_FOOD_COST       => 0,
        ];

        // Produits d'abord, encaissements ensuite : un règlement solde une
        // créance qui doit déjà exister au grand livre.
        // whereDate et non whereBetween : invoice_date est une colonne DATE,
        // qu'un encadrement par bornes datetime laisserait passer à côté.
        Invoice::query()
            ->whereDate('invoice_date', $start->toDateString())
            ->with('booking', 'customer')
            ->get()
            ->each(function (Invoice $invoice) use (&$compte) {
                if ($this->postInvoice($invoice)) {
                    $compte[self::SCHEMA_INVOICE]++;
                }
            });

        RestaurantCustomerOrder::query()
            ->where('payment_status', 'paid')
            ->whereBetween('paid_at', [$start, $end])
            ->get()
            ->each(function (RestaurantCustomerOrder $order) use (&$compte) {
                if ($this->postRestaurantSale($order)) {
                    $compte[self::SCHEMA_RESTAURANT_SALE]++;
                }
            });

        ShopOrder::query()
            ->where('payment_status', 'paid')
            ->whereBetween('paid_at', [$start, $end])
            ->get()
            ->each(function (ShopOrder $order) use (&$compte) {
                if ($this->postShopSale($order)) {
                    $compte[self::SCHEMA_SHOP_SALE]++;
                }
            });

        Payment::query()
            ->where('status', 'completed')
            ->whereBetween('paid_at', [$start, $end])
            ->with('booking')
            ->get()
            ->each(function (Payment $payment) use (&$compte) {
                if ($this->postPayment($payment)) {
                    $compte[self::SCHEMA_PAYMENT]++;
                }
            });

        Expense::query()
            ->whereBetween('occurred_at', [$start, $end])
            ->get()
            ->each(function (Expense $expense) use (&$compte) {
                if ($this->postExpense($expense)) {
                    $compte[self::SCHEMA_EXPENSE]++;
                }
            });

        if ($this->postFoodCost($start)) {
            $compte[self::SCHEMA_FOOD_COST]++;
        }

        return $compte;
    }

    /**
     * Facture d'hébergement : la créance client naît ici.
     *
     *   D 411000  TTC hébergement          (auxiliaire : le client)
     *     C 706000  base hors taxes
     *     C 443100  TVA collectée
     *
     * Seul l'hébergement est porté : les extras du folio proviennent du
     * restaurant et de la boutique, déjà comptabilisés à leur propre source.
     * Les additionner ici les compterait deux fois.
     */
    public function postInvoice(Invoice $invoice): ?JournalEntry
    {
        if ($this->dejaComptabilise($invoice, self::SCHEMA_INVOICE)) {
            return null;
        }

        $booking = $invoice->booking;
        $extras = (int) ($booking?->extras_amount ?? 0);
        $ttc = (int) $invoice->total_amount - $extras;

        if ($ttc <= 0) {
            return null; // Séjour offert, ou intégralement composé d'extras.
        }

        $decomposition = $this->taxation->breakdown($ttc);
        $client = $invoice->customer ?? $booking?->customer;

        $lignes = [[
            'account'   => Account::CLIENTS,
            'label'     => 'Facture ' . $invoice->invoice_number,
            'debit'     => $ttc,
            'auxiliary' => $client,
        ], [
            'account' => Account::REVENUE_ACCOMMODATION,
            'label'   => 'Hébergement',
            'credit'  => $decomposition->ht,
            'center'  => JournalEntryLine::CENTER_ACCOMMODATION,
        ]];

        if ($decomposition->vat > 0) {
            $lignes[] = [
                'account' => Account::VAT_COLLECTED,
                'label'   => 'TVA collectée',
                'credit'  => $decomposition->vat,
            ];
        }

        return $this->ledger->post(
            journalCode: Journal::SALES,
            date: Carbon::parse($invoice->invoice_date),
            label: 'Hébergement — facture ' . $invoice->invoice_number,
            lines: $lignes,
            source: $invoice,
            schema: self::SCHEMA_INVOICE,
            reference: $invoice->invoice_number,
        );
    }

    /**
     * Vente au restaurant.
     *
     * La contrepartie dépend du règlement : portée au folio, la commande
     * débite le compte client et sera encaissée avec le séjour ; réglée sur
     * place, elle débite directement la trésorerie. C'est cette distinction
     * qui évite de compter deux fois un dîner porté à la chambre.
     */
    public function postRestaurantSale(RestaurantCustomerOrder $order): ?JournalEntry
    {
        return $this->postSale(
            order: $order,
            ttc: (int) $order->total_amount,
            revenueAccount: Account::REVENUE_RESTAURANT,
            center: JournalEntryLine::CENTER_RESTAURANT,
            schema: self::SCHEMA_RESTAURANT_SALE,
            label: 'Restaurant — commande #' . $order->id,
            date: $order->paid_at,
            method: $order->payment_method,
            customer: $order->booking?->customer,
        );
    }

    /** Vente en boutique — même logique que le restaurant. */
    public function postShopSale(ShopOrder $order): ?JournalEntry
    {
        return $this->postSale(
            order: $order,
            ttc: (int) $order->total_amount,
            revenueAccount: Account::REVENUE_SHOP,
            center: JournalEntryLine::CENTER_SHOP,
            schema: self::SCHEMA_SHOP_SALE,
            label: 'Boutique — ' . ($order->order_number ?: 'commande #' . $order->id),
            date: $order->paid_at,
            method: $order->payment_method,
            customer: $order->customer ?? $order->booking?->customer,
        );
    }

    /**
     * Encaissement : la créance client se solde.
     *
     *   D 571000 / 521000 / 531000   selon le moyen de paiement
     *     C 411000                    (auxiliaire : le client)
     *
     * Un remboursement porte un montant négatif : les sens s'inversent.
     */
    public function postPayment(Payment $payment): ?JournalEntry
    {
        if ($this->dejaComptabilise($payment, self::SCHEMA_PAYMENT)) {
            return null;
        }

        $montant = (int) $payment->amount;

        if ($montant === 0) {
            return null;
        }

        $tresorerie = $this->compteTresorerie($payment->method);
        $client = $payment->booking?->customer ?? $payment->customer;
        $remboursement = $montant < 0;
        $valeur = abs($montant);

        $lignes = [[
            'account' => $tresorerie,
            'label'   => $remboursement ? 'Remboursement' : 'Encaissement',
            'debit'   => $remboursement ? 0 : $valeur,
            'credit'  => $remboursement ? $valeur : 0,
        ], [
            'account'   => Account::CLIENTS,
            'label'     => $payment->reference ?: 'Règlement client',
            'debit'     => $remboursement ? $valeur : 0,
            'credit'    => $remboursement ? 0 : $valeur,
            'auxiliary' => $client,
        ]];

        return $this->ledger->post(
            journalCode: $tresorerie === Account::CASH ? Journal::CASH : Journal::BANK,
            date: Carbon::parse($payment->paid_at ?? $payment->created_at),
            label: ($remboursement ? 'Remboursement' : 'Encaissement')
                . ($payment->booking?->booking_number ? ' — séjour ' . $payment->booking->booking_number : ''),
            lines: $lignes,
            source: $payment,
            schema: self::SCHEMA_PAYMENT,
            reference: $payment->reference,
        );
    }

    /**
     * Dépense décaissée.
     *
     *   D 6xx      selon la nature
     *     C 571000 / 521000
     */
    public function postExpense(Expense $expense): ?JournalEntry
    {
        if ($this->dejaComptabilise($expense, self::SCHEMA_EXPENSE)) {
            return null;
        }

        $montant = (int) $expense->amount;

        if ($montant <= 0) {
            return null;
        }

        $tresorerie = $this->compteTresorerie($expense->payment_method);

        return $this->ledger->post(
            journalCode: $tresorerie === Account::CASH ? Journal::CASH : Journal::BANK,
            date: Carbon::parse($expense->occurred_at),
            label: $expense->categoryLabel() . ' — ' . $expense->label,
            lines: [
                ['account' => $this->compteCharge($expense->category), 'label' => $expense->label, 'debit' => $montant],
                ['account' => $tresorerie, 'label' => 'Décaissement', 'credit' => $montant],
            ],
            source: $expense,
            schema: self::SCHEMA_EXPENSE,
        );
    }

    /**
     * Coût matière de la journée (Food Cost).
     *
     *   D 603200  variation des stocks de matières premières
     *     C 321000  matières premières — cuisine
     *
     * Agrégé à la journée plutôt qu'à la commande : une écriture par plat
     * vendu noierait le journal sans rien apprendre de plus. La source est la
     * date, ce qui garde le schéma idempotent.
     */
    public function postFoodCost(CarbonInterface $date): ?JournalEntry
    {
        $start = $date->copy()->startOfDay();
        $end = $date->copy()->endOfDay();

        $cout = (int) RestaurantPantryMovement::query()
            ->where('type', RestaurantPantryMovement::TYPE_OUT)
            ->whereBetween('occurred_at', [$start, $end])
            ->sum('total_cost');

        if ($cout <= 0) {
            return null;
        }

        // Pas de modèle source : on marque l'idempotence par un schéma daté.
        $schema = self::SCHEMA_FOOD_COST . ':' . $start->toDateString();

        $existante = JournalEntry::query()->where('schema', $schema)->first();

        if ($existante !== null) {
            return null;
        }

        return $this->ledger->post(
            journalCode: Journal::MISC,
            date: $start,
            label: 'Coût matière du ' . $start->format('d/m/Y'),
            lines: [
                ['account' => '603200', 'label' => 'Consommation cuisine', 'debit' => $cout, 'center' => JournalEntryLine::CENTER_RESTAURANT],
                ['account' => '321000', 'label' => 'Sortie de stock', 'credit' => $cout],
            ],
            schema: $schema,
        );
    }

    // ── Rouages internes ────────────────────────────────────────────────────

    /**
     * Schéma commun aux ventes restaurant et boutique : seuls le compte de
     * produit, le centre d'analyse et le libellé changent.
     */
    private function postSale(
        $order,
        int $ttc,
        string $revenueAccount,
        string $center,
        string $schema,
        string $label,
        $date,
        ?string $method,
        $customer,
    ): ?JournalEntry {
        if ($this->dejaComptabilise($order, $schema)) {
            return null;
        }

        if ($ttc <= 0) {
            return null;
        }

        $decomposition = $this->taxation->breakdown($ttc);
        $auFolio = $method === 'room_charge';
        $contrepartie = $auFolio ? Account::CLIENTS : $this->compteTresorerie($method);

        $lignes = [[
            'account'   => $contrepartie,
            'label'     => $auFolio ? 'Porté au folio' : 'Encaissement',
            'debit'     => $ttc,
            'auxiliary' => $auFolio ? $customer : null,
        ], [
            'account' => $revenueAccount,
            'label'   => 'Vente',
            'credit'  => $decomposition->ht,
            'center'  => $center,
        ]];

        if ($decomposition->vat > 0) {
            $lignes[] = [
                'account' => Account::VAT_COLLECTED,
                'label'   => 'TVA collectée',
                'credit'  => $decomposition->vat,
            ];
        }

        return $this->ledger->post(
            // Porté au folio, la vente relève des ventes ; encaissée sur
            // place, elle relève du journal de trésorerie correspondant.
            journalCode: $auFolio
                ? Journal::SALES
                : ($contrepartie === Account::CASH ? Journal::CASH : Journal::BANK),
            date: Carbon::parse($date ?? now()),
            label: $label,
            lines: $lignes,
            source: $order,
            schema: $schema,
        );
    }

    private function dejaComptabilise($source, string $schema): bool
    {
        return $this->ledger->findBySource($source, $schema) !== null;
    }

    /** Compte de trésorerie correspondant à un moyen de paiement. */
    private function compteTresorerie(?string $method): string
    {
        return match ($method) {
            'cash', null           => Account::CASH,
            'orange_money',
            'mtn_momo'             => '531000',
            'bank_transfer',
            'check',
            'stripe',
            'card'                 => Account::BANK,
            default                => Account::CASH,
        };
    }

    /** Compte de charge correspondant à une catégorie de dépense. */
    private function compteCharge(?string $category): string
    {
        return match ($category) {
            Expense::CATEGORY_ELECTRICITY,
            Expense::CATEGORY_WATER       => '605000',
            Expense::CATEGORY_PURCHASE    => '601000',
            Expense::CATEGORY_RENT        => '622000',
            Expense::CATEGORY_MAINTENANCE => '624000',
            Expense::CATEGORY_TRANSPORT   => '611000',
            default                       => '658000',
        };
    }
}
