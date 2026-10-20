<?php

use App\Enums\RoomStatus;
use App\Models\Account;
use App\Models\HousekeepingAssignment;
use App\Models\HousekeepingTeam;
use App\Models\JournalEntryLine;
use App\Models\MaintenanceRequest;
use App\Models\Role;
use App\Models\Room;
use App\Models\ServiceStore;
use App\Models\ServiceStoreMovement;
use App\Models\ServiceStoreStock;
use App\Models\StockItem;
use App\Models\StockRequisition;
use App\Models\TechnicianSpecialty;
use App\Models\User;
use App\Services\LedgerPostingService;
use App\Services\ServiceStoreService;
use App\Support\RoleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

/*
 * Service technique : tout le personnel demande une intervention, le
 * housekeeping saisit la technique en signalant un problème ; le responsable
 * affecte ou un technicien prend, il intervient avec les pièces de son
 * dépôt, termine, et le demandeur peut rouvrir quelques jours.
 */

beforeEach(function () {
    Carbon::setTestNow('2026-10-19 09:00');
    $this->seed([\Database\Seeders\TenantSeeder::class, \Database\Seeders\RoomTypeSeeder::class, \Database\Seeders\RoomSeeder::class]);
    RoleCatalog::sync();
    activerModules(['hebergement', 'reservations', 'clients', 'housekeeping', 'economat', 'technique', 'accounting', 'comptabilite']);

    $this->responsable = membreTechnique('Alain Responsable', 'tech_manager');
    $this->plombier = membreTechnique('Bruno Plombier', 'technician', ['plomberie']);
    $this->electricien = membreTechnique('Carine Électricienne', 'technician', ['cfo', 'cfa']);
    $this->receptionniste = membreTechnique('Diane Réception', 'reception');
    $this->chambre = Room::where('number', '101')->first();
    $this->chambre->updateStatus(RoomStatus::AVAILABLE);
});

afterEach(fn () => Carbon::setTestNow());

function membreTechnique(string $nom, string $role, array $specialites = []): User
{
    $user = User::factory()->create(['name' => $nom, 'is_active' => true]);
    $user->roles()->sync(Role::where('slug', $role)->pluck('id'));
    foreach ($specialites as $specialite) {
        TechnicianSpecialty::create(['user_id' => $user->id, 'specialty' => $specialite]);
    }

    return $user->fresh();
}

function demanderFuite($test, array $autres = [])
{
    return $test->actingAs($test->receptionniste)->post(route('technique.demandes.store'), $autres + [
        'title' => 'Fuite sous le lavabo', 'description' => 'Le client signale une flaque.',
        'specialty' => 'plomberie', 'priority' => 'urgent', 'department' => 'hebergement',
        'room_id' => $test->chambre->id, 'bloquer_chambre' => 1,
    ]);
}

/** Le dépôt technique, garni de 10 joints à 500 F. */
function depotTechnique(): array
{
    $depot = ServiceStore::create(['name' => 'Magasin plomberie', 'department' => 'technique']);
    $joint = StockItem::create(['name' => 'Joint de siphon', 'reference' => 'JS-32', 'unit' => 'pièce', 'average_cost' => 50_000]);
    app(ServiceStoreService::class)->receive($depot, $joint, 10, 50_000, ServiceStoreMovement::SOURCE_IMPORT);

    return [$depot, $joint];
}

test('la réception demande une intervention : la chambre est retirée de la vente, le responsable et le plombier sont alertés', function () {
    demanderFuite($this)->assertRedirect();

    $demande = MaintenanceRequest::sole();
    expect($demande->reference)->toBe('DIT-2026-0001')
        ->and($demande->status)->toBe(MaintenanceRequest::STATUS_OPEN)
        ->and($demande->room_blocked)->toBeTrue()
        ->and($this->chambre->fresh()->status)->toBe(RoomStatus::MAINTENANCE)
        ->and($demande->events()->pluck('event')->all())->toBe(['created']);

    expect($this->responsable->notifications()->first()?->data['title'])->toBe("Demande d'intervention urgente")
        ->and($this->plombier->notifications()->count())->toBe(1)
        // L'électricienne n'est pas de la spécialité.
        ->and($this->electricien->notifications()->count())->toBe(0);

    // La réception suit sa demande ; elle ne voit pas le tableau du service.
    $this->get(route('technique.demandes.index'))->assertOk()->assertSee('Fuite sous le lavabo')->assertSee("Mes demandes d'intervention");
    $this->get(route('technique.index'))->assertSessionHas('access_denied_popup');
});

