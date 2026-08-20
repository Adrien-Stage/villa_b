<?php

use App\Models\Account;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Payment;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\ShopOrder;
use App\Models\Tenant;
use App\Services\LedgerPostingService;
use App\Services\LedgerReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

function posting(): LedgerPostingService
{
    return app(LedgerPostingService::class);
}

/** Établissement avec TVA activée : les schémas doivent la ventiler. */
function etablissementAvecTva(): Tenant
{
    return Tenant::create([
        'name'     => 'Hôtel de test',
        'slug'     => 'hotel-test',
        'currency' => 'XAF',
        'settings' => ['taxes' => ['vat_enabled' => true]],
    ]);
}

function clientTest(): Customer
{
    return Customer::create(['first_name' => 'Awa', 'last_name' => 'Njoya']);
}

/** Opérateur : plusieurs tables exigent un created_by. */
function operateur(): \App\Models\User
{
    return \App\Models\User::firstOrCreate(
        ['email' => 'operateur@test.cm'],
        ['name' => 'Opérateur', 'password' => bcrypt('x'), 'role' => 'reception', 'is_active' => true]
    );
}

/** Paiement complet : booking_id et référence unique sont obligatoires. */
function paiement(int $bookingId, int $montant, string $methode, string $quand): Payment
{
    return Payment::create([
        'booking_id' => $bookingId,
        'amount'     => $montant,
        'method'     => $methode,
        'status'     => 'completed',
        'paid_at'    => Carbon::parse($quand),
        'reference'  => 'PAY-' . str_pad((string) (Payment::count() + 1), 4, '0', STR_PAD_LEFT),
    ]);
}

/** Vente boutique complète : order_number unique et created_by obligatoires. */
function venteBoutique(int $ttc, string $methode, string $quand): ShopOrder
{
    return ShopOrder::create([
        'order_number'   => 'SH-' . str_pad((string) (ShopOrder::count() + 1), 4, '0', STR_PAD_LEFT),
        'created_by'     => operateur()->id,
        'total_items'    => 1,
        'subtotal'       => $ttc,
        'tax_amount'     => 0,
        'total_amount'   => $ttc,
        'payment_status' => 'paid',
        'payment_method' => $methode,
        'paid_at'        => Carbon::parse($quand),
    ]);
}

function sejourFacture(int $ttc = 1_192_500, int $extras = 0): Invoice
{
    $client = clientTest();

    // Le code du type est unique : on le dérive du compteur pour que
    // plusieurs séjours puissent cohabiter dans un même test.
    $suffixe = RoomType::count() + 1;

    $type = RoomType::create([
        'name'          => "Standard {$suffixe}",
        'code'          => "STD{$suffixe}",
        'base_capacity' => 2,
        'max_capacity'  => 3,
        'base_price'    => $ttc,
        'is_active'     => true,
    ]);

    $room = Room::create([
        'room_type_id' => $type->id,
        'number'       => (string) (100 + $suffixe),
        'status'       => 'available',
        'is_active'    => true,
    ]);

    $booking = Booking::create([
        'room_id'           => $room->id,
        'customer_id'       => $client->id,
        'status'            => 'checked_out',
        'check_in'          => '2026-06-14',
        'check_out'         => '2026-06-15',
        'adults_count'      => 2,
        'total_nights'      => 1,
        'price_per_night'   => $ttc,
        'total_room_amount' => $ttc,
        'extras_amount'     => $extras,
        'total_amount'      => $ttc + $extras,
        'paid_amount'       => 0,
        'balance_due'       => $ttc + $extras,
    ]);

    return Invoice::create([
        'booking_id'     => $booking->id,
        'customer_id'    => $client->id,
        'invoice_number' => 'F-2026-000001',
        'invoice_date'   => '2026-06-15',
        'subtotal'       => $ttc + $extras,
        'tax_amount'     => 0,
        'total_amount'   => $ttc + $extras,
        'paid_amount'    => 0,
        'balance_due'    => $ttc + $extras,
        'status'         => 'sent',
    ]);
}

