<?php

use App\Models\Account;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AuxiliaryLedgerService;
use App\Services\BankReconciliation;
use App\Services\DsfWorkbook;
use App\Services\FiscalArchiveService;
use App\Services\FixedAssetRegister;
use App\Services\InvoiceFiscalRegister;
use App\Services\LedgerImportService;
use App\Services\LedgerPostingService;
use App\Services\LedgerService;
use App\Services\NightAuditService;
use App\Services\PaymentAccounting;
use App\Services\ReconciliationService;
use App\Services\SupplierInvoiceService;
use App\Services\SupplierSettlement;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-11-20 10:00'));
    activerModules(['ledger']);
});

function auditCapital(string $date = '2026-11-18', int $amount = 100000): JournalEntry
{
    return app(LedgerService::class)->post(Journal::BANK, Carbon::parse($date), 'Capital apporté', [
        ['account' => Account::BANK, 'debit' => $amount],
        ['account' => '101000', 'credit' => $amount],
    ], reference: 'CAP-001');
}

function auditCsv(array $header, string $rows): UploadedFile
{
    return UploadedFile::fake()->createWithContent('piece.csv', implode(';', $header) . "\n" . $rows);
}

function auditAccountant(): User
{
    return User::factory()->create(['role' => 'accountant']);
}

test('la base empêche la mutation et la suppression d’une écriture validée', function () {
    $this->actingAs(auditAccountant());
    $entry = auditCapital();
    $line = $entry->lines->first();
    expect($entry->validated_by)->toBe(auth()->id());

    foreach ([
        fn () => DB::table('journal_entries')->where('id', $entry->id)->update(['posted_at' => null]),
        fn () => DB::table('journal_entries')->where('id', $entry->id)->delete(),
        fn () => DB::table('journal_entry_lines')->where('id', $line->id)->update(['debit' => 1]),
        fn () => DB::table('journal_entry_lines')->where('id', $line->id)->delete(),
        fn () => DB::table('journal_entry_lines')->insert([
            'journal_entry_id' => $entry->id, 'account_code' => Account::BANK, 'debit' => 1,
        ]),
    ] as $attempt) {
        expect(fn () => DB::transaction($attempt))->toThrow(QueryException::class);
    }
    expect($entry->fresh('lines')->totalDebit())->toBe(100000);
});

test('une écriture déséquilibrée ne peut pas être validée hors du service', function () {
    $entry = app(LedgerService::class)->post(Journal::MISC, now(), 'Brouillon', [
        ['account' => Account::BANK, 'debit' => 900],
        ['account' => '101000', 'credit' => 900],
    ], autoPost: false);
    $entry->lines->first()->update(['debit' => 100]);

    expect(fn () => DB::transaction(fn () => $entry->update(['posted_at' => now()])))
        ->toThrow(QueryException::class);
    expect($entry->fresh()->isPosted())->toBeFalse();
});

test('une journée close refuse l’extourne et une correction ouverte conserve la date d’origine', function () {
    $entry = auditCapital();
    $night = Carbon::parse('2026-11-19');
    app(NightAuditService::class)->run($night);
    $ledger = app(LedgerService::class);

    expect(fn () => $ledger->reverse($entry, $night, 'Correction test'))->toThrow(RuntimeException::class, 'est clôturée');
    $reversal = $ledger->reverse($entry, now(), 'Correction test');

    expect($reversal->value_date->toDateString())->toBe('2026-11-18')
        ->and($reversal->reverses_id)->toBe($entry->id)
        ->and($entry->fresh()->reversed_by_id)->toBe($reversal->id);
    expect(fn () => $ledger->reverse($entry, now(), 'Second essai'))->toThrow(RuntimeException::class, 'déjà été');
});

