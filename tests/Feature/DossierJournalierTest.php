<?php

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\CashRegisterSession;
use App\Models\Customer;
use App\Models\Journal;
use App\Models\NightAudit;
use App\Models\Payment;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Services\LedgerService;
use App\Services\NightAuditService;
use App\Support\RoleCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;

uses(RefreshDatabase::class);

beforeEach(function () {
    RoleCatalog::sync();
    activerModules(['accounting']);
    $this->travelTo(CarbonImmutable::parse('2026-10-09 20:00'));
});

function utilisateurDuDossier(string $role): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->roles()->sync(Role::where('slug', $role)->pluck('id'));

    return $user->fresh();
}

function sejourDuDossier(array $attributs = []): Booking
{
    $type = RoomType::create(['name' => 'Standard', 'code' => 'STD', 'base_capacity' => 2, 'max_capacity' => 2,
        'base_price' => 3_000_000]);
    $chambre = Room::create(['number' => '101', 'room_type_id' => $type->id, 'is_active' => true, 'floor' => 1]);
    $client = Customer::create(['first_name' => 'Marie', 'last_name' => 'Njoya']);

    return Booking::create($attributs + [
        'booking_number' => 'BKG-DOSSIER', 'room_id' => $chambre->id, 'customer_id' => $client->id,
        'check_in' => '2026-10-09', 'actual_check_in' => '2026-10-09 13:00', 'check_out' => '2026-10-11',
        'status' => 'checked_in', 'adults_count' => 2, 'total_nights' => 2, 'price_per_night' => 3_000_000,
        'total_room_amount' => 6_000_000, 'total_amount' => 6_000_000, 'balance_due' => 6_000_000,
    ]);
}

test('le dossier affiche et exporte les mêmes feuilles et montants pour la date choisie', function () {
    $sejour = sejourDuDossier(['notes' => '=2+3']);
    Payment::create(['booking_id' => $sejour->id, 'customer_id' => $sejour->customer_id, 'amount' => 1_500_000,
        'method' => 'orange_money', 'status' => 'completed', 'paid_at' => '2026-10-09 12:00',
        'payment_origin' => Payment::ORIGIN_ACOMPTE_RESERVATION, 'reference' => 'PAY-DOSSIER']);
    $this->actingAs(utilisateurDuDossier('admin'));
    $date = ['jour' => '2026-10-09'];

    $this->get(route('editions.index'))->assertOk()->assertSee(route('editions.dossier'));
    $documents = $this->get(route('editions.dossier', $date))->assertOk()->assertSee('Provisoire')
        ->assertSee('PAY-DOSSIER', false)->viewData('documents');

    expect($documents['ventes']->lesTotaux()['montant'])->toBe(3_000_000)
        ->and($documents['encaissements']->lesTotaux()['montant'])->toBe(1_500_000)
        ->and($documents['recap-encaissements']->lesTotaux()['montant'])->toBe(1_500_000)
        ->and($documents['occupation']->lesTotaux()['taux'])->toEqual(100)
        ->and($documents['mouvements']->lesLignes()->first()['type'])->toBe('Acompte de réservation');

    $this->get(route('editions.dossier.print', $date + ['feuille' => 'arrivees']))
        ->assertOk()->assertSee('Arrivées de la journée')->assertDontSee('Journal des ventes');
    $pdf = $this->get(route('editions.dossier.export', $date))->assertOk()->assertHeader('content-type', 'application/pdf');
    expect($pdf->getContent())->toStartWith('%PDF');

    $xlsx = $this->get(route('editions.dossier.export', $date + ['format' => 'excel']))->assertOk();
    $fichier = tempnam(sys_get_temp_dir(), 'dossier-journalier-');
    try {
        file_put_contents($fichier, $xlsx->streamedContent());
        $classeur = IOFactory::load($fichier);
        expect($classeur->getSheetCount())->toBe(count($documents));
        $encaissements = $classeur->getSheetByName('Journal des encaissements');
        $montant = $encaissements->getCell('H' . substr($encaissements->getFreezePane(), 1));
        expect($montant->getValue())->toEqual(15_000)
            ->and($montant->getDataType())->toBe(DataType::TYPE_NUMERIC);
        $arrivees = $classeur->getSheetByName('Arrivées de la journée');
        $notes = $arrivees->getCell('N' . substr($arrivees->getFreezePane(), 1));
        expect($notes->getValue())->toBe('=2+3')
            ->and($notes->getDataType())->toBe(DataType::TYPE_STRING);
    } finally {
        unlink($fichier);
    }

    $export = AuditLog::query()->where('event_type', 'export')->latest('id')->firstOrFail();
    expect($export->payload['jour'])->toBe('2026-10-09')
        ->and($export->payload['format'])->toBe('excel')
        ->and($export->payload['feuilles'])->toContain('arrivees', 'encaissements');
});

