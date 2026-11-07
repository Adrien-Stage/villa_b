<?php

use App\Editions\Registres;
use App\Enums\BookingStatus;
use App\Models\Account;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PartnerOrganization;
use App\Models\Payment;
use App\Models\Room;
use App\Models\User;
use App\Models\WalletEntry;
use App\Models\WalletEntryAttachment;
use App\Services\AccountingService;
use App\Services\BusinessReportingService;
use App\Services\CheckOutService;
use App\Services\LedgerPostingService;
use App\Services\PartnerWalletService;
use App\Services\PermissionResolver;
use App\Services\RevenueJournal;
use App\Services\WalletService;
use Carbon\CarbonImmutable;
use Database\Seeders\RoomSeeder;
use Database\Seeders\RoomTypeSeeder;
use Database\Seeders\TenantSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([TenantSeeder::class, RoomTypeSeeder::class, RoomSeeder::class]);
    $this->partner = PartnerOrganization::create(['name' => 'Structure de test', 'type' => 'company', 'is_active' => true, 'has_convention' => false, 'room_discount_type' => 'none']);
    $this->finance = User::factory()->create(['role' => 'finance_manager']);
    $this->reception = User::factory()->create(['role' => 'reception']);
    $this->customer = Customer::factory()->create(['partner_organization_id' => $this->partner->id]);
    $this->booking = Booking::create(['booking_number' => 'WAL-2026-0001', 'customer_id' => $this->customer->id, 'room_id' => Room::first()->id,
        'status' => BookingStatus::CHECKED_IN, 'check_in' => now()->toDateString(), 'check_out' => now()->addDay()->toDateString(), 'adults_count' => 1,
        'total_nights' => 1, 'price_per_night' => 50000000, 'total_room_amount' => 50000000, 'total_amount' => 50000000, 'balance_due' => 50000000, 'paid_amount' => 0]);
    $this->credit = ['request_key' => (string) Str::uuid(), 'amount' => 10000000, 'method' => 'bank_transfer', 'reference' => 'VIR-TEST-001'];
    $this->debit = ['request_key' => (string) Str::uuid(), 'amount' => 500000, 'booking_id' => $this->booking->id,
        'authorized_by' => 'Responsable de la structure', 'authorization_reference' => 'Accord courriel TEST-001'];
    Storage::fake('local');
});

test('10 millions versés puis une facture de 500000 donnent 9500000 disponibles sans double encaissement', function () {
    $this->actingAs($this->finance)->post(route('partners.wallet.credit', $this->partner), $this->credit)->assertSessionHasNoErrors();
    expect($this->partner->wallet->balance)->toBe(1000000000);
    $this->actingAs($this->reception)->post(route('partners.wallet.debit', $this->partner), $this->debit)->assertSessionHasNoErrors();
    expect($this->partner->wallet->fresh()->balance)->toBe(950000000)
        ->and($this->booking->fresh()->paid_amount)->toBe(50000000)->and($this->booking->fresh()->balance_due)->toBe(0);
    $lines = app(Registres::class)->encaissements(CarbonImmutable::today(), CarbonImmutable::today());
    expect($lines->sum('montant'))->toBe(1000000000)->and($lines->pluck('service_cle')->all())->toBe(['depots_partenaires']);
    $cash = app(AccountingService::class)->recettes(now()->startOfDay(), now()->endOfDay());
    expect($cash['total'])->toBe(1000000000)->and($cash['hebergement'])->toBe(0)->and($cash['depots_partenaires'])->toBe(1000000000);
    $this->get(route('partners.wallet.show', $this->partner))->assertOk()->assertSee('9 500 000')->assertSee('Accord courriel TEST-001');
    $this->get(route('editions.show', ['edition' => 'comptes-depot-partenaires']))->assertOk()->assertSee('Structure de test');
});

test('la simple appartenance à une structure ne déclenche aucun débit', function () {
    app(PartnerWalletService::class)->topup($this->partner, $this->credit, $this->finance);
    $this->actingAs($this->reception)->get(route('bookings.show', $this->booking))->assertOk()->assertSee('compte dépôt');
    expect(WalletEntry::count())->toBe(1)->and($this->booking->fresh()->paid_amount)->toBe(0);
});

