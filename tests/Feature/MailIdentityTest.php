<?php

use App\Mail\CheckinCodeMail;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\User;
use App\Support\MailIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * L'adresse sous laquelle l'établissement écrit à ses clients et à leurs
 * mandataires se règle depuis les paramètres, plus depuis le seul .env.
 *
 * Ces tests verrouillent les deux faces : ce que l'écran enregistre, et ce que
 * le message emporte réellement — y compris quand un champ est laissé vide, où
 * le repli doit produire un expéditeur valide plutôt qu'une adresse absente.
 */

function mailManager(): User
{
    test()->seed(\Database\Seeders\TenantSeeder::class);
    MailIdentity::forget();

    return User::factory()->create(['role' => 'manager', 'is_active' => true]);
}

/** Enregistre l'identité de courriel via l'écran des paramètres. */
function enregistreIdentite(array $champs)
{
    return test()->post(route('settings.update', ['tab' => 'general']), [
        'settings' => $champs,
    ]);
}

function reglagesGeneral(): array
{
    MailIdentity::forget();

    return Tenant::first()->settings['general'] ?? [];
}

test("l'onglet général propose les trois champs d'expédition", function () {
    $this->actingAs(mailManager());

    $html = $this->get(route('settings.index', ['tab' => 'general']))->assertOk()->getContent();

    expect($html)->toContain('name="settings[mail_from_address]"')
        ->and($html)->toContain('name="settings[mail_from_name]"')
        ->and($html)->toContain('name="settings[mail_reply_to]"')
        ->and($html)->toContain('Courriel adressé aux clients');
});

test("l'adresse d'expédition est enregistrée", function () {
    $this->actingAs(mailManager());

    enregistreIdentite([
        'mail_from_address' => 'reservations@villaboutanga.cm',
        'mail_from_name'    => 'Réservations Villa Boutanga',
        'mail_reply_to'     => 'accueil@villaboutanga.cm',
    ])->assertRedirect();

    expect(reglagesGeneral())
        ->toMatchArray([
            'mail_from_address' => 'reservations@villaboutanga.cm',
            'mail_from_name'    => 'Réservations Villa Boutanga',
            'mail_reply_to'     => 'accueil@villaboutanga.cm',
        ]);
});

test('une adresse mal formée est refusée', function () {
    $this->actingAs(mailManager());

    enregistreIdentite(['mail_from_address' => 'pas-une-adresse'])
        ->assertSessionHasErrors('settings.mail_from_address');

    expect(reglagesGeneral())->not->toHaveKey('mail_from_address');
});

test('un champ vidé rend la main au repli plutôt que d\'enregistrer du vide', function () {
    $this->actingAs(mailManager());

    enregistreIdentite(['mail_from_address' => 'reservations@villaboutanga.cm']);
    enregistreIdentite(['mail_from_address' => '   ']);

    expect(reglagesGeneral()['mail_from_address'])->toBeNull()
        // Et l'expéditeur reste valide : c'est le .env qui reprend la main.
        ->and(MailIdentity::from()->address)->toBe(config('mail.from.address'));
});

test('sans réglage, le nom de l\'établissement sert de nom d\'expéditeur', function () {
    $this->actingAs(mailManager());
    Tenant::first()->update(['name' => 'Villa Boutanga', 'email' => null]);
    MailIdentity::forget();

    $expediteur = MailIdentity::from();

    expect($expediteur->address)->toBe(config('mail.from.address'))
        ->and($expediteur->name)->toBe('Villa Boutanga')
        ->and(MailIdentity::replyTo())->toBe([]);
});

test("l'adresse de l'établissement sert de réponse par défaut", function () {
    $this->actingAs(mailManager());
    Tenant::first()->update(['email' => 'contact@villaboutanga.cm']);
    MailIdentity::forget();

    $reponse = MailIdentity::replyTo();

    expect($reponse)->toHaveCount(1)
        ->and($reponse[0]->address)->toBe('contact@villaboutanga.cm');
});

test('une réponse identique à l\'expéditeur n\'est pas répétée', function () {
    $this->actingAs(mailManager());

    enregistreIdentite([
        'mail_from_address' => 'reservations@villaboutanga.cm',
        'mail_reply_to'     => 'reservations@villaboutanga.cm',
    ]);
    MailIdentity::forget();

    expect(MailIdentity::replyTo())->toBe([]);
});

test('le message au client part sous l\'adresse réglée', function () {
    $this->actingAs(mailManager());
    $this->seed([\Database\Seeders\RoomTypeSeeder::class, \Database\Seeders\RoomSeeder::class]);

    enregistreIdentite([
        'mail_from_address' => 'reservations@villaboutanga.cm',
        'mail_from_name'    => 'Villa Boutanga — Réservations',
        'mail_reply_to'     => 'accueil@villaboutanga.cm',
    ]);
    MailIdentity::forget();

    $client = Customer::create(['first_name' => 'Aminatou', 'last_name' => 'Njoya', 'email' => 'aminatou@exemple.cm']);
    $booking = Booking::create([
        'booking_number' => 'BK-TEST-01',
        'customer_id'    => $client->id,
        'room_id'        => Room::where('number', '101')->value('id'),
        'check_in'       => now()->addDay(),
        'check_out'      => now()->addDays(2),
        'total_nights'   => 1,
        'price_per_night' => 4500000,
        'total_room_amount' => 4500000,
        'adults_count'   => 1,
        'children_count' => 0,
        'total_amount'   => 4500000,
        'status'         => 'confirmed',
    ]);

    $enveloppe = (new CheckinCodeMail($booking))->envelope();

    expect($enveloppe->from->address)->toBe('reservations@villaboutanga.cm')
        ->and($enveloppe->from->name)->toBe('Villa Boutanga — Réservations')
        ->and($enveloppe->replyTo[0]->address)->toBe('accueil@villaboutanga.cm')
        // L'objet ne nomme plus un établissement en dur.
        ->and($enveloppe->subject)->toContain('Villa Boutanga')
        ->and($enveloppe->subject)->toContain('BK-TEST-01');
});

test("un rôle non manager ne peut pas changer l'adresse d'envoi", function () {
    mailManager();
    $this->actingAs(User::factory()->create(['role' => 'reception', 'is_active' => true]));

    enregistreIdentite(['mail_from_address' => 'pirate@exemple.cm'])->assertForbidden();

    expect(reglagesGeneral())->not->toHaveKey('mail_from_address');
});