test('la réception ne peut ouvrir ni exporter les feuilles comptables', function () {
    $this->actingAs(utilisateurDuDossier('reception'));
    $selection = ['jour' => '2026-10-09', 'feuille' => 'balance-agee'];

    $this->get(route('editions.dossier'))->assertOk()->assertSee('Arrivées de la journée')
        ->assertDontSee('Balance âgée clients par mois')->assertDontSee('Journal des encaissements');
    $this->get(route('editions.dossier', $selection))->assertForbidden();
    $this->get(route('editions.dossier.print', $selection))->assertForbidden();
    $this->get(route('editions.dossier.export', $selection + ['format' => 'excel']))->assertForbidden();
    $this->getJson(route('editions.dossier', ['jour' => '2026-02-30']))->assertUnprocessable();
});

test('une clôture fige les lignes et les cumuls suivants reprennent les valeurs archivées', function () {
    $sejour = sejourDuDossier();
    $audit = app(NightAuditService::class)->run(CarbonImmutable::parse('2026-10-09'));
    $sejour->update(['price_per_night' => 5_000_000, 'notes' => 'Modifié après clôture']);
    $this->actingAs(utilisateurDuDossier('admin'));

    $fige = $this->get(route('editions.dossier', ['jour' => '2026-10-09']))->assertOk()
        ->assertSee('Clôture n° ' . $audit->id)->assertDontSee('Modifié après clôture')->viewData('documents');
    expect($fige['occupation']->lesTotaux()['revenu'])->toBe(3_000_000)
        ->and($fige['ventes']->lesTotaux()['montant'])->toBe(3_000_000);

    $suivant = $this->get(route('editions.dossier', ['jour' => '2026-10-10']))->assertOk()->viewData('documents');
    expect($suivant['historique-occupation']->lesLignes()->pluck('revenu')->all())->toEqual([3_000_000, 5_000_000])
        ->and($suivant['comparatif']->lesLignes()->first()['mois'])->toBe(8_000_000);
});

test('une ancienne clôture est signalée et les caisses à cheval sur deux jours sont conservées', function () {
    $admin = utilisateurDuDossier('admin');
    NightAudit::create(['audit_date' => '2026-10-08', 'closed_at' => '2026-10-09 00:30']);
    CashRegisterSession::create(['user_id' => $admin->id, 'module' => 'reception',
        'opened_at' => '2026-10-08 22:00', 'closed_at' => '2026-10-09 02:00',
        'opening_amount' => 10_000_000, 'theoretical_closing_amount' => 15_000_000,
        'actual_closing_amount' => 14_900_000, 'discrepancy_amount' => -100_000]);
    $this->actingAs($admin);

    $ancien = $this->get(route('editions.dossier', ['jour' => '2026-10-08', 'feuille' => 'caisses']))
        ->assertOk()->assertSee('Dossier reconstitué')->viewData('documents');
    $jour = $this->get(route('editions.dossier', ['jour' => '2026-10-09', 'feuille' => 'caisses']))
        ->assertOk()->viewData('documents');
    expect($ancien['caisses']->lesLignes()->sole()['ecart'])->toBeNull()
        ->and($jour['caisses']->lesLignes()->sole()['ecart'])->toBe(-100_000);
});

test('la balance âgée date les créances en mois calendaires et écarte les écritures futures', function () {
    $sejour = sejourDuDossier();
    $ledger = app(LedgerService::class);
    foreach (['2026-06-20' => 1_000, '2026-09-30' => 2_000, '2026-10-01' => 3_000, '2026-10-10' => 4_000] as $date => $montant) {
        $ledger->post(Journal::SALES, CarbonImmutable::parse($date), 'Facturation', [
            ['account' => Account::CLIENTS, 'debit' => $montant, 'auxiliary' => $sejour->customer],
            ['account' => Account::REVENUE_ACCOMMODATION, 'credit' => $montant],
        ]);
    }

    $this->actingAs(utilisateurDuDossier('admin'));
    $documents = $this->get(route('editions.dossier', ['jour' => '2026-10-09', 'feuille' => 'balance-agee']))
        ->assertOk()->viewData('documents');
    expect($documents['balance-agee']->lesTotaux())->toBe([
        'total' => 6_000, 'm0' => 3_000, 'm1' => 2_000, 'm2' => 0, 'm3' => 0, 'avant' => 1_000,
    ]);
});