test("affectée, commencée, avec ses pièces, puis terminée : la chambre repasse au ménage, le demandeur est prévenu, les pièces sont une charge de l'hébergement", function () {
    [$depot, $joint] = depotTechnique();
    demanderFuite($this);
    $demande = MaintenanceRequest::sole();

    $this->actingAs($this->responsable)->post(route('technique.demandes.assign', $demande), ['technician_id' => $this->plombier->id])
        ->assertSessionHas('success');
    expect($demande->fresh()->status)->toBe(MaintenanceRequest::STATUS_ASSIGNED)
        ->and($this->plombier->notifications()->get()->pluck('data.title'))->toContain('Intervention à faire');

    // L'électricienne n'y touche pas.
    $this->actingAs($this->electricien)->post(route('technique.demandes.start', $demande))->assertForbidden();

    $this->actingAs($this->plombier);
    $this->get(route('technique.demandes.show', $demande))->assertOk()
        ->assertSee("Commencer l'intervention", false)->assertSee('Joint de siphon')->assertSee('Fuite sous le lavabo');
    $this->post(route('technique.demandes.start', $demande))->assertSessionHas('success');
    $this->post(route('technique.demandes.parts', $demande), ['depot' => $depot->id, 'pieces' => [$joint->id => 2]])->assertSessionHas('success');
    expect((float) ServiceStoreStock::where('service_store_id', $depot->id)->value('current_stock'))->toBe(8.0);

    Carbon::setTestNow('2026-10-19 10:30');
    $this->post(route('technique.demandes.complete', $demande), ['resolution' => 'Joint changé, plus de fuite.'])->assertSessionHas('success');

    $demande->refresh();
    expect($demande->status)->toBe(MaintenanceRequest::STATUS_DONE)
        ->and($demande->dureeMinutes())->toBe(90)
        ->and($demande->valeurDesPieces())->toBe(100_000)
        ->and($this->chambre->fresh()->status)->toBe(RoomStatus::DIRTY)
        ->and($this->receptionniste->notifications()->first()?->data['title'])->toBe('Intervention terminée')
        ->and($demande->events()->pluck('event')->all())->toBe(['created', 'assigned', 'started', 'parts', 'completed']);

    app(LedgerPostingService::class)->postDay(Carbon::parse('2026-10-19'));
    $charge = JournalEntryLine::query()->where('account_code', Account::variationFor($joint->stockAccount()))->where('debit', '>', 0)->sole();
    expect($charge->debit)->toBe(100_000)
        ->and($charge->analytic_center)->toBe(JournalEntryLine::CENTER_ACCOMMODATION);
});

test("un technicien prend une demande libre ; elle n'est plus à prendre", function () {
    demanderFuite($this, ['bloquer_chambre' => 0]);
    $demande = MaintenanceRequest::sole();

    $this->actingAs($this->plombier)->post(route('technique.demandes.take', $demande))->assertSessionHas('success');
    expect($demande->fresh()->assigned_to)->toBe($this->plombier->id)
        ->and($this->chambre->fresh()->status)->toBe(RoomStatus::AVAILABLE);

    $this->actingAs($this->electricien)->post(route('technique.demandes.take', $demande))->assertSessionHas('error');
    expect($demande->fresh()->assigned_to)->toBe($this->plombier->id);
});

test('le demandeur rouvre une intervention dans les sept jours, pas au-delà', function () {
    demanderFuite($this);
    $demande = MaintenanceRequest::sole();
    $this->actingAs($this->plombier)->post(route('technique.demandes.take', $demande));
    $this->post(route('technique.demandes.complete', $demande), ['resolution' => 'Serré le raccord.']);

    Carbon::setTestNow('2026-10-22 09:00');
    $this->actingAs($this->receptionniste)->post(route('technique.demandes.reopen', $demande), ['motif' => 'Ça fuit encore.'])
        ->assertSessionHas('success');
    expect($demande->fresh()->status)->toBe(MaintenanceRequest::STATUS_ASSIGNED)
        ->and($demande->fresh()->reopened_count)->toBe(1)
        ->and($this->chambre->fresh()->status)->toBe(RoomStatus::MAINTENANCE)
        ->and($this->plombier->notifications()->get()->pluck('data.title'))->toContain('Intervention rouverte');

    // Rouverte, la demande garde l'ancien compte rendu — sans le faire passer pour celui d'une intervention finie.
    $this->actingAs($this->plombier)->get(route('technique.demandes.show', $demande))->assertOk()
        ->assertSee('Compte rendu précédent')->assertDontSee("Compte rendu de l'intervention");

    $this->actingAs($this->plombier)->post(route('technique.demandes.complete', $demande), ['resolution' => 'Raccord remplacé.']);
    Carbon::setTestNow('2026-11-02 09:00');
    $this->actingAs($this->receptionniste)->post(route('technique.demandes.reopen', $demande), ['motif' => 'Encore.'])
        ->assertSessionHas('error');
    expect($demande->fresh()->status)->toBe(MaintenanceRequest::STATUS_DONE);
});