test('un délettrage conserve la situation des créances à la date d’arrêté', function () {
    $client = \App\Models\Customer::factory()->create();
    $ledger = app(LedgerService::class);
    $facture = $ledger->post(Journal::SALES, Carbon::parse('2026-08-01'), 'Facture client', [
        ['account' => Account::CLIENTS, 'debit' => 200000, 'auxiliary' => $client],
        ['account' => Account::REVENUE_ACCOMMODATION, 'credit' => 200000],
    ]);
    $reglement = $ledger->post(Journal::BANK, Carbon::parse('2026-10-06'), 'Virement client', [
        ['account' => Account::CLIENTS, 'credit' => 200000, 'auxiliary' => $client],
        ['account' => Account::BANK, 'debit' => 200000],
    ]);
    $this->travelTo(Carbon::parse('2026-10-08 10:00'));
    $reconciliation = app(ReconciliationService::class);
    $code = $reconciliation->reconcile([$facture->lines->first()->id, $reglement->lines->first()->id]);
    $this->travelTo(Carbon::parse('2026-11-20 10:00'));
    $reconciliation->unreconcile($code);
    $reports = app(AuxiliaryLedgerService::class);

    expect($reports->agedBalance(Account::CLIENTS, Carbon::parse('2026-09-30'))['rows']->first()['total'])->toBe(200000)
        ->and($reports->agedBalanceByMonth(Carbon::parse('2026-10-31')))->toBeEmpty();
    expect(DB::table('ledger_reconciliation_events')->count())->toBe(4);
});

test('le règlement partiel d’un fournisseur est daté, plafonné et disponible au rejeu', function () {
    $supplier = Supplier::create(['name' => 'Fournisseur test', 'is_active' => true]);
    $invoice = app(SupplierInvoiceService::class)->record([
        'supplier' => $supplier, 'number' => 'FAC-001', 'invoice_date' => Carbon::parse('2026-11-18'),
        'charge_account' => '601000', 'label' => 'Achats', 'amount_ttc' => 500000,
    ]);
    $settlement = app(SupplierSettlement::class);
    $date = Carbon::parse('2026-11-19');
    $first = $settlement->pay($invoice, $date, 200000, 'bank_transfer', 'VIR-001');

    expect($settlement->pay($invoice, $date, 200000, 'bank_transfer', 'VIR-001')->id)->toBe($first->id)
        ->and($settlement->balance($invoice, $date))->toBe(300000)
        ->and($settlement->balance($invoice, $date->copy()->subDay()))->toBe(500000);
    expect(fn () => $settlement->pay($invoice, $date, 300001, 'bank_transfer', 'VIR-002'))
        ->toThrow(RuntimeException::class, 'dépasse le solde');
    $advance = $settlement->advance($supplier, Carbon::parse('2026-11-17'), 100000, 'bank_transfer', 'AV-001');
    expect($advance->lines->firstWhere('account_code', Account::SUPPLIER_ADVANCES)->debit)->toBe(100000);
    $settlement->pay($invoice, $date, 80000, 'advance', 'AFFECT-001');
    expect($settlement->availableAdvance($supplier, $date))->toBe(20000)
        ->and($settlement->balance($invoice, $date))->toBe(220000);
    expect(fn () => $settlement->pay($invoice, $date, 20001, 'advance', 'AFFECT-002'))
        ->toThrow(RuntimeException::class, 'avance suffisante');
});

test('un relevé est importé une fois et le pointage exige un montant et un compte concordants', function () {
    $entry = auditCapital();
    $bank = app(BankReconciliation::class);
    $statement = $bank->import(auditCsv(BankReconciliation::HEADER, "2026-11-18;CAP-001;100000;Capital\n"),
        Account::BANK, 'BNQ-11');
    $actual = $bank->import(auditCsv(BankReconciliation::HEADER, "2026-11-18;CAP-001;100000;Capital\n"),
        Account::BANK, 'BNQ-11');
    expect($actual->id)->toBe($statement->id);
    $line = $statement->lines()->firstOrFail();

    expect(fn () => $bank->setMatch($line, $entry->lines->last()->id))->toThrow(RuntimeException::class);
    expect($bank->candidates($line)->modelKeys())->toBe([$entry->lines->first()->id]);
    $bank->setMatch($line, $entry->lines->first()->id);
    expect($line->fresh()->journal_entry_line_id)->toBe($entry->lines->first()->id);
    $bank->setMatch($line, null);
    expect($line->fresh()->journal_entry_line_id)->toBeNull();
    expect(DB::table('bank_reconciliation_events')->count())->toBe(2);
});