test('un accord explicite est obligatoire même avec un portefeuille approvisionné', function () {
    app(PartnerWalletService::class)->topup($this->partner, $this->credit, $this->finance);
    $this->actingAs($this->reception)->post(route('partners.wallet.debit', $this->partner), array_diff_key($this->debit, ['authorized_by' => true, 'authorization_reference' => true]))
        ->assertSessionHasErrors(['authorized_by', 'authorization_reference']);
    expect(WalletEntry::count())->toBe(1)->and($this->booking->fresh()->paid_amount)->toBe(0);
});

test('solde insuffisant et dépassement de facture ne produisent aucune écriture partielle', function () {
    app(PartnerWalletService::class)->topup($this->partner, array_replace($this->credit, ['amount' => 10]), $this->finance);
    $this->actingAs($this->reception)->post(route('partners.wallet.debit', $this->partner), $this->debit)->assertSessionHasErrors('wallet');
    expect(WalletEntry::count())->toBe(1)->and(Payment::count())->toBe(1);
    app(PartnerWalletService::class)->topup($this->partner, array_replace($this->credit, ['request_key' => (string) Str::uuid()]), $this->finance);
    $this->post(route('partners.wallet.debit', $this->partner), array_replace($this->debit, ['amount' => 500001]))->assertSessionHasErrors('wallet');
    expect(WalletEntry::count())->toBe(2)->and(Payment::count())->toBe(2);
});

test('un autre partenaire ne peut pas payer la facture de ce membre', function () {
    $other = PartnerOrganization::create(['name' => 'Autre structure', 'type' => 'company', 'is_active' => true]);
    app(PartnerWalletService::class)->topup($other, $this->credit, $this->finance);
    $this->actingAs($this->reception)->post(route('partners.wallet.debit', $other), $this->debit)->assertSessionHasErrors('wallet');
    expect($other->wallet->balance)->toBe(1000000000)->and($this->booking->fresh()->paid_amount)->toBe(0);
});

test('chaque rôle ne dispose que des opérations autorisées', function () {
    $this->actingAs($this->reception)->postJson(route('partners.wallet.credit', $this->partner), $this->credit)->assertForbidden();
    $this->actingAs($this->finance)->postJson(route('partners.wallet.debit', $this->partner), $this->debit)->assertForbidden();
    $this->actingAs(User::factory()->create(['role' => 'housekeeping_staff']))->getJson(route('partners.wallet.show', $this->partner))->assertForbidden();
    expect(fn () => app(PartnerWalletService::class)->topup($this->partner, $this->credit, $this->reception))
        ->toThrow(AuthorizationException::class);
    expect(fn () => app(PartnerWalletService::class)->settle($this->partner, $this->booking, null, $this->debit, $this->finance))
        ->toThrow(AuthorizationException::class);
    expect(Payment::count())->toBe(0);
});

test('répéter un crédit ou un débit identique ne compte qu’une opération', function () {
    $service = app(PartnerWalletService::class);
    $first = $service->topup($this->partner, $this->credit, $this->finance);
    expect($service->topup($this->partner, $this->credit, $this->finance)->id)->toBe($first->id);
    $payment = $service->settle($this->partner, $this->booking, null, $this->debit, $this->reception);
    expect($service->settle($this->partner, $this->booking, null, $this->debit, $this->reception)->id)->toBe($payment->id)
        ->and(Payment::count())->toBe(2)->and($this->partner->wallet->balance)->toBe(950000000);
});

test('une clé réutilisée avec un autre montant est refusée', function () {
    app(PartnerWalletService::class)->topup($this->partner, $this->credit, $this->finance);
    $this->actingAs($this->finance)->post(route('partners.wallet.credit', $this->partner), array_replace($this->credit, ['amount' => 20]))->assertSessionHasErrors('wallet');
    expect($this->partner->wallet->balance)->toBe(1000000000);
});