test("annulée, la demande prévient le technicien qui l'avait reçue et le responsable, pas son auteur", function () {
    demanderFuite($this, ['bloquer_chambre' => 0]);
    $demande = MaintenanceRequest::sole();
    $this->actingAs($this->responsable)->post(route('technique.demandes.assign', $demande), ['technician_id' => $this->plombier->id]);

    $this->actingAs($this->receptionniste)->post(route('technique.demandes.cancel', $demande), ['motif' => 'Réparée par le client.'])->assertSessionHas('success');

    expect($this->plombier->notifications()->get()->pluck('data.title'))->toContain('Demande annulée')
        ->and($this->responsable->notifications()->get()->pluck('data.title'))->toContain('Demande annulée')
        ->and($this->receptionniste->notifications()->get()->pluck('data.title'))->not->toContain('Demande annulée')
        // L'électricienne n'avait pas cette demande : rien.
        ->and($this->electricien->notifications()->get()->pluck('data.title'))->not->toContain('Demande annulée');
});

test("un compte rendu oublié est refusé, et l'erreur ne s'affiche qu'une fois", function () {
    demanderFuite($this, ['bloquer_chambre' => 0]);
    $demande = MaintenanceRequest::sole();
    $this->actingAs($this->plombier)->post(route('technique.demandes.take', $demande));

    $this->from(route('technique.demandes.show', $demande))->post(route('technique.demandes.complete', $demande), ['resolution' => ''])
        ->assertSessionHasErrors('resolution');
    $html = $this->get(route('technique.demandes.show', $demande))->getContent();

    expect($demande->fresh()->status)->toBe(MaintenanceRequest::STATUS_ASSIGNED)
        ->and(substr_count($html, 'Dites ce qui a été fait'))->toBe(1);
});

test('le housekeeping signale un problème : une demande part au service technique', function () {
    $gouvernante = membreTechnique('Eva Gouvernante', 'housekeeping_supervisor');
    $equipe = HousekeepingTeam::create(['name' => 'Étage 1', 'leader_id' => $gouvernante->id, 'is_active' => true]);
    $equipe->members()->sync([$gouvernante->id]);
    $this->chambre->updateStatus(RoomStatus::CLEANING);
    HousekeepingAssignment::create(['housekeeping_team_id' => $equipe->id, 'room_id' => $this->chambre->id,
        'status' => 'in_progress', 'assigned_at' => now(), 'started_at' => now()]);

    $this->actingAs($gouvernante)->post(route('housekeeping.issue', $this->chambre), ['issue_notes' => 'Prise électrique arrachée'])
        ->assertSessionHas('success', fn ($m) => str_contains($m, 'DIT-2026-0001'));

    $demande = MaintenanceRequest::sole();
    expect($demande->source)->toBe(MaintenanceRequest::SOURCE_HOUSEKEEPING)
        ->and($demande->department)->toBe('housekeeping')
        ->and($demande->room_id)->toBe($this->chambre->id)
        // À qualifier : toute l'équipe est alertée.
        ->and($this->electricien->notifications()->count())->toBe(1)
        ->and($this->plombier->notifications()->count())->toBe(1);

    // Terminée, l'affectation de ménage reprend.
    $this->actingAs($this->electricien)->post(route('technique.demandes.take', $demande));
    $this->post(route('technique.demandes.complete', $demande), ['resolution' => 'Prise refixée.']);
    expect(HousekeepingAssignment::sole()->status)->toBe('in_progress')
        ->and($gouvernante->notifications()->get()->pluck('data.title'))->toContain('Intervention terminée');
});

test('sans le module, ni écran technique ni demande au signalement', function () {
    activerModules(['hebergement', 'reservations', 'housekeeping', 'economat']);

    $this->actingAs($this->receptionniste)->get(route('technique.demandes.create'))->assertStatus(403);
    expect(collect(\App\Support\Navigation::getLauncherGroups($this->responsable))->pluck('id'))->not->toContain('technique');
});

test("la technique adresse ses réquisitions à l'économat, visées par son responsable", function () {
    $this->actingAs($this->plombier);
    expect(\App\Support\Navigation::groupeDesDemandes($this->plombier))->toBe('technique')
        ->and(StockRequisition::visaRequis($this->plombier, 'technique'))->toBeTrue()
        ->and(StockRequisition::servicesDirigesPar($this->responsable))->toContain('technique');

    $this->get(route('economat.requisitions.create'))->assertOk()->assertSee('Technique / Maintenance');
});

test("l'équipe : le responsable règle les spécialités, le tableau montre ce qui attend", function () {
    demanderFuite($this);

    $this->actingAs($this->responsable)->get(route('technique.index'))->assertOk()->assertSee('Fuite sous le lavabo')->assertSee('À prendre');
    $this->put(route('technique.equipe.update', $this->electricien), ['specialites' => ['cfo', 'climatisation']])->assertSessionHas('success');
    expect($this->electricien->technicianSpecialties()->pluck('specialty')->sort()->values()->all())->toBe(['cfo', 'climatisation']);

    $this->actingAs($this->plombier)->put(route('technique.equipe.update', $this->electricien), ['specialites' => []])
        ->assertSessionHas('access_denied_popup');
});