// ── Facture d'hébergement ───────────────────────────────────────────────────

test('la facture crée la créance client et ventile la TVA', function () {
    etablissementAvecTva();
    $facture = sejourFacture();

    $entry = posting()->postInvoice($facture);

    expect($entry)->not->toBeNull();
    expect($entry->isBalanced())->toBeTrue();

    $client = $entry->lines->firstWhere('account_code', Account::CLIENTS);
    $produit = $entry->lines->firstWhere('account_code', Account::REVENUE_ACCOMMODATION);
    $tva = $entry->lines->firstWhere('account_code', Account::VAT_COLLECTED);

    expect($client->debit)->toBe(1_192_500);
    expect($produit->credit)->toBe(1_000_000);
    expect($tva->credit)->toBe(192_500);

    // Le produit porte son centre d'analyse : la marge par activité en dépend.
    expect($produit->analytic_center)->toBe(JournalEntryLine::CENTER_ACCOMMODATION);

    // L'auxiliaire désigne le client ; le compte reste le collectif.
    expect($client->auxiliary_type)->toBe(Customer::class);
});

test('sans TVA activée, la facture ne ventile aucune taxe', function () {
    Tenant::create(['name' => 'Hôtel', 'slug' => 'h', 'currency' => 'XAF', 'settings' => []]);
    $facture = sejourFacture();

    $entry = posting()->postInvoice($facture);

    expect($entry->lines)->toHaveCount(2);
    expect($entry->lines->firstWhere('account_code', Account::REVENUE_ACCOMMODATION)->credit)->toBe(1_192_500);
});

test('les extras du folio sont exclus de la facture d’hébergement', function () {
    etablissementAvecTva();
    // 11 925 F d'hébergement + 5 000 F d'extras déjà comptabilisés ailleurs.
    $facture = sejourFacture(ttc: 1_192_500, extras: 500_000);

    $entry = posting()->postInvoice($facture);

    // Seul l'hébergement est porté : compter les extras ici les doublerait.
    expect($entry->lines->firstWhere('account_code', Account::CLIENTS)->debit)->toBe(1_192_500);
});

// ── Ventes ──────────────────────────────────────────────────────────────────

test('une vente boutique réglée en espèces débite la caisse', function () {
    etablissementAvecTva();

    $order = venteBoutique(1_192_500, 'cash', '2026-06-15 12:00');

    $entry = posting()->postShopSale($order);

    expect($entry->isBalanced())->toBeTrue();
    expect($entry->lines->firstWhere('account_code', Account::CASH)->debit)->toBe(1_192_500);
    expect($entry->lines->firstWhere('account_code', Account::REVENUE_SHOP)->credit)->toBe(1_000_000);
    expect($entry->journal->code)->toBe('CA');
});

test('une vente portée au folio débite le client, pas la caisse', function () {
    etablissementAvecTva();

    $order = venteBoutique(1_192_500, 'room_charge', '2026-06-15 12:00');

    $entry = posting()->postShopSale($order);

    // C'est tout l'enjeu de l'anti-double-comptage : aucune trésorerie ne
    // bouge, la créance client grossit et sera soldée avec le séjour.
    expect($entry->lines->firstWhere('account_code', Account::CLIENTS)->debit)->toBe(1_192_500);
    expect($entry->lines->firstWhere('account_code', Account::CASH))->toBeNull();
    expect($entry->journal->code)->toBe('VT');
});

// ── Encaissements ───────────────────────────────────────────────────────────

test('un encaissement solde la créance client', function () {
    etablissementAvecTva();
    $facture = sejourFacture();

    $payment = paiement($facture->booking_id, 1_192_500, 'cash', '2026-06-15 18:00');

    $entry = posting()->postPayment($payment);

    expect($entry->lines->firstWhere('account_code', Account::CASH)->debit)->toBe(1_192_500);
    expect($entry->lines->firstWhere('account_code', Account::CLIENTS)->credit)->toBe(1_192_500);
});