test('une annulation restitue le portefeuille et rouvre le solde de facture une seule fois', function () {
    $s = app(PartnerWalletService::class);
    $s->topup($this->partner, $this->credit, $this->finance);
    $invoice = Invoice::create(['booking_id' => $this->booking->id, 'customer_id' => $this->customer->id, 'invoice_number' => 'F-WALLET-01',
        'invoice_date' => today(), 'total_amount' => 50000000, 'subtotal' => 50000000, 'tax_amount' => 0, 'paid_amount' => 0, 'balance_due' => 50000000, 'status' => 'sent', 'kind' => 'sejour']);
    $payment = $s->settle($this->partner, $this->booking, $invoice, $this->debit, $this->reception);
    expect($invoice->fresh()->status)->toBe('paid');
    $key = (string) Str::uuid();
    $reversal = $s->reverse($this->partner, $payment->walletEntry, ['request_key' => $key, 'reason' => 'Accord révoqué'], $this->finance);
    expect($reversal->amount)->toBe(-50000000)->and($invoice->fresh()->balance_due)->toBe(50000000)
        ->and($this->partner->wallet->balance)->toBe(1000000000)->and($this->booking->fresh()->paid_amount)->toBe(0);
    expect($s->reverse($this->partner, $payment->walletEntry, ['request_key' => $key, 'reason' => 'Accord révoqué'], $this->finance)->id)->toBe($reversal->id);
    $this->actingAs($this->finance)->post(route('partners.wallet.reverse', [$this->partner, $payment->walletEntry]), ['request_key' => (string) Str::uuid(), 'reason' => 'Deuxième annulation'])->assertSessionHasErrors('wallet');
});

test('un approvisionnement consommé ne peut pas être annulé au-delà du solde disponible', function () {
    $s = app(PartnerWalletService::class);
    $credit = $s->topup($this->partner, $this->credit, $this->finance);
    $s->settle($this->partner, $this->booking, null, $this->debit, $this->reception);
    $this->actingAs($this->finance)->post(route('partners.wallet.reverse', [$this->partner, $credit->walletEntry]), ['request_key' => (string) Str::uuid(), 'reason' => 'Erreur de versement'])->assertSessionHasErrors('wallet');
    expect($this->partner->wallet->balance)->toBe(950000000);
});

test('les justificatifs sont privés et ne se téléchargent pas par un autre partenaire', function () {
    $file = UploadedFile::fake()->createWithContent('accord.pdf', "%PDF-1.4\nJustificatif de test");
    $s = app(PartnerWalletService::class);
    $s->topup($this->partner, $this->credit, $this->finance);
    $payment = $s->settle($this->partner, $this->booking, null, $this->debit, $this->reception, $file);
    $entry = $payment->walletEntry;
    Storage::disk('local')->assertExists($entry->attachment->path);
    $this->actingAs($this->reception)->get(route('partners.wallet.proof', [$this->partner, $entry]))->assertOk();
    $other = PartnerOrganization::create(['name' => 'Autre', 'type' => 'company']);
    $this->get(route('partners.wallet.proof', [$other, $entry]))->assertNotFound();
});

test('le grand livre porte une avance puis une utilisation sans doubler les comptes de trésorerie', function () {
    $s = app(PartnerWalletService::class);
    $credit = $s->topup($this->partner, $this->credit, $this->finance);
    $debit = $s->settle($this->partner, $this->booking, null, $this->debit, $this->reception);
    $ledger = app(LedgerPostingService::class);
    $a = $ledger->postPayment($credit->fresh());
    $b = $ledger->postPayment($debit->fresh());
    expect($a->lines->where('account_code', Account::PARTNER_DEPOSITS)->sum('credit'))->toBe(1000000000)
        ->and($b->lines->where('account_code', Account::PARTNER_DEPOSITS)->sum('debit'))->toBe(50000000)
        ->and($b->lines->where('account_code', Account::CLIENTS)->sum('credit'))->toBe(50000000)
        ->and($b->lines->whereIn('account_code', [Account::CASH, Account::BANK]))->toHaveCount(0)
        ->and($ledger->postPayment($debit->fresh()))->toBeNull();
});

test('le moteur générique peut servir un client sans dépendre du contexte corporate', function () {
    $s = app(WalletService::class);
    $wallet = $s->forOwner($this->customer);
    expect($wallet->balance)->toBe(0);
    $s->credit($wallet, 9000, (string) Str::uuid(), ['context' => 'autre_usage']);
    $entry = $s->debit($wallet, 4000, (string) Str::uuid());
    expect($wallet->fresh()->balance)->toBe(5000)->and($wallet->owner->is($this->customer))->toBeTrue()->and(Payment::count())->toBe(0);
    $s->reverse($entry, (string) Str::uuid(), ['reason' => 'Correction']);
    expect($wallet->fresh()->balance)->toBe(9000);
});

