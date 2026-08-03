<?php

use App\Models\RoomType;
use App\Models\ServiceItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Valeurs des cases à cocher du formulaire de pack.
 *
 * Ces cases portaient une liaison « :value » associée à « x-model ». Alpine
 * s'approprie la propriété value d'une case pilotée par x-model : la liaison
 * était écrasée et chaque case partait avec une valeur vide. Cocher un repas
 * envoyait donc une chaîne vide, et la création échouait sur
 * « The selected meals.0 is invalid » — sans que rien à l'écran n'explique
 * pourquoi.
 *
 * Les valeurs sont désormais écrites par le serveur. Ces tests le verrouillent :
 * revenir à une liaison dynamique casserait de nouveau la création, en silence.
 */

function packFormManager(): User
{
    test()->seed(\Database\Seeders\TenantSeeder::class);

    return User::factory()->create(['role' => 'manager', 'is_active' => true]);
}

test('les repas portent leur valeur littérale', function () {
    $this->actingAs(packFormManager());

    $html = $this->get(route('settings.index', ['tab' => 'hebergement']))->assertOk()->getContent();

    foreach (['breakfast', 'lunch', 'dinner'] as $repas) {
        expect($html)->toContain('type="checkbox" value="' . $repas . '"');
    }
});

test('les types de chambre portent leur identifiant', function () {
    $type = RoomType::create([
        'code' => 'STD', 'name' => 'Chambre Standard',
        'base_capacity' => 2, 'max_capacity' => 3,
        'base_price' => 4500000, 'is_active' => true,
    ]);

    $this->actingAs(packFormManager());

    $this->get(route('settings.index', ['tab' => 'hebergement']))
        ->assertOk()
        ->assertSee('type="checkbox" value="' . $type->id . '"', false);
});

test('les prestations portent leur identifiant', function () {
    $service = ServiceItem::create([
        'name' => 'Blanchisserie', 'category' => 'blanchisserie',
        'price' => 500000, 'is_active' => true,
    ]);

    $this->actingAs(packFormManager());

    $this->get(route('settings.index', ['tab' => 'hebergement']))
        ->assertOk()
        ->assertSee('type="checkbox" value="' . $service->id . '"', false);
});

test('aucune case du formulaire ne dépend d\'une liaison dynamique', function () {
    $this->actingAs(packFormManager());

    $html = $this->get(route('settings.index', ['tab' => 'hebergement']))->assertOk()->getContent();

    // Le HTML servi ne doit plus contenir de « :value » sur une case : c'est
    // la forme qui produisait des valeurs vides.
    expect($html)->not->toContain('type="checkbox" :value');
});

test('le mode de facturation propose ses options sans JavaScript', function () {
    $this->actingAs(packFormManager());

    $html = $this->get(route('settings.index', ['tab' => 'hebergement']))->assertOk()->getContent();

    // Générées par x-for, ces options disparaissaient si le JavaScript ne
    // démarrait pas, et le champ obligatoire partait vide.
    foreach (array_keys(\App\Models\RoomPackage::PRICING_MODES) as $mode) {
        expect($html)->toContain('<option value="' . $mode . '"');
    }
});

test('le formulaire Alpine a sa forme complète dès l\'initialisation', function () {
    $this->actingAs(packFormManager());

    $html = $this->get(route('settings.index', ['tab' => 'hebergement']))->assertOk()->getContent();

    // C'était la cause racine : parti d'un objet vide, le tableau visé par
    // x-model valait undefined à la liaison, et Alpine vidait l'attribut value
    // de chaque case. init() doit remplir le formulaire avant cette liaison.
    expect($html)->toContain('init() {')
        ->and($html)->toContain('this.form = this.blank();');
});

test('les types de chambre sont cochés par défaut', function () {
    RoomType::create([
        'code' => 'STD', 'name' => 'Chambre Standard',
        'base_capacity' => 2, 'max_capacity' => 3,
        'base_price' => 4500000, 'is_active' => true,
    ]);

    $this->actingAs(packFormManager());

    $html = $this->get(route('settings.index', ['tab' => 'hebergement']))->assertOk()->getContent();

    // Un pack s'adresse par défaut à tout le parc : on décoche ce qu'on exclut,
    // au lieu de l'ancien « rien de coché vaut tout », illisible à l'écran.
    expect($html)->toContain('room_type_ids: roomTypes.map((t) => t.id)');
});