test('un remboursement inverse les sens', function () {
    etablissementAvecTva();
    $facture = sejourFacture();

    $payment = paiement($facture->booking_id, -500_000, 'cash', '2026-06-15 18:00');

    $entry = posting()->postPayment($payment);

    expect($entry->lines->firstWhere('account_code', Account::CASH)->credit)->toBe(500_000);
    expect($entry->lines->firstWhere('account_code', Account::CLIENTS)->debit)->toBe(500_000);
});

test('le moyen de paiement choisit le compte de trésorerie', function () {
    etablissementAvecTva();
    $facture = sejourFacture();

    $mobile   = paiement($facture->booking_id, 100_000, 'orange_money', '2026-06-15 10:00');
    $virement = paiement($facture->booking_id, 100_000, 'bank_transfer', '2026-06-15 11:00');

    expect(posting()->postPayment($mobile)->lines->pluck('account_code'))->toContain('531000');
    expect(posting()->postPayment($virement)->lines->pluck('account_code'))->toContain(Account::BANK);
});

// ── Dépenses ────────────────────────────────────────────────────────────────

test('une dépense débite sa charge et crédite la trésorerie', function () {
    etablissementAvecTva();

    $depense = Expense::create([
        'occurred_at'    => Carbon::parse('2026-06-15'),
        'category'       => Expense::CATEGORY_ELECTRICITY,
        'label'          => 'Facture ENEO juin',
        'amount'         => 750_000,
        'payment_method' => 'bank_transfer',
    ]);

    $entry = posting()->postExpense($depense);

    expect($entry->lines->firstWhere('account_code', '605000')->debit)->toBe(750_000);
    expect($entry->lines->firstWhere('account_code', Account::BANK)->credit)->toBe(750_000);
});

// ── Idempotence ─────────────────────────────────────────────────────────────

test('rejouer une journée ne double aucune écriture', function () {
    etablissementAvecTva();
    sejourFacture();

    Expense::create([
        'occurred_at' => Carbon::parse('2026-06-15'), 'category' => 'water',
        'label' => 'Camwater', 'amount' => 200_000, 'payment_method' => 'cash',
    ]);

    $premier = posting()->postDay(Carbon::parse('2026-06-15'));
    $apres = JournalEntry::count();

    $second = posting()->postDay(Carbon::parse('2026-06-15'));

    expect(array_sum($premier))->toBe(2);
    expect(array_sum($second))->toBe(0);
    expect(JournalEntry::count())->toBe($apres);
});

// ── Cohérence d'ensemble ────────────────────────────────────────────────────

test('une journée complète produit un grand livre équilibré', function () {
    etablissementAvecTva();
    $facture = sejourFacture();

    paiement($facture->booking_id, 1_192_500, 'cash', '2026-06-15 20:00');
    venteBoutique(300_000, 'cash', '2026-06-15 14:00');

    posting()->postDay(Carbon::parse('2026-06-15'));

    $reports = app(LedgerReportService::class);
    $balance = $reports->balance(Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30'));
    $totaux = $reports->balanceTotals($balance);

    expect($totaux['balanced'])->toBeTrue();

    // La créance née de la facture est soldée par l'encaissement.
    $client = $balance->firstWhere('code', Account::CLIENTS);
    expect($client['balance'])->toBe(0);
});

test('la comptabilisation s’arrête sur une période verrouillée', function () {
    etablissementAvecTva();
    sejourFacture();

    // On verrouille juin après avoir comptabilisé.
    posting()->postDay(Carbon::parse('2026-06-15'));
    $periode = \App\Models\FiscalPeriod::forDate(Carbon::parse('2026-06-15'));
    app(\App\Services\LedgerService::class)->lockPeriod($periode);

    // Une opération arrivée après coup ne peut plus être portée sur juin.
    Expense::create([
        'occurred_at' => Carbon::parse('2026-06-20'), 'category' => 'other',
        'label' => 'Retardataire', 'amount' => 100_000, 'payment_method' => 'cash',
    ]);

    posting()->postDay(Carbon::parse('2026-06-20'));
})->throws(RuntimeException::class, 'verrouillée');
