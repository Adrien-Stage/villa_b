<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Expense;
use App\Models\FolioItem;
use App\Models\Invoice;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Payment;
use App\Models\ReceptionSale;
use App\Models\ReceptionSaleItem;
use App\Models\RestaurantBuffetEntry;
use App\Models\RestaurantBanquet;
use App\Models\RestaurantBanquetPayment;
use App\Models\RestaurantCustomerOrder;
use App\Models\RestaurantPantryMovement;
use App\Models\ShopOrder;
use App\Models\StockMovement;
use App\Models\StockRequisition;
use App\Models\SupplierInvoice;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
    public const SCHEMA_RECEPTION_SALE = 'reception_sale';
    public const SCHEMA_BUFFET_ENTRY = 'buffet_entry';
    public const SCHEMA_BANQUET_SALE = 'banquet_sale';
    public const SCHEMA_BANQUET_PAYMENT = 'banquet_payment';
    public const SCHEMA_EXPENSE = 'expense';
    public const SCHEMA_FOOD_COST = 'food_cost';
    public const SCHEMA_SUPPLIER_INVOICE = 'supplier_invoice';
    public const SCHEMA_ECONOMAT_STOCK = 'economat_stock';
    public const SCHEMA_PANTRY_STOCK = 'pantry_stock';
    public const SCHEMA_STOCK_RECLASS = 'stock_reclass';
    public const SCHEMA_SERVICE_STORE = 'service_store_stock';
    public const SCHEMA_MINIBAR_SALE = 'minibar_sale';
    public const SCHEMA_MINIBAR_STOCK = 'minibar_stock';

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
            self::SCHEMA_RECEPTION_SALE  => 0,
            self::SCHEMA_BUFFET_ENTRY    => 0,
            self::SCHEMA_BANQUET_SALE    => 0,
            self::SCHEMA_BANQUET_PAYMENT => 0,
            self::SCHEMA_PAYMENT         => 0,
            self::SCHEMA_EXPENSE         => 0,
            self::SCHEMA_FOOD_COST       => 0,
            self::SCHEMA_ECONOMAT_STOCK  => 0,
            self::SCHEMA_PANTRY_STOCK    => 0,
            self::SCHEMA_SERVICE_STORE   => 0,
            self::SCHEMA_MINIBAR_SALE    => 0,
            self::SCHEMA_MINIBAR_STOCK   => 0,
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

        // Consommations du mini-bar portées au folio, ou facturées à part après
        // le départ : la créance naît au relevé.
        \App\Models\MinibarCheck::query()
            ->whereIn('billing', [\App\Models\MinibarCheck::BILLING_FOLIO,
                \App\Models\MinibarCheck::BILLING_A_FACTURER])
            ->whereBetween('checked_at', [$start, $end])
            ->with('booking.customer')
            ->get()
            ->each(function (\App\Models\MinibarCheck $check) use (&$compte) {
                if ($this->postMinibarSale($check)) {
                    $compte[self::SCHEMA_MINIBAR_SALE]++;
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

        ReceptionSale::query()->whereIn('payment_status', ['paid', 'charged_to_room'])
            ->whereBetween('paid_at', [$start, $end])->with(['booking.customer', 'payment', 'customer'])
            ->each(function (ReceptionSale $sale) use (&$compte) {
                if ($this->postReceptionSale($sale)) {
                    $compte[self::SCHEMA_RECEPTION_SALE]++;
                }
            });

        RestaurantBuffetEntry::query()->whereBetween('created_at', [$start, $end])->with('booking.customer')
            ->each(function (RestaurantBuffetEntry $entry) use (&$compte) {
                if ($this->postSale(
                    order: $entry,
                    ttc: $entry->amount,
                    revenueAccount: Account::REVENUE_RESTAURANT,
                    center: JournalEntryLine::CENTER_RESTAURANT,
                    schema: self::SCHEMA_BUFFET_ENTRY,
                    label: 'Buffet — entrée #' . $entry->id,
                    date: $entry->created_at,
                    method: $entry->payment_method,
                    customer: $entry->booking?->customer,
                )) {
                    $compte[self::SCHEMA_BUFFET_ENTRY]++;
                }
            });

        $this->postBanquets($start, $end, $compte);

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

        if ($this->postServiceStoreStock($start)) {
            $compte[self::SCHEMA_SERVICE_STORE]++;
        }

        if ($this->postMinibarStock($start)) {
            $compte[self::SCHEMA_MINIBAR_STOCK]++;
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
     * restaurant, de la boutique et du mini-bar, déjà comptabilisés à leur
     * propre source.
     * Les additionner ici les compterait deux fois.
     */
    public function postInvoice(Invoice $invoice): ?JournalEntry
    {
        if ($this->dejaComptabilise($invoice, self::SCHEMA_INVOICE)) {
            return null;
        }

        // Facture complémentaire : des consommations du mini-bar, déjà
        // comptabilisées au relevé qui les a constatées.
        if ($invoice->isComplementaire()) {
            return null;
        }

        $booking = $invoice->booking;
        $client = $invoice->customer ?? $booking?->customer;

        $invoice->loadMissing('items');
        if ($invoice->items->contains(fn ($item) => $item->category !== 'tourist_tax' && $item->tax_rate_code !== null)) {
            $lignes = $this->invoiceTaxLines($invoice, $client);
        } else {
            $lignes = $this->legacyInvoiceLines($invoice, $client);
        }
        if ($lignes === []) {
            return null;
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

    private function legacyInvoiceLines(Invoice $invoice, $client): array
    {
        $lodgingTax = (int) $invoice->tourist_tax_amount;
        $ttc = (int) $invoice->total_amount - (int) ($invoice->booking?->extras_amount ?? 0) - $lodgingTax;
        if ($ttc <= 0 && $lodgingTax === 0) {
            return [];
        }

        $ttc = max(0, $ttc);
        $tax = $this->taxation->breakdown($ttc);
        return [
            ['account' => Account::CLIENTS, 'debit' => $ttc + $lodgingTax, 'auxiliary' => $client],
            ['account' => Account::REVENUE_ACCOMMODATION, 'credit' => $tax->ht,
                'center' => JournalEntryLine::CENTER_ACCOMMODATION],
            ['account' => Account::VAT_COLLECTED, 'credit' => $tax->vat],
            ['account' => Account::TOURIST_TAX, 'credit' => $lodgingTax],
        ];
    }

    private function invoiceTaxLines(Invoice $invoice, $client): array
    {
        $folioIds = $invoice->items->where('source_type', FolioItem::class)->pluck('source_id');
        $fromSales = collect();
        if ($folioIds->isNotEmpty()) {
            $fromSales = RestaurantCustomerOrder::query()->whereIn('folio_item_id', $folioIds)
                ->where('payment_status', 'paid')->pluck('folio_item_id')
                ->merge(ShopOrder::query()->whereIn('folio_item_id', $folioIds)
                    ->where('payment_status', 'paid')->pluck('folio_item_id'))
                ->merge(RestaurantBuffetEntry::query()->whereIn('folio_item_id', $folioIds)->pluck('folio_item_id'))
                ->merge(ReceptionSaleItem::query()->whereIn('folio_item_id', $folioIds)->pluck('folio_item_id'))
                ->merge(\App\Models\MinibarCheckLine::query()->whereIn('folio_item_id', $folioIds)->pluck('folio_item_id'));
        }
        $total = 0;
        $imputations = [];
        foreach ($invoice->items as $item) {
            if ($item->source_type === FolioItem::class && $fromSales->contains($item->source_id)) {
                continue;
            }
            $total += $item->total_price;
            if ($item->category === 'tourist_tax') {
                $this->imputer($imputations, Account::TOURIST_TAX, 'Taxe de séjour', 0, $item->total_price);
                continue;
            }
            $centre = match ($item->category) {
                'restaurant' => JournalEntryLine::CENTER_RESTAURANT,
                'shop' => JournalEntryLine::CENTER_SHOP,
                'laundry', 'spa', 'activity' => $item->category,
                default => JournalEntryLine::CENTER_ACCOMMODATION,
            };
            $compte = match ($centre) {
                JournalEntryLine::CENTER_RESTAURANT => Account::REVENUE_RESTAURANT,
                JournalEntryLine::CENTER_SHOP => Account::REVENUE_SHOP,
                default => Account::REVENUE_ACCOMMODATION,
            };
            $this->imputerCredit($imputations, $compte, $item->description, $item->total_price - $item->tax_amount, $centre);
            $this->imputerCredit($imputations, Account::VAT_COLLECTED, 'TVA collectée', $item->tax_amount - $item->tax_surtax_amount);
            $this->imputerCredit($imputations, Account::VAT_SURTAX, 'Centimes additionnels communaux', $item->tax_surtax_amount);
        }
        if ($total === 0 && $imputations === []) {
            return [];
        }

        return array_merge([[
            'account' => Account::CLIENTS, 'label' => $invoice->invoice_number,
            'debit' => max(0, $total), 'credit' => max(0, -$total), 'auxiliary' => $client,
        ]], array_values($imputations));
    }

    private function imputerCredit(array &$imputations, string $account, string $label, int $value, ?string $center = null): void
    {
        if ($value !== 0) {
            $this->imputer($imputations, $account, $label, max(0, -$value), max(0, $value), $center);
        }
    }

    public function postReceptionSale(ReceptionSale $sale): ?JournalEntry
    {
        $method = $sale->payment_type === 'room_charge' ? 'room_charge' : $sale->payment_method;
        // Une ancienne écriture d'encaissement peut déjà solder cette vente.
        // Dans ce cas la vente crée la créance correspondante.
        if ($sale->payment && $this->dejaComptabilise($sale->payment, self::SCHEMA_PAYMENT)) {
            $method = 'room_charge';
        }

        return $this->postSale(
            order: $sale,
            ttc: $sale->total_amount,
            revenueAccount: Account::REVENUE_ACCOMMODATION,
            center: JournalEntryLine::CENTER_ACCOMMODATION,
            schema: self::SCHEMA_RECEPTION_SALE,
            label: 'POS Réception — ' . $sale->sale_number,
            date: $sale->paid_at,
            method: $method,
            customer: $sale->booking?->customer ?? $sale->customer,
            recordedVat: $sale->tax_amount,
        );
    }

    private function postBanquets(CarbonInterface $start, CarbonInterface $end, array &$counts): void
    {
        RestaurantBanquet::withoutGlobalScopes()->whereIn('status', [RestaurantBanquet::REALISE, RestaurantBanquet::SOLDE])
            ->where(fn ($q) => $q->whereBetween('realized_at', [$start, $end])
                ->orWhere(fn ($q) => $q->whereNull('realized_at')->whereDate('event_date', $start)))
            ->with(['customer', 'booking.customer'])->each(function (RestaurantBanquet $banquet) use (&$counts, $start) {
                if ($this->postSale(
                    order: $banquet, ttc: $banquet->total_amount, revenueAccount: Account::REVENUE_RESTAURANT,
                    center: 'conferences', schema: self::SCHEMA_BANQUET_SALE, label: 'Banquet — ' . $banquet->reference,
                    date: $banquet->realized_at ?? $start, method: 'room_charge',
                    customer: $banquet->booking?->customer ?? $banquet->customer,
                )) {
                    $counts[self::SCHEMA_BANQUET_SALE]++;
                }
                $banquet->payments()->where('paid_at', '<', $start)->get()->each(function (RestaurantBanquetPayment $payment) use ($banquet, $start) {
                    $entry = $this->ledger->findBySource($payment, self::SCHEMA_BANQUET_PAYMENT);
                    if ($entry?->lines->contains('account_code', Account::PARTNER_DEPOSITS)) {
                        $this->applyBanquetAdvance($payment, $banquet, $start);
                    }
                });
            });

        RestaurantBanquetPayment::query()->whereBetween('paid_at', [$start, $end])
            ->with(['banquet' => fn ($q) => $q->withoutGlobalScopes()->with(['customer', 'booking.customer'])])
            ->each(function (RestaurantBanquetPayment $payment) use ($start, &$counts) {
                $banquet = $payment->banquet;
                if (!$banquet || $payment->amount <= 0 || $this->dejaComptabilise($payment, self::SCHEMA_BANQUET_PAYMENT)) {
                    return;
                }
                $sale = $this->ledger->findBySource($banquet, self::SCHEMA_BANQUET_SALE);
                $account = $this->compteTresorerie($payment->payment_method);
                $unearned = !$sale || $sale->entry_date->greaterThan($start);
                $this->ledger->post($account === Account::CASH ? Journal::CASH : Journal::BANK, $start,
                    'Règlement banquet — ' . $banquet->reference, [
                        ['account' => $account, 'debit' => $payment->amount],
                        ['account' => $unearned ? Account::PARTNER_DEPOSITS : Account::CLIENTS,
                            'credit' => $payment->amount, 'auxiliary' => $banquet->booking?->customer ?? $banquet->customer],
                    ], source: $payment, schema: self::SCHEMA_BANQUET_PAYMENT, reference: $payment->receiptReference());
                $counts[self::SCHEMA_BANQUET_PAYMENT]++;
            });
    }

    private function applyBanquetAdvance(RestaurantBanquetPayment $payment, RestaurantBanquet $banquet, CarbonInterface $date): void
    {
        $customer = $banquet->booking?->customer ?? $banquet->customer;
        $this->ledger->post(Journal::MISC, $date, 'Imputation de l’acompte — ' . $banquet->reference, [
            ['account' => Account::PARTNER_DEPOSITS, 'debit' => $payment->amount, 'auxiliary' => $customer],
            ['account' => Account::CLIENTS, 'credit' => $payment->amount, 'auxiliary' => $customer],
        ], source: $payment, schema: 'banquet_apply_advance', reference: $payment->receiptReference());
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
        if ($montant > 0 && $payment->payment_origin === Payment::ORIGIN_POS_RECEPTION
            && ReceptionSale::query()->where('payment_id', $payment->id)->where('payment_status', 'paid')->exists()) {
            return null;
        }

        $walletSettlement = $payment->method === Payment::METHOD_PARTNER_WALLET;
        $deposit = $payment->purpose === Payment::PURPOSE_PARTNER_DEPOSIT;
        // Un dépôt est une avance du tiers ; l'utiliser n'est pas un second encaissement.
        $tresorerie = $walletSettlement ? Account::PARTNER_DEPOSITS : $this->compteTresorerie($payment->method);
        $client = $payment->booking?->customer ?? $payment->customer;
        $remboursement = $montant < 0;
        $valeur = abs($montant);

        $lignes = [[
            'account' => $tresorerie,
            'label'   => $remboursement ? 'Remboursement' : 'Encaissement',
            'debit'   => $remboursement ? 0 : $valeur,
            'credit'  => $remboursement ? $valeur : 0,
            'auxiliary' => $walletSettlement ? $payment->partnerOrganization : null,
        ], [
            'account'   => $deposit ? Account::PARTNER_DEPOSITS : Account::CLIENTS,
            'label'     => $payment->reference ?: 'Règlement client',
            'debit'     => $remboursement ? $valeur : 0,
            'credit'    => $remboursement ? 0 : $valeur,
            'auxiliary' => $deposit ? $payment->partnerOrganization : $client,
        ]];

        return $this->ledger->post(
            journalCode: $walletSettlement ? Journal::MISC : (
                $tresorerie === Account::CASH ? Journal::CASH : Journal::BANK),
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
        return $this->postStockBatch(
            self::SCHEMA_FOOD_COST,
            $date,
            'Coût matière',
            RestaurantPantryMovement::query()->where('type', RestaurantPantryMovement::TYPE_OUT),
            function (Collection $mouvements) {
                $cout = (int) $mouvements->sum('total_cost');

                return $cout > 0 ? [
                    ['account' => '603200', 'label' => 'Consommation cuisine', 'debit' => $cout,
                        'center' => JournalEntryLine::CENTER_RESTAURANT],
                    ['account' => '321000', 'label' => 'Sortie de stock', 'credit' => $cout],
                ] : [];
            },
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
        // La reprise du stock initial entre au grand livre par les à-nouveaux
        // du comptable : la passer ici la compterait deux fois.
        $mouvements = StockMovement::query()
            ->with('item.category')
            ->where(fn ($q) => $q->whereNull('source_type')
                ->orWhere('source_type', '!=', StockMovement::SOURCE_OPENING));

        return $this->postStockBatch(self::SCHEMA_ECONOMAT_STOCK, $date, 'Mouvements de stock économat',
            $mouvements, $this->economatLines(...));
    }

    private function economatLines(Collection $mouvements): array
    {
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

            // Transféré à un dépôt de l'économat (ou revenu de lui à
            // l'annulation) : même article, même compte de stock, seul le
            // lieu change. La charge naîtra à l'inventaire du dépôt.
            if ($mouvement->source_type === StockMovement::SOURCE_TRANSFER) {
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
            // dépôt, qui révèle ce qui a été consommé. Un classeur importé
            // pour un dépôt y livre de même ce qu'il fait entrer.
            if ($requisition?->service_store_id !== null
                || ($mouvement->source_type === StockMovement::SOURCE_IMPORT && !empty($mouvement
                    ->details['vers_depot']))) {
                continue;
            }

            // Le stock change de magasin, il ne se consomme pas : la charge
            // naîtra à la sortie du garde-manger (coût matière). Une découpe
            // verse elle aussi ses portions au garde-manger.
            if ($requisition?->department === 'restaurant' || $mouvement->source_type === StockMovement::SOURCE_CUT) {
                if ($stock !== Account::STOCK_KITCHEN) {
                    $this->imputer($imputations, Account::STOCK_KITCHEN, 'Transferts vers la cuisine', $valeur, 0);
                    $this->imputer($imputations, $stock, 'Transferts vers la cuisine', 0, $valeur);
                }

                continue;
            }

            $libelle = match (true) {
                $requisition !== null => 'Livraisons aux services',
                $inventaire           => 'Manquants d’inventaire économat',
                $mouvement->source_type === StockMovement::SOURCE_EXTERNAL_ISSUE => 'Sorties hors établissement',
                default               => 'Sorties de stock économat',
            };

            $centre = $requisition !== null
                ? $this->centreDuService($requisition->department)
                : JournalEntryLine::CENTER_STORE;

            $this->imputer($imputations, $variation, $libelle, $valeur, 0, $centre);
            $this->imputer($imputations, $stock, $libelle, 0, $valeur);
        }

        return array_values($imputations);
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
        $mouvements = RestaurantPantryMovement::query()
            ->whereIn('type', [RestaurantPantryMovement::TYPE_IN, RestaurantPantryMovement::TYPE_ADJUST])
            ->whereNull('stock_requisition_id')
            ->where(fn ($q) => $q->whereNull('reason')
                ->orWhere('reason', '!=', RestaurantPantryMovement::REASON_TRANSFER_IN));

        return $this->postStockBatch(self::SCHEMA_PANTRY_STOCK, $date, 'Mouvements du garde-manger',
            $mouvements, $this->pantryLines(...));
    }

    private function pantryLines(Collection $mouvements): array
    {
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

        return array_values($imputations);
    }

    /**
     * Consommation du mini-bar : la vente d'une marchandise, comme à la
     * boutique. Portée au folio, la créance se solde avec le séjour ; relevée
     * après le départ, avec la facture complémentaire.
     *
     *   D 411000  TTC   (auxiliaire : le client)
     *     C 701000  base hors taxes   [hébergement]
     *     C 443100  TVA collectée
     */
    public function postMinibarSale(\App\Models\MinibarCheck $check): ?JournalEntry
    {
        if (!in_array($check->billing, [\App\Models\MinibarCheck::BILLING_FOLIO,
            \App\Models\MinibarCheck::BILLING_A_FACTURER], true)) {
            return null;
        }

        return $this->postSale(
            order: $check,
            ttc: (int) $check->total_amount,
            revenueAccount: Account::REVENUE_MINIBAR,
            center: JournalEntryLine::CENTER_ACCOMMODATION,
            schema: self::SCHEMA_MINIBAR_SALE,
            label: 'Mini-bar — chambre ' . ($check->room?->number ?? '') . ', relevé n° ' . $check->id,
            date: $check->checked_at,
            method: 'room_charge',
            customer: $check->booking?->customer,
        );
    }

    /**
     * Consommations relevées dans les mini-bars de la journée. Les articles y
     * sont entrés depuis le dépôt sans charge : elle naît ici, au coût auquel
     * ils sont entrés dans la chambre.
     *
     *   D 603x [hébergement]   C 3x
     */
    public function postMinibarStock(CarbonInterface $date): ?JournalEntry
    {
        $mouvements = \App\Models\MinibarMovement::query()
            ->with('item.category')
            ->where('type', \App\Models\MinibarMovement::TYPE_CONSUMPTION);

        return $this->postStockBatch(self::SCHEMA_MINIBAR_STOCK, $date, 'Consommations des mini-bars',
            $mouvements, $this->minibarStockLines(...));
    }

    private function minibarStockLines(Collection $mouvements): array
    {
        $imputations = [];
        foreach ($mouvements as $mouvement) {
            $valeur = (int) round(abs($mouvement->quantity) * $mouvement->unit_cost);
            if ($valeur <= 0) {
                continue;
            }

            $stock = $mouvement->stock_account ?: $mouvement->item?->stockAccount() ?? Account::STOCK_STORE;
            $libelle = 'Consommations des mini-bars';
            $this->imputer($imputations, Account::variationFor($stock), $libelle, $valeur, 0,
                JournalEntryLine::CENTER_ACCOMMODATION);
            $this->imputer($imputations, $stock, $libelle, 0, $valeur);
        }

        return array_values($imputations);
    }

    /**
     * Inventaires des dépôts de service clôturés dans la journée, et ce qu'un
     * dépôt remet à un bénéficiaire ou en reçoit en retour.
     *
     * La livraison au dépôt n'a rien coûté au service : c'est ici, quand le
     * comptage révèle ce qui a été consommé, ou quand la marchandise sort
     * vers un bénéficiaire (chantier, artisan), que la charge naît.
     *
     *   Consommation (manquant), sortie   D 603x [centre du service]   C 3x
     *   Excédent, retour en stock         D 3x                         C 603x [centre]
     */
    public function postServiceStoreStock(CarbonInterface $date): ?JournalEntry
    {
        $mouvements = \App\Models\ServiceStoreMovement::query()
            ->with('store', 'item.category')
            ->where(fn ($q) => $q->where('type', \App\Models\ServiceStoreMovement::TYPE_ADJUSTMENT)
                ->orWhereIn('source_type', [
                    \App\Models\ServiceStoreMovement::SOURCE_BENEFICIAIRE,
                    \App\Models\ServiceStoreMovement::SOURCE_RETOUR,
                    \App\Models\ServiceStoreMovement::SOURCE_INTERVENTION,
                    \App\Models\ServiceStoreMovement::SOURCE_BAR_SALE,
                    \App\Models\ServiceStoreMovement::SOURCE_BAR_RETURN,
                    \App\Models\ServiceStoreMovement::SOURCE_BAR_VARIANCE,
                ]));

        return $this->postStockBatch(self::SCHEMA_SERVICE_STORE, $date, 'Consommations des dépôts de service',
            $mouvements, $this->serviceStoreLines(...));
    }

    private function serviceStoreLines(Collection $mouvements): array
    {
        $demandes = \App\Models\MaintenanceRequest::query()
            ->whereKey($mouvements->where('source_type', \App\Models\ServiceStoreMovement::SOURCE_INTERVENTION)
                ->pluck('source_id')->filter()->unique())
            ->pluck('department', 'id');

        $imputations = [];

        foreach ($mouvements as $mouvement) {
            $variationStock = (int) round((float) $mouvement->quantity * $mouvement->unit_cost);
            if (in_array($mouvement->source_type, \App\Models\ServiceStoreMovement::BAR_SOURCES, true)) {
                $variationStock = $mouvement->details['value_delta'];
            }
            $valeur = abs($variationStock);

            if ($valeur <= 0 || $mouvement->store === null) {
                continue;
            }

            $stock = $mouvement->stock_account ?: $mouvement->item?->stockAccount() ?? Account::STOCK_STORE;
            $variation = Account::variationFor($stock);
            // Une pièce employée par une intervention coûte au service où l'on
            // a réparé : une chambre à l'hébergement, la cuisine au restaurant.
            $centre = $this->centreDuService(
                $mouvement->source_type === \App\Models\ServiceStoreMovement::SOURCE_INTERVENTION
                    ? ($demandes[$mouvement->source_id] ?? $mouvement->store->department)
                    : $mouvement->store->department
            );

            $nature = match ($mouvement->source_type) {
                \App\Models\ServiceStoreMovement::SOURCE_BENEFICIAIRE => 'Sorties à des bénéficiaires',
                \App\Models\ServiceStoreMovement::SOURCE_RETOUR       => 'Retours en stock',
                \App\Models\ServiceStoreMovement::SOURCE_INTERVENTION => 'Pièces des interventions',
                \App\Models\ServiceStoreMovement::SOURCE_BAR_SALE => 'Consommations au bar',
                \App\Models\ServiceStoreMovement::SOURCE_BAR_RETURN => 'Annulations avant préparation au bar',
                \App\Models\ServiceStoreMovement::SOURCE_BAR_COUNT => 'Écarts sur bouteilles ouvertes',
                \App\Models\ServiceStoreMovement::SOURCE_BAR_VARIANCE => 'Attributions de volume au bar',
                default                                                 => null,
            };

            if ($variationStock < 0) {
                $libelle = ($nature ?? 'Consommation') . " — {$mouvement->store->name}";
                $this->imputer($imputations, $variation, $libelle, $valeur, 0, $centre);
                $this->imputer($imputations, $stock, $libelle, 0, $valeur);
            } else {
                $libelle = ($nature ?? 'Excédent d’inventaire') . " — {$mouvement->store->name}";
                $this->imputer($imputations, $stock, $libelle, $valeur, 0);
                $this->imputer($imputations, $variation, $libelle, 0, $valeur, $centre);
            }
        }

        return array_values($imputations);
    }

    /**
     * Un lot quotidien peut recevoir des compléments jusqu'à la clôture.
     * On conserve les mouvements déjà traités, y compris ceux sans incidence
     * comptable. Les lots de l'ancien format n'avaient pas cette provenance.
     */
    private function postStockBatch(string $stream, CarbonInterface $date, string $label, Builder $query,
        callable $buildLines): ?JournalEntry
    {
        $start = $date->copy()->startOfDay();
        $end = $date->copy()->endOfDay();
        $schema = $stream . ':' . $start->toDateString();

        return DB::transaction(function () use ($start, $end, $schema, $stream, $label, $query, $buildLines) {
            $this->ledger->lockForDate($start);
            $initial = JournalEntry::query()->where('schema', $schema)->first();
            $legacy = $initial && !DB::table('ledger_stock_movements')->where('journal_entry_id', $initial->id)->exists();
            $mouvements = $query
                ->whereBetween('occurred_at', [$start, $end])
                ->when($legacy, fn ($q) => $q->where('created_at', '>', $initial->created_at))
                ->whereNotIn('id', DB::table('ledger_stock_movements')->select('movement_id')->where('stream', $stream))
                ->orderBy('id')
                ->get();

            if ($mouvements->isEmpty()) {
                return null;
            }

            $lines = $buildLines($mouvements);
            $entry = $lines === [] ? null : $this->ledger->post(
                journalCode: Journal::MISC,
                date: $start,
                label: $label . ' du ' . $start->format('d/m/Y'),
                lines: $lines,
                schema: $initial ? $schema . ':' . $mouvements->first()->id : $schema,
            );

            foreach ($mouvements->pluck('id')->chunk(250) as $ids) {
                DB::table('ledger_stock_movements')->insert($ids->map(fn ($id) => [
                    'stream' => $stream,
                    'movement_id' => $id,
                    'journal_entry_id' => $entry?->id,
                ])->all());
            }

            return $entry;
        });
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
    public function postStockReclassification(string $from, string $to, int $amount, string $label,
        CarbonInterface $date): ?JournalEntry
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
        ?int $recordedVat = null,
    ): ?JournalEntry {
        if ($this->dejaComptabilise($order, $schema)) {
            return null;
        }

        if ($ttc <= 0) {
            return null;
        }

        $snapshot = $order->vat_snapshot ? json_decode($order->vat_snapshot, true, flags: JSON_THROW_ON_ERROR) : null;
        if ($snapshot && (int) $snapshot['total'] !== $ttc) {
            throw new \RuntimeException('Le montant de la vente diffère de sa ventilation fiscale initiale. Vérifiez la pièce et sa régularisation.');
        }
        $vat = $snapshot['vat'] ?? $recordedVat ?? $this->taxation->breakdown($ttc)->vat;
        $surtax = $snapshot['surtax'] ?? 0;
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
            'credit'  => $ttc - $vat - $surtax,
            'center'  => $center,
        ]];

        if ($vat > 0) {
            $lignes[] = [
                'account' => Account::VAT_COLLECTED,
                'label'   => 'TVA collectée',
                'credit'  => $vat,
            ];
        }
        if ($surtax > 0) {
            $lignes[] = ['account' => Account::VAT_SURTAX, 'label' => 'Centimes additionnels communaux', 'credit' => $surtax];
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
    private function imputer(array &$imputations, string $compte, string $libelle, int $debit, int $credit,
        ?string $centre = null): void
    {
        $cle = implode('|', [$compte, $debit > 0 ? 'D' : 'C', $libelle, $centre]);

        $imputations[$cle] ??= ['account' => $compte, 'label' => $libelle, 'debit' => 0, 'credit' => 0,
            'center' => $centre];
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
            'bar', 'conferences', 'spa', 'laundry', 'activity' => $service,
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
            'mobile_money',
            'mtn_momo'             => '531000',
            'bank_transfer',
            'transfer',
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