test('un lot de paie équilibré est rejouable et les pièces recopiées dans un autre lot sont refusées', function () {
    $service = app(LedgerImportService::class);
    $rows = "2026-11-20;PAIE-11;661000;Salaires;300000;0;2026-11-19;restaurant\n"
        . "2026-11-20;PAIE-11;421000;Net à payer;0;300000;2026-11-19;\n";
    $import = $service->import(auditCsv(LedgerImportService::HEADER, $rows), 'payroll', 'Lot novembre');
    expect($service->import(auditCsv(LedgerImportService::HEADER, $rows), 'payroll', 'Lot novembre')->id)->toBe($import->id);
    expect(fn () => $service->import(auditCsv(LedgerImportService::HEADER, str_replace('Salaires', 'Paie', $rows)), 'payroll', 'Autre lot'))
        ->toThrow(RuntimeException::class, 'déjà importée');
    expect(JournalEntry::sole()->value_date->toDateString())->toBe('2026-11-19');
});

test('l’archive annuelle fige les livres et permet de renseigner un modèle DSF sans écraser ses formules', function () {
    auditCapital('2025-12-31');
    $exercise = FiscalYear::forDate(Carbon::parse('2025-12-31'));
    $archives = app(FiscalArchiveService::class);
    $proof = UploadedFile::fake()->createWithContent('inventaire.txt', 'Inventaire vérifié');
    expect(fn () => $archives->close($exercise, 'normal', 'Méthodes et état des soldes.', [$proof], []))
        ->toThrow(RuntimeException::class, 'douze périodes');
    foreach ($exercise->periods as $period) {
        app(LedgerService::class)->lockPeriod($period);
    }
    $archive = $archives->close($exercise, 'normal', 'Méthodes et état des soldes.', [$proof], []);
    $payload = $archives->read($archive);
    expect($archive->retain_until->toDateString())->toBe('2035-12-31')
        ->and($exercise->fresh()->isClosed())->toBeTrue()
        ->and(count($payload['ledger']))->toBe(2)
        ->and($payload['attachments'][0]['name'])->toBe('inventaire.txt');
    $this->artisan('ledger:verify-archive', ['year' => 2025])->assertSuccessful();

    $template = new Spreadsheet();
    $template->getActiveSheet()->setTitle('Bilan')->setCellValue('B2', '=SUM(B1:B1)');
    $file = UploadedFile::fake()->create('DSF.xlsx');
    (new Xlsx($template))->save($file->getPathname());
    $filled = app(DsfWorkbook::class)->fill($archive, $file,
        auditCsv(DsfWorkbook::HEADER, "Bilan;B1;52;closing\n"));
    expect($filled->getActiveSheet()->getCell('B1')->getValue())->toBe(1000)
        ->and($filled->getActiveSheet()->getCell('B2')->getValue())->toBe('=SUM(B1:B1)');
    expect(fn () => app(DsfWorkbook::class)->fill($archive, $file, auditCsv(DsfWorkbook::HEADER, "Bilan;B2;52;closing\n")))
        ->toThrow(RuntimeException::class, 'formule');
});

test('le registre d’actifs lie l’acquisition et comptabilise une seule dotation à la date demandée', function () {
    $ledger = app(LedgerService::class);
    $purchase = $ledger->post(Journal::BANK, Carbon::parse('2026-11-01'), 'Achat équipement', [
        ['account' => '244000', 'debit' => 1200000],
        ['account' => Account::BANK, 'credit' => 1200000],
    ], reference: 'IMMO-01');
    $service = app(FixedAssetRegister::class);
    $asset = $service->register([
        'reference' => 'MAT-01', 'label' => 'Matériel', 'journal_entry_line_id' => $purchase->lines->first()->id,
        'in_service_on' => '2026-11-01', 'useful_months' => 12, 'residual_amount' => 0,
        'depreciation_account' => '284000', 'charge_account' => '681200',
    ]);
    $date = Carbon::parse('2026-11-19');
    $first = $service->depreciation($asset, $date);
    expect($first->totalDebit())->toBe((int) round(1200000 * (19 / 365)))
        ->and($service->depreciation($asset, $date)->id)->toBe($first->id);
    $disposed = $service->dispose($asset, now(), 'CESSION-01', 350000, 0, false);
    expect($disposed->isBalanced())->toBeTrue()->and($asset->fresh()->disposal_entry_id)->toBe($disposed->id);
});

