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
use App\Models\StockMovement;
use App\Models\StockRequisition;
use App\Models\SupplierInvoice;
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
    public const SCHEMA_SUPPLIER_INVOICE = 'supplier_invoice';
    public const SCHEMA_ECONOMAT_STOCK = 'economat_stock';
    public const SCHEMA_PANTRY_STOCK = 'pantry_stock';
    public const SCHEMA_STOCK_RECLASS = 'stock_reclass';

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
            self::SCHEMA_ECONOMAT_STOCK  => 0,
            self::SCHEMA_PANTRY_STOCK    => 0,
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

        if ($this->postEconomatStock($start)) {
            $compte[self::SCHEMA_ECONOMAT_STOCK]++;
        }

        if ($this->postPantryStock($start)) {
            $compte[self::SCHEMA_PANTRY_STOCK]++;
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
     * Facture fournisseur, retenue à la source comprise.
     *
     *   D 6xx      base hors taxes           (nature de la charge)
     *   D 445100   TVA récupérable
     *     C 401000   net à payer             (auxiliaire : le fournisseur)
     *     C 442100   retenue à la source
     *
     * La retenue se comptabilise **dans la même écriture**, en taxe négative :
     * la dette envers le fournisseur naît déjà nette de ce qu'on prélèvera
     * pour l'État. La sortir dans une seconde écriture laisserait, entre les
     * deux, un solde fournisseur qui n'a jamais été dû.
     *
     * Sans retenue, la ligne 442100 est simplement absente et le net à payer
     * vaut le TTC.
     */
    public function postSupplierInvoice(SupplierInvoice $invoice): ?JournalEntry
    {
        if ($this->dejaComptabilise($invoice, self::SCHEMA_SUPPLIER_INVOICE)) {
            return null;
        }

        if ($invoice->amount_ttc <= 0) {
            return null;
        }

        $libelle = $invoice->supplier?->name . ' — ' . $invoice->number;

        $lignes = [[
            'account' => $invoice->charge_account,
            'label'   => $invoice->label,
            'debit'   => $invoice->amount_ht,
        ]];

        if ($invoice->amount_vat > 0) {
            $lignes[] = [
                'account' => Account::VAT_DEDUCTIBLE,
                'label'   => 'TVA récupérable',
                'debit'   => $invoice->amount_vat,
            ];
        }

        $lignes[] = [
            'account'   => Account::SUPPLIERS,
            'label'     => 'Facture ' . $invoice->number,
            'credit'    => $invoice->net_payable,
            'auxiliary' => $invoice->supplier,
        ];

        if ($invoice->withholding_amount > 0) {
            $lignes[] = [
                'account' => Account::WITHHOLDING,
                'label'   => 'Retenue ' . $invoice->withholdingLabel()
                    . ' ' . rtrim(rtrim(number_format($invoice->withholdingRate(), 2, ',', ''), '0'), ',') . ' %',
                'credit'  => $invoice->withholding_amount,
            ];
        }

        return $this->ledger->post(
            journalCode: Journal::PURCHASES,
            date: Carbon::parse($invoice->invoice_date),
            label: $libelle,
            lines: $lignes,
            source: $invoice,
            schema: self::SCHEMA_SUPPLIER_INVOICE,
            reference: $invoice->number,
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

    /**
     * Mouvements de l'économat de la journée, en inventaire permanent.
     *
     * La facture fournisseur porte l'achat en 60x ; le stock, lui, vit en
     * classe 3 avec la variation (603x) en contrepartie. Sans ce reflet, la
     * consommation passée en 603 s'ajouterait à l'achat et la charge serait
     * comptée deux fois.
     *
     *   Entrée (réception, excédent)    D 3x                 C 603x
     *   Sortie (service, manquant)      D 603x [centre]      C 3x
     *   Transfert vers la cuisine       D 321000             C 3x   (sans charge)
     *
     * La livraison à la boutique est passée en charge : la boutique ne
     * comptabilise pas encore le coût de ses ventes. Agrégé à la journée,
     * idempotent par schéma daté comme le coût matière.
     */
    public function postEconomatStock(CarbonInterface $date): ?JournalEntry
    {
        $start = $date->copy()->startOfDay();
        $end = $date->copy()->endOfDay();
        $schema = self::SCHEMA_ECONOMAT_STOCK . ':' . $start->toDateString();

        if (JournalEntry::query()->where('schema', $schema)->exists()) {
            return null;
        }

        // La reprise du stock initial entre au grand livre par les à-nouveaux
        // du comptable : la passer ici la compterait deux fois.
        $mouvements = StockMovement::query()
            ->with('item.category')
            ->where(fn ($q) => $q->whereNull('source_type')
                ->orWhere('source_type', '!=', StockMovement::SOURCE_OPENING))
            ->whereBetween('occurred_at', [$start, $end])
            ->orderBy('id')
            ->get();

        $requisitions = StockRequisition::query()
            ->whereIn('id', $mouvements
                ->where('source_type', StockMovement::SOURCE_REQUISITION)
                ->pluck('source_id')
                ->filter()
                ->unique())
            ->get()
            ->keyBy('id');

        $imputations = [];

        foreach ($mouvements as $mouvement) {
            if ($mouvement->item === null) {
                continue;
            }

            $valeur = (int) round(abs((float) $mouvement->quantity) * $mouvement->unit_cost);

            if ($valeur <= 0) {
                continue;
            }

            // Le compte retenu au moment du mouvement : celui de la catégorie a
            // pu changer depuis, et le reclassement a déjà déplacé le stock.
            $stock = $mouvement->stock_account ?: $mouvement->item->stockAccount();
            $variation = Account::variationFor($stock);
            $entree = (float) $mouvement->quantity > 0;
            $inventaire = $mouvement->type === StockMovement::TYPE_ADJUSTMENT;

            if ($entree) {
                $libelle = $inventaire ? 'Excédents d’inventaire économat' : 'Entrées en stock économat';
                $this->imputer($imputations, $stock, $libelle, $valeur, 0);
                $this->imputer($imputations, $variation, $libelle, 0, $valeur, JournalEntryLine::CENTER_STORE);

                continue;
            }

            $requisition = $mouvement->source_type === StockMovement::SOURCE_REQUISITION
                ? $requisitions->get($mouvement->source_id)
                : null;

            // Livré à un dépôt de service : même article, même compte de
            // stock, seul le lieu change. La charge naîtra à l'inventaire du
            // dépôt, qui révèle ce qui a été consommé.
            if ($requisition?->service_store_id !== null) {
                continue;
            }

            // Le stock change de magasin, il ne se consomme pas : la charge
            // naîtra à la sortie du garde-manger (coût matière).
            if ($requisition?->department === 'restaurant') {
                if ($stock !== Account::STOCK_KITCHEN) {
                    $this->imputer($imputations, Account::STOCK_KITCHEN, 'Transferts vers la cuisine', $valeur, 0);
                    $this->imputer($imputations, $stock, 'Transferts vers la cuisine', 0, $valeur);
                }

                continue;
            }

            $libelle = match (true) {
                $requisition !== null => 'Livraisons aux services',
                $inventaire           => 'Manquants d’inventaire économat',
                default               => 'Sorties de stock économat',
            };

            $centre = $requisition !== null
                ? $this->centreDuService($requisition->department)
                : JournalEntryLine::CENTER_STORE;

            $this->imputer($imputations, $variation, $libelle, $valeur, 0, $centre);
            $this->imputer($imputations, $stock, $libelle, 0, $valeur);
        }

        if ($imputations === []) {
            return null;
        }

        return $this->ledger->post(
            journalCode: Journal::MISC,
            date: $start,
            label: 'Mouvements de stock économat du ' . $start->format('d/m/Y'),
            lines: array_values($imputations),
            schema: $schema,
        );
    }

    /**
     * Entrées et écarts du garde-manger de la journée, en inventaire permanent.
     *
     * Le coût matière crédite le 321000 à chaque sortie ; ce schéma le débite
     * pour tout ce qui y entre sans passer par l'économat.
     *
     *   Achat direct, préparation, retour de vente   D 321000   C 603200
     *   Excédent d'inventaire                        D 321000   C 603200
     *   Manquant d'inventaire                        D 603200   C 321000
     *
     * Le transfert depuis l'économat est déjà passé côté économat : on l'écarte.
     * Une préparation produite se neutralise avec la sortie de ses ingrédients ;
     * la charge naît à sa vente.
     */
    public function postPantryStock(CarbonInterface $date): ?JournalEntry
    {
        $start = $date->copy()->startOfDay();
        $end = $date->copy()->endOfDay();
        $schema = self::SCHEMA_PANTRY_STOCK . ':' . $start->toDateString();

        if (JournalEntry::query()->where('schema', $schema)->exists()) {
            return null;
        }

        $mouvements = RestaurantPantryMovement::query()
            ->whereIn('type', [RestaurantPantryMovement::TYPE_IN, RestaurantPantryMovement::TYPE_ADJUST])
            ->whereNull('stock_requisition_id')
            ->where(fn ($q) => $q->whereNull('reason')
                ->orWhere('reason', '!=', RestaurantPantryMovement::REASON_TRANSFER_IN))
            ->whereBetween('occurred_at', [$start, $end])
            ->orderBy('id')
            ->get();

        $imputations = [];
        $stock = Account::STOCK_KITCHEN;
        $variation = Account::variationFor($stock);

        foreach ($mouvements as $mouvement) {
            if ($mouvement->type === RestaurantPantryMovement::TYPE_IN) {
                $valeur = (int) $mouvement->total_cost;
                $libelle = match ($mouvement->reason) {
                    RestaurantPantryMovement::REASON_PRODUCTION  => 'Préparations produites',
                    RestaurantPantryMovement::REASON_SALE_RETURN => 'Retours de ventes annulées',
                    default                                      => 'Entrées en cuisine',
                };
                $ecart = $valeur;
            } else {
                // Un ajustement porte le stock constaté, pas l'écart : on le
                // retrouve contre le stock laissé par le mouvement précédent.
                $precedent = (float) (RestaurantPantryMovement::query()
                    ->where('restaurant_pantry_item_id', $mouvement->restaurant_pantry_item_id)
                    ->where('id', '<', $mouvement->id)
                    ->orderByDesc('id')
                    ->value('stock_after') ?? 0);

                $ecart = (int) round(((float) $mouvement->stock_after - $precedent) * (float) $mouvement->unit_cost);
                $valeur = abs($ecart);
                $libelle = $ecart > 0 ? 'Excédents d’inventaire cuisine' : 'Manquants d’inventaire cuisine';
            }

            if ($valeur <= 0) {
                continue;
            }

            if ($ecart > 0) {
                $this->imputer($imputations, $stock, $libelle, $valeur, 0);
                $this->imputer($imputations, $variation, $libelle, 0, $valeur, JournalEntryLine::CENTER_RESTAURANT);
            } else {
                $this->imputer($imputations, $variation, $libelle, $valeur, 0, JournalEntryLine::CENTER_RESTAURANT);
                $this->imputer($imputations, $stock, $libelle, 0, $valeur);
            }
        }

        if ($imputations === []) {
            return null;
        }

        return $this->ledger->post(
            journalCode: Journal::MISC,
            date: $start,
            label: 'Mouvements du garde-manger du ' . $start->format('d/m/Y'),
            lines: array_values($imputations),
            schema: $schema,
        );
    }

    /**
     * Valeur du stock repris sur un exercice, par compte de stock.
     *
     * Ce sont les lignes de classe 3 que le comptable porte dans ses
     * à-nouveaux : la reprise n'est jamais comptabilisée automatiquement.
     *
     * @return array<string, int> [compte => montant en centimes]
     */
    public function openingStockByAccount(CarbonInterface $from, CarbonInterface $to): array
    {
        return StockMovement::query()
            ->with('item.category')
            ->where('source_type', StockMovement::SOURCE_OPENING)
            ->whereBetween('occurred_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->get()
            ->groupBy(fn (StockMovement $m) => $m->stock_account ?: $m->item?->stockAccount() ?? Account::STOCK_STORE)
            ->map(fn ($mouvements) => (int) $mouvements->sum(
                fn (StockMovement $m) => (int) round((float) $m->quantity * $m->unit_cost)
            ))
            ->sortKeys()
            ->all();
    }

    /**
     * Reclassement du stock d'un compte de classe 3 vers un autre, quand une
     * catégorie change de compte ou un article de catégorie.
     *
     *   D 3x nouveau compte    C 3x ancien compte
     *
     * Aucune charge : la marchandise ne bouge pas, seul son classement change.
     * Passé immédiatement et non au night audit, car les mouvements suivants
     * sont déjà comptabilisés sur le nouveau compte.
     */
    public function postStockReclassification(string $from, string $to, int $amount, string $label, CarbonInterface $date): ?JournalEntry
    {
        if ($amount <= 0 || $from === $to) {
            return null;
        }

        return $this->ledger->post(
            journalCode: Journal::MISC,
            date: $date,
            label: $label,
            lines: [
                ['account' => $to, 'label' => $label, 'debit' => $amount],
                ['account' => $from, 'label' => $label, 'credit' => $amount],
            ],
            schema: self::SCHEMA_STOCK_RECLASS,
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

    /**
     * Cumule un montant sur une ligne d'écriture, par compte, sens, libellé
     * et centre : une journée de mouvements tient en quelques lignes.
     *
     * @param  array<string, array<string, mixed>>  $imputations
     */
    private function imputer(array &$imputations, string $compte, string $libelle, int $debit, int $credit, ?string $centre = null): void
    {
        $cle = implode('|', [$compte, $debit > 0 ? 'D' : 'C', $libelle, $centre]);

        $imputations[$cle] ??= ['account' => $compte, 'label' => $libelle, 'debit' => 0, 'credit' => 0, 'center' => $centre];
        $imputations[$cle]['debit'] += $debit;
        $imputations[$cle]['credit'] += $credit;
    }

    /** Centre d'analyse qui supporte la consommation d'un service demandeur. */
    private function centreDuService(?string $service): string
    {
        return match ($service) {
            'hebergement', 'housekeeping' => JournalEntryLine::CENTER_ACCOMMODATION,
            'restaurant'                  => JournalEntryLine::CENTER_RESTAURANT,
            'boutique'                    => JournalEntryLine::CENTER_SHOP,
            default                       => JournalEntryLine::CENTER_STORE,
        };
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