test('un mouvement enregistré ne peut pas être modifié ou supprimé', function () {
    $s = app(WalletService::class);
    $wallet = $s->forOwner($this->customer);
    $entry = $s->credit($wallet, 100, (string) Str::uuid());
    expect(fn () => $entry->update(['amount' => 999]))->toThrow(LogicException::class)
        ->and(fn () => $entry->delete())->toThrow(LogicException::class);
});

test('une convention signée est jointe au formulaire et téléchargée uniquement avec les droits', function () {
    $file = UploadedFile::fake()->createWithContent('convention.pdf', "%PDF-1.4\nConvention fictive");
    $this->actingAs($this->finance)->post(route('settings.partners.store'), ['name' => 'Avec convention', 'type' => 'company', 'has_convention' => 1,
        'room_discount_type' => 'percent', 'room_discount_value' => 10, 'conventions' => [$file]])->assertSessionHasNoErrors();
    $partner = PartnerOrganization::where('name', 'Avec convention')->sole();
    $attachment = $partner->conventionAttachments()->sole();
    Storage::disk('local')->assertExists($attachment->path);
    expect($partner->isValidOn())->toBeTrue();
    $this->get(route('settings.partners.attachments.show', [$partner, $attachment]))->assertOk();
    $this->get(route('settings.partners.attachments.show', [$this->partner, $attachment]))->assertNotFound();
    $this->actingAs(User::factory()->create(['role' => 'housekeeping_staff']))->getJson(route('settings.partners.attachments.show', [$partner, $attachment]))->assertForbidden();
});

test('une organisation sans convention ne reçoit aucun privilège même si la requête en transmet', function () {
    $this->actingAs($this->finance)->post(route('settings.partners.store'), ['name' => 'Sans convention', 'type' => 'company', 'has_convention' => 0,
        'room_discount_type' => 'percent', 'room_discount_value' => 90, 'restaurant_discount_percent' => 50, 'late_checkout' => 1])->assertSessionHasNoErrors();
    $partner = PartnerOrganization::where('name', 'Sans convention')->sole();
    expect($partner->isValidOn())->toBeFalse()->and($partner->roomDiscountFor(50000))->toBe(0)
        ->and($partner->privilegeLabels())->toBe([])->and($partner->restaurant_discount_percent)->toBe(0);
});

test('un fichier exécutable est refusé sans créer de partenaire ni de crédit', function () {
    $this->actingAs($this->finance)->post(route('settings.partners.store'), ['name' => 'Fichier refusé', 'type' => 'company', 'has_convention' => 1,
        'room_discount_type' => 'none', 'conventions' => [UploadedFile::fake()->createWithContent('convention.php', '<?php echo 1;')]])->assertSessionHasErrors('conventions.0');
    expect(PartnerOrganization::where('name', 'Fichier refusé')->exists())->toBeFalse();
    $this->post(route('partners.wallet.credit', $this->partner), $this->credit + ['proof' => UploadedFile::fake()->create('proof.exe')])->assertSessionHasErrors('proof');
    expect(Payment::count())->toBe(0);
});

test('la comptabilité peut approvisionner sans pouvoir débiter et un rôle en lecture seule ne crédite pas', function () {
    $accountant = User::factory()->create(['role' => 'accountant']);
    $this->actingAs($accountant)->post(route('partners.wallet.credit', $this->partner), $this->credit)->assertSessionHasNoErrors();
    $this->postJson(route('partners.wallet.debit', $this->partner), $this->debit)->assertForbidden();
    $accountant->roles()->updateExistingPivot($accountant->roles->first()->id, ['level' => 'read']);
    app(PermissionResolver::class)->forget();
    $this->actingAs($accountant->fresh())->postJson(route('partners.wallet.credit', $this->partner), array_replace($this->credit, ['request_key' => (string) Str::uuid()]))->assertForbidden();
});

test('une facture complémentaire se règle sans modifier le total payé du séjour', function () {
    $s = app(PartnerWalletService::class);
    $s->topup($this->partner, $this->credit, $this->finance);
    $invoice = Invoice::create(['kind' => 'complementaire', 'booking_id' => $this->booking->id, 'customer_id' => $this->customer->id, 'invoice_number' => 'F-COMPL-WALLET',
        'invoice_date' => today(), 'total_amount' => 100000, 'subtotal' => 100000, 'tax_amount' => 0, 'paid_amount' => 0, 'balance_due' => 100000, 'status' => 'sent']);
    $this->actingAs($this->reception)->post(route('partners.wallet.debit', $this->partner), array_replace($this->debit, ['invoice_id' => $invoice->id, 'amount' => 1000]))->assertSessionHasNoErrors();
    expect($invoice->fresh()->status)->toBe('paid')->and($this->booking->fresh()->paid_amount)->toBe(0);
    app(CheckOutService::class)->recalculateTotals($this->booking);
    expect($this->booking->fresh()->paid_amount)->toBe(0);
});