test('le suivi DGI conserve le fichier préparé et l’accusé renseigné par le comptable', function () {
    $this->seed([\Database\Seeders\TenantSeeder::class, \Database\Seeders\RoomTypeSeeder::class, \Database\Seeders\RoomSeeder::class]);
    $tenant = Tenant::current();
    $tenant->update(['settings' => ['taxes' => ['issuer_niu' => 'NIU-TEST']]]);
    $customer = \App\Models\Customer::factory()->create();
    $booking = \App\Models\Booking::create([
        'customer_id' => $customer->id, 'room_id' => \App\Models\Room::query()->firstOrFail()->id,
        'check_in' => today()->subDay(), 'check_out' => today(),
        'total_nights' => 1, 'price_per_night' => 5000, 'total_room_amount' => 5000, 'total_amount' => 5000,
    ]);
    $invoice = Invoice::create([
        'booking_id' => $booking->id, 'customer_id' => $customer->id,
        'invoice_number' => 'FACT-TEST', 'invoice_date' => today(), 'subtotal' => 5000, 'tax_amount' => 0,
        'total_amount' => 5000, 'paid_amount' => 5000, 'balance_due' => 0, 'status' => 'paid',
    ]);
    $invoice->items()->create([
        'description' => 'Service', 'quantity' => 1, 'unit_price' => 5000, 'total_price' => 5000, 'category' => 'room',
    ]);
    $register = app(InvoiceFiscalRegister::class);
    $prepared = $register->prepare($invoice);
    expect(json_decode($register->read($prepared), true)['issuer']['niu'])->toBe('NIU-TEST');
    $proof = UploadedFile::fake()->createWithContent('reponse.txt', 'Réponse fictive du test');
    $accepted = $register->recordResponse($invoice, 'accepted', 'TEST-ACC-12', $proof, null);
    expect($register->read($accepted))->toBe('Réponse fictive du test');
    expect(fn () => $register->prepare($invoice))->toThrow(RuntimeException::class, 'accusé');
});

test('le change d’origine est rapproché et un versement opérateur isole ses commissions', function () {
    $payment = Payment::create([
        'reference' => 'PAY-TEST', 'amount' => 100000, 'currency' => 'XAF', 'status' => 'completed',
        'method' => 'orange_money', 'paid_at' => Carbon::parse('2026-11-19'),
    ]);
    app(LedgerPostingService::class)->postPayment($payment);
    $service = app(PaymentAccounting::class);
    $service->recordExchange($payment, [
        'currency' => 'EUR', 'original_minor' => 200, 'minor_digits' => 2, 'xaf_rate' => '500.00',
        'rate_on' => '2026-11-19', 'reference' => 'TAUX-TEST',
    ]);
    $entry = $service->settle($payment, now(), 98000, 'REV-001');
    expect($entry->lines->firstWhere('account_code', '631000')->debit)->toBe(2000)
        ->and($entry->lines->firstWhere('account_code', '531000')->credit)->toBe(100000)
        ->and($service->settle($payment, now(), 98000, 'REV-001')->id)->toBe($entry->id);
});

test('la vente du POS réception est comptabilisée avec sa TVA d’origine, une seule fois côté caisse', function () {
    $tenant = Tenant::firstOrCreate(['id' => 1], ['name' => 'Hôtel test', 'slug' => 'audit-pos']);
    $tenant->update(['settings' => ['taxes' => ['vat_enabled' => true]]]);
    $payment = Payment::create([
        'reference' => 'POS-PAY-123', 'amount' => 119250, 'currency' => 'XAF', 'status' => 'completed',
        'method' => 'cash', 'paid_at' => now(), 'payment_origin' => Payment::ORIGIN_POS_RECEPTION,
    ]);
    $sale = \App\Models\ReceptionSale::create([
        'user_id' => auditAccountant()->id, 'sale_number' => 'POS-123',
        'customer_name' => 'Client de passage', 'payment_id' => $payment->id, 'payment_type' => 'immediate',
        'payment_method' => 'cash', 'payment_status' => 'paid', 'paid_at' => now(),
        'subtotal' => 119250, 'tax_amount' => 19250, 'total_amount' => 119250,
    ]);
    $tenant->update(['settings' => ['taxes' => ['vat_enabled' => false]]]);
    $posting = app(LedgerPostingService::class);
    $posting->postDay(now());
    $entry = JournalEntry::query()->where('source_type', $sale::class)->where('source_id', $sale->id)->sole();

    expect($entry->lines->firstWhere('account_code', Account::CASH)->debit)->toBe(119250)
        ->and($entry->lines->firstWhere('account_code', Account::VAT_COLLECTED)->credit)->toBe(17500)
        ->and($entry->lines->firstWhere('account_code', Account::VAT_SURTAX)->credit)->toBe(1750);
    $posting->postDay(now());
    expect(JournalEntry::count())->toBe(1);
});

test('la facture d’hôtel porte en dette la taxe de séjour et conserve les taux et exonérations par ligne', function () {
    $this->seed([\Database\Seeders\TenantSeeder::class, \Database\Seeders\RoomTypeSeeder::class, \Database\Seeders\RoomSeeder::class]);
    Tenant::current()->update(['settings' => ['taxes' => ['vat_enabled' => true, 'tourist_tax_enabled' => true, 'classification' => '3']]]);
    $customer = \App\Models\Customer::factory()->create();
    $room = \App\Models\Room::query()->firstOrFail();
    $booking = \App\Models\Booking::create([
        'room_id' => $room->id, 'customer_id' => $customer->id,
        'check_in' => today()->subDay(), 'actual_check_in' => today()->subDay()->setHour(14), 'check_out' => today(),
        'status' => \App\Enums\BookingStatus::CHECKED_IN, 'adults_count' => 2, 'children_count' => 0,
        'price_per_night' => 1192500, 'total_room_amount' => 1192500, 'total_nights' => 1,
        'extras_amount' => 100000, 'tourist_tax_amount' => 600000, 'total_amount' => 1892500,
        'paid_amount' => 1892500, 'balance_due' => 0,
    ]);
    $booking->folioItems()->create([
        'customer_id' => $customer->id, 'type' => 'other', 'description' => 'Prestation avec justificatif fixture',
        'quantity' => 1, 'unit_price' => 100000, 'total_price' => 100000, 'occurred_at' => now(),
        'vat_rate_code' => 'EXONERE', 'vat_reason' => 'Pièce TEST-EXO',
    ]);
    Payment::create([
        'booking_id' => $booking->id, 'customer_id' => $customer->id, 'reference' => 'PAY-SEJOUR-TEST',
        'amount' => 1892500, 'currency' => 'XAF', 'status' => 'completed', 'method' => 'cash', 'paid_at' => now(),
    ]);
    $invoice = app(\App\Services\CheckOutService::class)->process($booking);
    $entry = app(LedgerPostingService::class)->postInvoice($invoice);

    expect($invoice->tourist_tax_amount)->toBe(600000)
        ->and($invoice->tax_amount)->toBe(192500)
        ->and($invoice->items->firstWhere('tax_rate_code', 'EXONERE')->vat_reason)->toBe('Pièce TEST-EXO')
        ->and($entry->lines->firstWhere('account_code', Account::TOURIST_TAX)->credit)->toBe(600000)
        ->and($entry->lines->firstWhere('account_code', Account::VAT_SURTAX)->credit)->toBe(17500)
        ->and($entry->totalDebit())->toBe(1892500);
});

test('le comptable ouvre les nouveaux écrans et exporte les livres sans mise en cache', function () {
    auditCapital();
    $this->actingAs(auditAccountant());
    foreach (['statutory.index', 'exchange.index', 'assets.index', 'fiscal.index'] as $route) {
        $this->get(route('accounting.ledger.' . $route))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    }
    $pdf = $this->get(route('accounting.ledger.statutory.export', [
        'kind' => 'books', 'format' => 'pdf', 'from' => '2026-11-01', 'to' => '2026-11-30',
    ]))->assertOk();
    expect($pdf->getContent())->toStartWith('%PDF');
    $this->actingAs(User::factory()->create(['role' => 'reception']))->getJson(route('accounting.ledger.exchange.index'))->assertForbidden();
});