test('annuler après le départ un règlement effectué avant facture rouvre aussi la facture émise', function () {
    $service = app(PartnerWalletService::class);
    $service->topup($this->partner, $this->credit, $this->finance);
    $payment = $service->settle($this->partner, $this->booking, null, $this->debit, $this->reception);
    $this->actingAs($this->reception);
    $invoice = app(CheckOutService::class)->process($this->booking->fresh());
    expect($invoice->status)->toBe('paid')->and($invoice->paid_amount)->toBe(50000000);

    $service->reverse($this->partner, $payment->walletEntry, ['request_key' => (string) Str::uuid(), 'reason' => 'Prise en charge révoquée après départ'], $this->finance);

    expect($invoice->fresh()->balance_due)->toBe(50000000)->and($invoice->fresh()->paid_amount)->toBe(0)
        ->and($invoice->fresh()->status)->toBe('sent')->and($this->booking->fresh()->balance_due)->toBe(50000000)
        ->and($this->partner->wallet->balance)->toBe(1000000000);
});

test('la caisse et le journal par point de vente ne recomptent pas les règlements sur dépôt', function () {
    $s = app(PartnerWalletService::class);
    $s->topup($this->partner, $this->credit, $this->finance);
    $s->settle($this->partner, $this->booking, null, $this->debit, $this->reception);
    $journal = app(RevenueJournal::class)->forPeriod(now()->startOfDay(), now()->endOfDay());
    expect($journal['total'])->toBe(1000000000)->and($journal['par_point_de_vente']['Dépôts partenaires'])->toBe(1000000000);
    expect(Payment::where('method', 'partner_wallet')->whereNotNull('cash_register_session_id')->count())->toBe(0);
});

test('le reporting compte la prestation réglée sur dépôt et exclut le versement encore disponible', function () {
    $service = app(PartnerWalletService::class);
    $reporting = app(BusinessReportingService::class);
    $service->topup($this->partner, $this->credit, $this->finance);
    expect($reporting->revenue(now()->startOfDay(), now()->endOfDay())['hotel'])->toBe(0);

    $service->settle($this->partner, $this->booking, null, $this->debit, $this->reception);

    expect($reporting->revenue(now()->startOfDay(), now()->endOfDay())['hotel'])->toBe(50000000);
    activerModules(['analytics']);
    $this->actingAs($this->finance)->get(route('analytics.index', ['period' => 'year']))->assertOk()
        ->assertViewHas('hotelRevenue', fn ($amount) => (int) $amount === 50000000);
});

test('un échec après stockage du justificatif annule le crédit et nettoie le fichier', function () {
    WalletEntryAttachment::creating(fn () => throw new RuntimeException('Échec contrôlé du stockage des métadonnées'));
    try {
        $proof = UploadedFile::fake()->createWithContent('preuve.pdf', "%PDF-1.4\nTest de rollback");
        expect(fn () => app(PartnerWalletService::class)->topup($this->partner, $this->credit, $this->finance, $proof))->toThrow(RuntimeException::class);
        expect(Payment::count())->toBe(0)->and(WalletEntry::count())->toBe(0)->and(Storage::disk('local')->allFiles())->toBe([]);
    } finally {
        WalletEntryAttachment::flushEventListeners();
    }
});

test('le CSV conserve l’absence de convention sans modifier le solde du compte dépôt', function () {
    app(PartnerWalletService::class)->topup($this->partner, $this->credit, $this->finance);
    $this->actingAs(User::factory()->create(['role' => 'manager']));
    $export = $this->get(route('settings.partners.export'))->assertOk()->streamedContent();
    expect($export)->toContain('convention')->toContain('Structure de test');
    $this->post(route('settings.partners.import'), ['csv_file' => UploadedFile::fake()->createWithContent('partenaires.csv', $export)])->assertSessionHasNoErrors()->assertSessionHas('success');
    expect($this->partner->fresh()->has_convention)->toBeFalse()->and($this->partner->wallet->balance)->toBe(1000000000)->and(WalletEntry::count())->toBe(1);
});
